<?php

namespace App\Services\Mao;

use App\Models\KadiwaApplication;
use App\Models\KadiwaEvent;
use App\Models\KadiwaEventAudit;
use App\Models\KadiwaEventCoordinator;
use App\Models\User;
use App\Policies\KadiwaWorkflowPolicy;
use App\Policies\KadiwaAuthorization as KadiwaAccess;
use App\Support\WorkflowTimestamp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * KADIWA event management for explicitly authorized personnel.
 *
 * Creates drafts, edits drafts, publishes, closes registration, reschedules and records
 * coordinator assignments. Every significant action appends a kadiwa_event_audits row.
 *
 * This service never approves participation, never sells anything and never touches a
 * producer, buyer, price, payment or procurement record.
 */
final class KadiwaEventService
{
    public function __construct(
        private KadiwaWorkflowPolicy $policy,
        private WorkflowDraftPreparation $drafts,
    ) {}

    public function ready(): void
    {
        $this->policy->ready();
    }

    /**
     * Explicit authority only. An administrator role on its own returns false everywhere.
     */
    public function capabilities(User $actor): array
    {
        $this->ready();
        $manager = $this->policy->manager($actor);
        $coordinator = $this->isCoordinator($actor);
        $assigned = $manager
            ? KadiwaEvent::orderBy('starts_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->assignedEventIds($actor);

        return [
            'manager' => $manager,
            'coordinator' => $coordinator,
            'inquiry_reviewer' => $this->policy->inquiryReviewer($actor),
            'assigned_event_ids' => $assigned,
        ];
    }

    /**
     * Participation opportunities. Only published, non-draft events are returned to producers;
     * the event list never exposes approved counts, remaining slots or applicant identities.
     */
    public function published(User $actor, bool $upcomingOnly = false): array
    {
        $this->ready();
        $query = KadiwaEvent::where('status', '!=', 'draft')
            ->orderBy('starts_at')->orderBy('id');
        if ($upcomingOnly) $query->where('ends_at', '>=', now());

        return $this->present($query->limit(100)->get());
    }

    public function show(User $actor, int $id): KadiwaEvent
    {
        $this->ready();
        $event = KadiwaEvent::findOrFail($id);
        abort_unless($this->policy->viewsEvent($actor, $event), 403);

        return $event;
    }

    public function assigned(User $actor): array
    {
        $this->ready();
        abort_unless($this->policy->administrator($actor), 403);

        $query = KadiwaEvent::query();
        if (!$this->policy->manager($actor)) {
            $ids = $this->assignedEventIds($actor);
            abort_if(!$ids, 403, 'No KADIWA event assignment.');
            $query->whereIn('id', $ids);
        }

        return $this->present($query->orderBy('starts_at')->orderBy('id')->limit(100)->get());
    }

    public function store(User $actor, array $input): KadiwaEvent
    {
        $this->ready();
        abort_unless($this->policy->manager($actor), 403, 'KADIWA manager assignment is required.');
        $draft = $this->drafts->kadiwaEvent($input);
        $details = $draft['submitted_details'];

        return PreparedWorkflowTransaction::run(function () use ($actor, $details, $draft) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'kadiwa' => true]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->manager($current), 403);

            $event = new KadiwaEvent();
            $this->persist($event, [
                'title' => $details['title'],
                'description' => $details['description'],
                'venue' => $details['venue'],
                'location' => $details['location'],
                'starts_at' => $details['starts_at'],
                'ends_at' => $details['ends_at'],
                'opens_at' => $details['opens_at'],
                'closes_at' => $details['closes_at'],
                'capacity' => $details['capacity'] ?? null,
                'categories' => $details['categories'],
                'requirements' => $details['requirements'],
                'rsbsa_required' => (bool) $details['rsbsa_required'],
                'evidence_required' => (bool) $details['evidence_required'],
                'status' => 'draft',
                'row_version' => 0,
            ]);
            $this->audit($actor, $event, 'created', null, 'draft', null, $details['operation_key']);

            return $event->refresh();
        });
    }

    public function update(User $actor, int $id, array $input): KadiwaEvent
    {
        $this->ready();
        abort_unless($this->policy->manager($actor), 403, 'KADIWA manager assignment is required.');

        return PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'event' => $id]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->manager($current), 403);
            $event = KadiwaEvent::whereKey($id)->lockForUpdate()->firstOrFail();
            $draft = $this->drafts->kadiwaEventUpdate($event->only(
                ['id', 'status', 'row_version', 'title', 'description', 'capacity', 'categories',
                    'requirements', 'rsbsa_required', 'evidence_required']
            ), $input);
            $details = $draft['submitted_details'];

            $this->persist($event, [
                'title' => $details['title'],
                'description' => $details['description'],
                'capacity' => $details['capacity'] ?? null,
                'categories' => $details['categories'],
                'requirements' => $details['requirements'],
                'rsbsa_required' => (bool) $details['rsbsa_required'],
                'evidence_required' => (bool) $details['evidence_required'],
                'row_version' => $draft['proposed_attributes']['row_version'],
            ]);
            $this->audit($actor, $event, 'updated', $event->status, $event->status, null,
                $this->derivedKey($event->getKey(), 'updated', (int) $details['version']));

            return $event->refresh();
        });
    }

    public function transition(User $actor, int $id, array $input): KadiwaEvent
    {
        $this->ready();
        abort_unless($this->policy->manager($actor), 403, 'Only a KADIWA manager may change event status.');
        Validator::make($input, [
            'status' => 'required|string|max:32', 'version' => 'required|integer|min:0',
            'operation_key' => 'required|uuid',
            'row_version' => 'prohibited',
        ])->validate();

        return PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'event' => $id, 'event_children' => true]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->manager($current), 403);
            $event = KadiwaEvent::whereKey($id)->lockForUpdate()->firstOrFail();
            $previous = $event->status;
            $draft = $this->drafts->kadiwaEventTransition($event->toArray(), $input['status'], (int) $input['version'], $input['operation_key']);

            // A repeated identical operation returns the current record instead of a conflict.
            $duplicate = $this->replay($input['operation_key'], $event->getKey());
            if ($duplicate !== null) return $event->refresh();

            $this->persist($event, $draft['proposed_attributes']);
            // Cancelling a published event cancels its live applications, with a recorded reason.
            if ($draft['proposed_attributes']['status'] === 'cancelled') {
                $this->cancelApplications($actor, $event);
            }
            $this->audit($actor, $event, $this->transitionAction($draft['proposed_attributes']['status']),
                $previous, $draft['proposed_attributes']['status'], null, $input['operation_key']);

            return $event->refresh();
        });
    }

    public function reschedule(User $actor, int $id, array $input): KadiwaEvent
    {
        $this->ready();
        abort_unless($this->policy->manager($actor), 403, 'Only a KADIWA manager may reschedule an event.');
        Validator::make($input, ['operation_key' => 'required|uuid'])->validate();

        return PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'event' => $id, 'event_children' => true]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->manager($current), 403);
            $event = KadiwaEvent::whereKey($id)->lockForUpdate()->firstOrFail();
            $draft = $this->drafts->kadiwaReschedule($event->toArray(), $input);
            $details = $draft['submitted_details'];

            $this->persist($event, [
                'venue' => $details['venue'],
                'location' => $details['location'],
                'starts_at' => $details['starts_at'],
                'ends_at' => $details['ends_at'],
                'opens_at' => $details['opens_at'],
                'closes_at' => $details['closes_at'],
                'status' => 'draft',
                'row_version' => $draft['proposed_attributes']['row_version'],
            ]);
            $this->audit($actor, $event, 'rescheduled', $event->status, 'draft', $details['reason'], $input['operation_key']);

            return $event->refresh();
        });
    }

    /**
     * Coordinator authority is recorded, never granted. The account must already exist, be
     * active and be an administrator; this call cannot create or promote an account.
     */
    public function assignCoordinator(User $actor, int $id, array $input): void
    {
        $this->ready();
        abort_unless($this->policy->manager($actor), 403, 'Only a KADIWA manager may assign coordinators.');
        Validator::make($input, [
            'user_id' => 'required|integer|min:1', 'active' => 'required|boolean',
            'operation_key' => 'nullable|uuid',
        ])->validate();

        PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor' => $actor->getKey(), 'event' => $id, 'users' => [(int) $input['user_id']]]);
            $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->manager($current), 403);
            $event = KadiwaEvent::whereKey($id)->lockForUpdate()->firstOrFail();
            $coordinator = User::whereKey($input['user_id'])->lockForUpdate()->firstOrFail();
            abort_unless($coordinator->is_active === true
                && in_array($coordinator->user_type, ['Admin', 'Super Admin', KadiwaAccess::ROLE], true), 422,
                'A coordinator must be an active administrator or KADIWA Manager account.');
            abort_if((int) $coordinator->getKey() === (int) $actor->getKey()
                && !$this->policy->inquiryReviewer($current), 422, 'Assign another authorized account.');

            $assignment = KadiwaEventCoordinator::where('event_id', $event->getKey())
                ->where('user_id', $coordinator->getKey())->lockForUpdate()->first();
            if (!$assignment) $assignment = new KadiwaEventCoordinator();
            $this->persist($assignment, [
                'event_id' => $event->getKey(),
                'user_id' => $coordinator->getKey(),
                'active' => (bool) $input['active'],
            ]);
            $this->audit($actor, $event, 'coordinator_assigned', $event->status, $event->status, null,
                $input['operation_key'] ?? $this->derivedKey($event->getKey(), 'coordinator_assigned', (int) $event->row_version));
        });
    }

    /**
     * Participation queue for an assigned coordinator or a manager.
     * Application rows carry no producer identity: a reviewer acts on the commodity
     * details and a documented reason, not on a named person they cannot verify.
     */
    public function queue(User $actor, int $id): array
    {
        $this->ready();
        $event = KadiwaEvent::findOrFail($id);
        abort_unless($this->policy->manages($actor, $event), 403);

        $approved = KadiwaApplication::where('event_id', $event->getKey())
            ->where('status', 'approved')->count();
        $capacity = $event->capacity;
        $pageSize = (int) config('kadiwa.limits.queue_page', 100);

        $applications = KadiwaApplication::where('event_id', $event->getKey())
            ->orderBy('id')->limit($pageSize)->get()
            ->map(fn (KadiwaApplication $a) => $this->presentApplication($a))->all();

        return [
            'event' => $this->presentEvent($event),
            'approved_count' => $approved,
            'available_slots' => $capacity === null ? null : max(0, (int) $capacity - $approved),
            'applications' => $applications,
        ];
    }

    public function audits(User $actor, int $id): array
    {
        $this->ready();
        $event = KadiwaEvent::findOrFail($id);
        abort_unless($this->policy->manages($actor, $event), 403);

        return KadiwaEventAudit::where('event_id', $event->getKey())
            ->orderBy('id')->limit(200)->get()
            ->map(fn (KadiwaEventAudit $a) => [
                'action' => $a->action,
                'previous_status' => $a->previous_status,
                'new_status' => $a->new_status,
                'reason' => $a->reason,
                'created_at' => WorkflowTimestamp::format($a->created_at),
            ])->all();
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private function cancelApplications(User $actor, KadiwaEvent $event): void
    {
        $live = KadiwaApplication::where('event_id', $event->getKey())
            ->whereIn('status', ['pending', 'approved', 'needs_information'])
            ->orderBy('id')->lockForUpdate()->get();

        foreach ($live as $application) {
            $previous = $application->status;
            $this->persist($application, ['status' => 'cancelled', 'row_version' => $application->row_version + 1]);
            $this->persist(new \App\Models\KadiwaApplicationStatusEvent(), [
                'application_id' => $application->getKey(),
                'actor_user_id' => $actor->getKey(),
                'action' => 'event_cancelled',
                'previous_status' => $previous,
                'new_status' => 'cancelled',
            ]);
        }
    }

    private function transitionAction(string $target): string
    {
        return match ($target) {
            'open' => 'published',
            'closed' => 'registration_closed',
            'ongoing' => 'marked_ongoing',
            'completed' => 'marked_completed',
            'cancelled' => 'cancelled',
            default => 'status_changed',
        };
    }

    /**
     * Deterministic audit key for operations the client does not supply one for.
     * Retrying the same edit therefore records one audit row, not a new one.
     */
    private function derivedKey(int $eventId, string $action, int $version): string
    {
        $h = hash('sha256', $eventId . '|' . $action . '|' . $version);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
            . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
    }

    /** An operation_key already recorded for this event makes the call a no-op, not a conflict. */
    private function replay(string $operationKey, int $eventId): ?KadiwaEventAudit
    {
        return KadiwaEventAudit::where('operation_key', $operationKey)->where('event_id', $eventId)->first();
    }

    private function audit(User $actor, KadiwaEvent $event, string $action, ?string $from, ?string $to,
        ?string $reason, string $operationKey): void
    {
        $this->persist(new KadiwaEventAudit(), [
            'event_id' => $event->getKey(),
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'previous_status' => $from,
            'new_status' => $to,
            'reason' => $reason,
            'version' => (int) $event->row_version,
            'operation_key' => $operationKey,
        ]);
    }

    private function persist(Model $record, array $attributes): void
    {
        abort_unless($record->forceFill($attributes)->save(), 503, 'KADIWA record or audit could not be persisted.');
    }

    private function isCoordinator(User $actor): bool
    {
        return app(KadiwaAccess::class)->isCoordinator($actor);
    }

    private function assignedEventIds(User $actor): array
    {
        return app(KadiwaAccess::class)->assignedEventIds($actor);
    }

    public function present(iterable $events): array
    {
        $result = [];
        foreach ($events as $event) $result[] = $this->presentEvent($event);

        return $result;
    }

    private function presentEvent(KadiwaEvent $event): array
    {
        return [
            'id' => (int) $event->getKey(),
            'title' => (string) $event->title,
            'description' => (string) $event->description,
            'venue' => (string) $event->venue,
            'location' => (string) $event->location,
            'starts_at' => WorkflowTimestamp::format($event->starts_at),
            'ends_at' => WorkflowTimestamp::format($event->ends_at),
            'opens_at' => WorkflowTimestamp::format($event->opens_at),
            'closes_at' => WorkflowTimestamp::format($event->closes_at),
            'capacity' => $event->capacity === null ? null : (int) $event->capacity,
            'categories' => array_values((array) ($event->categories ?? [])),
            'requirements' => array_values((array) ($event->requirements ?? [])),
            'status' => (string) $event->status,
            'row_version' => (int) $event->row_version,
            'evidence_required' => (bool) $event->evidence_required,
            'rsbsa_required' => (bool) $event->rsbsa_required,
        ];
    }

    private function presentApplication(KadiwaApplication $application): array
    {
        return [
            'id' => (int) $application->getKey(),
            'event_id' => (int) $application->event_id,
            'status' => (string) $application->status,
            'row_version' => (int) $application->row_version,
            'created_at' => WorkflowTimestamp::format($application->created_at),
        ];
    }

    /** Wraps a database failure without leaking a driver message to the client. */
    public function guard(callable $operation, string $context)
    {
        try {
            return $operation();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            abort(409, 'That KADIWA operation was already recorded. Reload the current state.');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('KADIWA database operation stopped.', ['context' => $context]);

            abort(503, 'The KADIWA operation could not complete. No automatic retry.');
        }
    }
}