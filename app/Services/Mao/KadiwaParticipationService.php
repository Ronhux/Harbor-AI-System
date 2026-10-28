<?php

namespace App\Services\Mao;

use App\Models\KadiwaApplication;
use App\Models\KadiwaApplicationStatusEvent;
use App\Models\KadiwaEvent;
use App\Models\KadiwaParticipationReview;
use App\Models\Producer;
use App\Models\User;
use App\Policies\KadiwaWorkflowPolicy;
use App\Support\WorkflowTimestamp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Producer participation in KADIWA events, and the authorized review of those applications.
 *
 * A KADIWA participation approval means only that a producer may sell at that venue on that
 * date. It is not a purchase, order, award, procurement decision, payment or reservation, and
 * it grants no procurement authority anywhere else in HarborAI.
 */
final class KadiwaParticipationService
{
    /** Statuses in which an application can still change and still occupies a slot. */
    private const LIVE_STATUSES = ['pending', 'approved', 'needs_information'];

    public function __construct(
        private KadiwaWorkflowPolicy $policy,
        private WorkflowDraftPreparation $drafts,
    ) {}

    public function ready(): void
    {
        $this->policy->ready();
    }

    public function mine(User $actor): array
    {
        $this->ready();
        $producer = $this->policy->participant($actor);

        return KadiwaApplication::where('producer_id', $producer->getKey())
            ->orderByDesc('id')->limit(100)->get()
            ->map(fn (KadiwaApplication $a) => $this->presentApplication($a))->all();
    }

    public function show(User $actor, int $id): array
    {
        $this->ready();
        $application = KadiwaApplication::with('event')->findOrFail($id);
        abort_unless($this->policy->viewsApplication($actor, $application), 403);

        $details = (array) ($application->details ?? []);

        return [
            'application' => $this->presentApplication($application),
            'details' => [
                'commodities' => array_values((array) ($details['commodities'] ?? [])),
                'participation_notes' => $details['participation_notes'] ?? null,
            ],
            'history' => KadiwaApplicationStatusEvent::where('application_id', $application->getKey())
                ->orderBy('id')->limit((int) config('kadiwa.limits.detail_rows', 200))->get()
                ->map(fn (KadiwaApplicationStatusEvent $e) => [
                    'action' => (string) $e->action,
                    'previous_status' => $e->previous_status === null ? null : (string) $e->previous_status,
                    'new_status' => (string) $e->new_status,
                    'created_at' => WorkflowTimestamp::format($e->created_at),
                ])->all(),
            // Reasons a producer must read to understand a decision. Reviewer identity is withheld.
            'review_notes' => KadiwaParticipationReview::where('application_id', $application->getKey())
                ->orderBy('id')->limit((int) config('kadiwa.limits.detail_rows', 200))->get()
                ->map(fn (KadiwaParticipationReview $r) => [
                    'action' => (string) $r->decision,
                    'reason' => (string) $r->reason,
                    'created_at' => WorkflowTimestamp::format($r->created_at),
                ])->all(),
        ];
    }

    public function apply(User $actor, int $eventId, array $input): KadiwaApplication
    {
        $this->ready();
        $producer = $this->policy->participant($actor);
        $event = KadiwaEvent::whereKey($eventId)->first();
        abort_unless($event && $event->status !== 'draft', 422, 'Select a published event.');

        return PreparedWorkflowTransaction::run(function () use ($actor, $producer, $event, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'producer' => $producer->getKey(),
                'event' => $event->getKey(), 'kadiwa' => true]);
            $lockedProducer = Producer::whereKey($producer->getKey())->lockForUpdate()->firstOrFail();
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(app(\App\Policies\MaoIdentityPolicy::class)->producer($current, $lockedProducer), 403);
            $lockedEvent = KadiwaEvent::whereKey($event->getKey())->lockForUpdate()->firstOrFail();

            $this->assertEligible($lockedProducer, $lockedEvent);
            $draft = $this->drafts->kadiwaApplication($current, $lockedProducer,
                $lockedEvent->only(['id', 'status', 'categories', 'opens_at', 'closes_at']), $input);
            $details = $draft['submitted_details'];
            $hash = $this->hashDetails($details);

            // An identical retry of the same submission key returns the same record.
            $replay = KadiwaApplication::where('submission_key', $details['submission_key'])->lockForUpdate()->first();
            if ($replay) {
                abort_unless((int) $replay->producer_id === (int) $lockedProducer->getKey()
                    && (int) $replay->event_id === (int) $lockedEvent->getKey()
                    && hash_equals((string) $replay->submission_hash, $hash), 409,
                    'That submission key was already used for different details.');

                return $replay;
            }

            $existing = KadiwaApplication::where('producer_id', $lockedProducer->getKey())
                ->where('event_id', $lockedEvent->getKey())
                ->whereIn('status', self::LIVE_STATUSES)->lockForUpdate()->first();
            abort_if($existing, 409, 'An application for this event already exists.');

            $application = new KadiwaApplication();
            $this->persist($application, [
                'event_id' => $lockedEvent->getKey(),
                'producer_id' => $lockedProducer->getKey(),
                'submission_key' => $details['submission_key'],
                'submission_hash' => $hash,
                'details' => ['commodities' => $details['commodities'], 'participation_notes' => $details['participation_notes'] ?? null],
                'status' => 'pending',
                'row_version' => 0,
            ]);
            $this->history($application, $current, 'submitted', null, 'pending');

            return $application->refresh();
        });
    }

    public function resubmit(User $actor, int $id, array $input): KadiwaApplication
    {
        $this->ready();
        $producer = $this->policy->participant($actor);
        $application = KadiwaApplication::whereKey($id)->firstOrFail();
        abort_unless((int) $application->producer_id === (int) $producer->getKey(), 403);

        return PreparedWorkflowTransaction::run(function () use ($actor, $producer, $application, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'producer' => $producer->getKey(),
                'participant' => $application->getKey(), 'event' => (int) $application->event_id]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $lockedProducer = Producer::whereKey($producer->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(app(\App\Policies\MaoIdentityPolicy::class)->producer($current, $lockedProducer), 403);
            $lockedApplication = KadiwaApplication::whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            $lockedEvent = KadiwaEvent::whereKey($lockedApplication->event_id)->lockForUpdate()->firstOrFail();

            $this->assertEligible($lockedProducer, $lockedEvent);
            $draft = $this->drafts->kadiwaResubmission($current, $lockedProducer,
                $lockedEvent->only(['id', 'status', 'categories', 'opens_at', 'closes_at']),
                $lockedApplication->only(['id', 'event_id', 'producer_id', 'status', 'row_version']), $input);
            $details = $draft['submitted_details'];

            $previous = (string) $lockedApplication->status;
            $this->persist($lockedApplication, [
                'details' => ['commodities' => $details['commodities'], 'participation_notes' => $details['participation_notes'] ?? null],
                'submission_hash' => $this->hashDetails($details),
                'status' => 'pending',
                'row_version' => (int) $details['version'] + 1,
            ]);
            $this->history($lockedApplication, $current, 'resubmitted', $previous, 'pending');

            return $lockedApplication->refresh();
        });
    }

    /**
     * One review entry point for both directions:
     *  - an assigned coordinator or manager records approve / reject / request_information
     *  - the producer records their own withdrawal
     * A coordinator can never withdraw on a producer's behalf, and a producer can never approve.
     */
    public function review(User $actor, int $id, array $input): KadiwaApplication
    {
        $this->ready();
        $application = KadiwaApplication::with('event')->findOrFail($id);
        $isReviewer = $this->policy->reviews($actor, $application);

        if (($input['decision'] ?? null) === 'withdraw') {
            // Only the owning producer may withdraw, so the producer context is resolved here.
            $producer = $this->policy->participant($actor);
            abort_unless((int) $application->producer_id === (int) $producer->getKey(), 403);

            return $this->withdraw($actor, $producer, $application->getKey(), $input);
        }

        return $this->decide($actor, $application->getKey(), $input, $isReviewer);
    }

    private function decide(User $actor, int $id, array $input, bool $isReviewer): KadiwaApplication
    {
        abort_unless($isReviewer, 403, 'Participation decisions require an assigned KADIWA reviewer.');
        Validator::make($input, ['operation_key' => 'required|uuid'])->validate();

        return PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'participant' => $id,
                'event_children' => true]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $application = KadiwaApplication::whereKey($id)->lockForUpdate()->firstOrFail();
            $event = KadiwaEvent::whereKey($application->event_id)->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->reviews($current, $application), 403, 'Assignment changed; reload.');
            $producer = Producer::whereKey($application->producer_id)->lockForUpdate()->firstOrFail();
            $applicant = User::whereKey($producer->user_id)->lockForUpdate()->firstOrFail();
            // Reviewing your own application is never allowed, whatever the assignment is.
            abort_if((int) $applicant->getKey() === (int) $current->getKey(), 403, 'Self-review is not allowed.');

            $draft = $this->drafts->kadiwaReview($current, $event->toArray(),
                $application->only(['id', 'event_id', 'status', 'row_version']), $input);
            $details = $draft['submitted_details'];

            $replay = KadiwaParticipationReview::where('operation_key', $details['operation_key'])->lockForUpdate()->first();
            if ($replay) {
                abort_unless((int) $replay->application_id === $id, 409, 'That operation key was already used.');

                return $application;
            }

            if ($details['decision'] === 'approve') $this->assertCapacity($event);

            $previous = (string) $application->status;
            // Only the fields that exist on the application row are written; the prepared
            // draft also carries reviewer bookkeeping that belongs to the review ledger.
            $this->persist($application, [
                'status' => $draft['proposed_attributes']['status'],
                'row_version' => $draft['proposed_attributes']['row_version'],
            ]);
            $this->persist(new KadiwaParticipationReview(), [
                'application_id' => $application->getKey(),
                'reviewer_id' => $current->getKey(),
                'decision' => $details['decision'],
                'reason' => $details['reason'],
                'version' => (int) $details['version'] + 1,
                'operation_key' => $details['operation_key'],
            ]);
            $this->history($application, $current, $details['decision'], $previous,
                $draft['proposed_attributes']['status']);

            return $application->refresh();
        });
    }

    private function withdraw(User $actor, Producer $producer, int $id, array $input): KadiwaApplication
    {
        Validator::make($input, ['operation_key' => 'required|uuid'])->validate();

        return PreparedWorkflowTransaction::run(function () use ($actor, $producer, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'participant' => $id]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $lockedProducer = Producer::whereKey($producer->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(app(\App\Policies\MaoIdentityPolicy::class)->producer($current, $lockedProducer), 403);
            $application = KadiwaApplication::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $application->producer_id === (int) $lockedProducer->getKey(), 403);

            $draft = $this->drafts->kadiwaWithdrawal($current, $lockedProducer,
                $application->only(['id', 'event_id', 'producer_id', 'status', 'row_version']), $input);
            $details = $draft['submitted_details'];

            $replay = KadiwaParticipationReview::where('operation_key', $details['operation_key'])->lockForUpdate()->first();
            if ($replay) {
                abort_unless((int) $replay->application_id === $id, 409, 'That operation key was already used.');

                return $application;
            }

            $previous = (string) $application->status;
            $this->persist($application, [
                'status' => $draft['proposed_attributes']['status'],
                'row_version' => $draft['proposed_attributes']['row_version'],
            ]);
            // A producer withdrawal is recorded in the same append-only review ledger.
            $this->persist(new KadiwaParticipationReview(), [
                'application_id' => $application->getKey(),
                'reviewer_id' => $current->getKey(),
                'decision' => 'withdraw',
                'reason' => $details['reason'],
                'version' => (int) $details['version'] + 1,
                'operation_key' => $details['operation_key'],
            ]);
            $this->history($application, $current, 'withdraw', $previous, 'withdrawn');

            return $application->refresh();
        });
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Participation prerequisites. The RSBSA registry match is only demanded when the
     * published event asks for it; private evidence upload is not integrated yet, so an
     * event that requires it stays closed to applications rather than accepting partial data.
     */
    private function assertEligible(Producer $producer, KadiwaEvent $event): void
    {
        abort_unless($event->status !== 'draft', 422, 'Select a published event.');
        $this->policy->meetsEventEligibility($producer, $event);
        abort_if($event->evidence_required, 503,
            'This event requires private participation evidence that is not integrated yet.');
    }

    /** Checked inside the transaction, against the locked event, immediately before approving. */
    private function assertCapacity(KadiwaEvent $event): void
    {
        if ($event->capacity === null) return;
        $approved = KadiwaApplication::where('event_id', $event->getKey())
            ->where('status', 'approved')->count();
        abort_if($approved >= (int) $event->capacity, 409,
            'This event is at its published capacity. No slot is reserved by this request.');
    }

    private function hashDetails(array $details): string
    {
        $payload = [
            'commodities' => array_map(static fn (array $c): array => [
                'commodity' => trim((string) $c['commodity']),
                'category' => trim((string) $c['category']),
                'quantity' => (string) $c['quantity'],
                'unit' => trim((string) $c['unit']),
            ], (array) ($details['commodities'] ?? [])),
            'participation_notes' => ($details['participation_notes'] ?? '') === ''
                ? null : (string) $details['participation_notes'],
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function history(KadiwaApplication $application, User $actor, string $action,
        ?string $from, string $to): void
    {
        $this->persist(new KadiwaApplicationStatusEvent(), [
            'application_id' => $application->getKey(),
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'previous_status' => $from,
            'new_status' => $to,
        ]);
    }

    private function persist(Model $record, array $attributes): void
    {
        abort_unless($record->forceFill($attributes)->save(), 503, 'KADIWA record or audit could not be persisted.');
    }

    public function presentApplication(KadiwaApplication $application): array
    {
        return [
            'id' => (int) $application->getKey(),
            'event_id' => (int) $application->event_id,
            'status' => (string) $application->status,
            'row_version' => (int) $application->row_version,
            'created_at' => WorkflowTimestamp::format($application->created_at),
        ];
    }
}