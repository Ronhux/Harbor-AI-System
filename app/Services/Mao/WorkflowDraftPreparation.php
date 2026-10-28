<?php

namespace App\Services\Mao;

use App\Models\Buyer;
use App\Models\FcaRegistry;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Validates proposed submissions only: no persistence, permissions, or routes. */
final class WorkflowDraftPreparation
{
    private const SERVER_FIELDS = [
        'status', 'reviewed_by', 'reviewed_at', 'authorized', 'legacy_trade_access',
        'account_review_status', 'rsbsa_verification_status', 'procurement_eligibility_status',
        'payment_status', 'award_status', 'buyer_id', 'producer_id', 'user_id',
    ];

    public const KADIWA_EVENT_STATUSES = ['draft', 'open', 'closed', 'ongoing', 'completed', 'cancelled'];

    /** The only status changes an operator may perform. A client cannot invent a transition. */
    public const KADIWA_TRANSITIONS = [
        'draft' => ['open', 'cancelled'],
        'open' => ['closed', 'cancelled'],
        'closed' => ['ongoing', 'cancelled'],
        'ongoing' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const KADIWA_DECISIONS = ['approve', 'reject', 'request_information'];

    public const KADIWA_DECISION_STATUS = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'request_information' => 'needs_information',
        'withdraw' => 'withdrawn',
    ];

    public function fcaApplication(User $actor, Producer $producer, FcaRegistry $fca, array $input): array
    {
        $this->assertProducerOwner($actor, $producer);
        abort_unless($fca->getKey() && (int) $fca->getKey() > 0, 422, 'Select a directory record for review.');
        $data = $this->validate($input, [
            'representative_position' => 'required|string|max:120',
            'authorization_statement' => 'required|string|max:2000',
            // Opaque receipt from a future protected upload service, not a public URL/path.
            // Null when this is a preliminary intake without an identity document.
            'evidence_receipt' => ['nullable', 'string', 'regex:/\A[a-zA-Z0-9_-]{16,100}\z/'],
            'fca_id' => 'prohibited', 'president_name' => 'prohibited',
        ]);

        return $this->draft($data, [
            'fca_id' => (int) $fca->getKey(), 'producer_id' => (int) $producer->getKey(),
            'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null,
        ], [
            'protected_upload_receipt_ownership', 'directory_record_validation',
            'representative_authorization_review', 'separate_procurement_eligibility_review',
        ]);
    }

    /** All identity/application context must be resolved server-side before use. */
    public function fcaReview(User $reviewer, User $applicant, Producer $producer, FcaRegistry $fca, array $application, array $input): array
    {
        $this->assertAdministrator($reviewer);
        abort_unless((int) ($application['id'] ?? 0) > 0 && (int) $fca->getKey() > 0
            && (int) $producer->getKey() > 0 && (int) $applicant->getKey() > 0
            && (int) $producer->user_id === (int) $applicant->getKey()
            && (int) ($application['producer_id'] ?? 0) === (int) $producer->getKey()
            && (int) ($application['fca_id'] ?? 0) === (int) $fca->getKey(), 403);
        $data = $this->validate($input, [
            'decision' => ['required', Rule::in(['authorize', 'reject', 'revoke'])],
            'review_reason' => 'required|string|max:2000',
            'evidence_receipt' => ['required_if:decision,authorize', 'nullable', 'string', 'regex:/\A[a-zA-Z0-9_-]{16,100}\z/'],
            'expires_at' => 'nullable|date|after:now',
        ]);
        if ($data['decision'] === 'authorize') $this->assertProducerOwner($applicant, $producer);
        $current = $application['status'] ?? null;
        abort_unless(($data['decision'] === 'revoke' && $current === 'authorized')
            || (in_array($data['decision'], ['authorize', 'reject'], true) && $current === 'pending'), 409);

        return $this->draft($data, [
            'application_id' => (int) $application['id'], 'reviewed_by' => (int) $reviewer->getKey(),
            'status' => match ($data['decision']) { 'authorize' => 'authorized', 'reject' => 'rejected', 'revoke' => 'revoked' },
        ], ['protected_upload_receipt_ownership', 'current_application_lock', 'separate_procurement_eligibility_review']);
    }

    /**
     * Creating an event record is not scheduling a real market. The record is always
     * persisted as a draft and must be separately reviewed and published before any
     * producer can see it or apply.
     */
    public function kadiwaEvent(array $input): array
    {
        $data = $this->validate($input, [
            'title' => 'required|string|max:160',
            'description' => 'required|string|max:4000',
            'venue' => 'required|string|max:160',
            'location' => 'required|string|max:200',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'opens_at' => 'required|date|before_or_equal:closes_at',
            'closes_at' => 'required|date|before_or_equal:starts_at',
            'capacity' => 'nullable|integer|min:1|max:' . (int) config('kadiwa.limits.capacity_max', 10000),
            'categories' => 'required|array|min:1|max:' . (int) config('kadiwa.limits.categories', 30),
            'categories.*' => 'required|string|max:80',
            'requirements' => 'nullable|array|max:' . (int) config('kadiwa.limits.requirements', 30),
            'requirements.*' => 'required|string|max:500',
            'rsbsa_required' => 'required|boolean',
            'evidence_required' => 'required|boolean',
            'operation_key' => 'required|uuid',
            'row_version' => 'prohibited',
        ]);

        $data['categories'] = $this->lineList($data['categories'] ?? [], 80);
        $data['requirements'] = $this->lineList($data['requirements'] ?? [], 500);

        return $this->draft($data, ['status' => 'draft', 'row_version' => 0], [
            'administrator_publication_review', 'explicit_coordinator_assignments',
        ]);
    }

    /** Draft edits may never move the schedule; a schedule change is a separate reviewed action. */
    public function kadiwaEventUpdate(array $event, array $input): array
    {
        $data = $this->validate($input, [
            'title' => 'required|string|max:160',
            'description' => 'required|string|max:4000',
            'capacity' => 'nullable|integer|min:1|max:' . (int) config('kadiwa.limits.capacity_max', 10000),
            'categories' => 'required|array|min:1|max:' . (int) config('kadiwa.limits.categories', 30),
            'categories.*' => 'required|string|max:80',
            'requirements' => 'nullable|array|max:' . (int) config('kadiwa.limits.requirements', 30),
            'requirements.*' => 'required|string|max:500',
            'rsbsa_required' => 'required|boolean',
            'evidence_required' => 'required|boolean',
            'version' => 'required|integer|min:0',
            'operation_key' => 'prohibited',
            // A reschedule is its own reviewed operation with its own reason.
            'starts_at' => 'prohibited', 'ends_at' => 'prohibited',
            'opens_at' => 'prohibited', 'closes_at' => 'prohibited',
            'venue' => 'prohibited', 'location' => 'prohibited',
        ]);

        $data['categories'] = $this->lineList($data['categories'], 80);
        $data['requirements'] = $this->lineList($data['requirements'] ?? [], 500);
        $this->assertEditableDraft($event, $data['version']);

        return $this->draft($data, ['row_version' => (int) $event['row_version'] + 1],
            ['administrator_publication_review']);
    }

    /**
     * Publishing, closing, completing and cancelling are separate operator decisions.
     * The permitted next statuses are fixed; a client cannot invent a transition.
     */
    public function kadiwaEventTransition(array $event, string $target, int $version, string $operationKey): array
    {
        $current = (string) ($event['status'] ?? '');
        abort_unless(in_array($current, self::KADIWA_EVENT_STATUSES, true), 409, 'Event status is not recognised.');
        abort_unless((int) ($event['row_version'] ?? -1) === $version, 409, 'Event changed; review its current state.');
        abort_unless(Validator::make(compact('target', 'operationKey'),
            ['target' => 'required|string', 'operationKey' => 'required|uuid'])->passes(), 422);
        abort_unless(in_array($target, self::KADIWA_TRANSITIONS[$current] ?? [], true), 422,
            'That status change is not permitted from the current state.');

        // Publishing requires a complete, internally consistent record.
        if ($target === 'open') {
            $this->assertPublishable($event);
        }

        return $this->draft(
            ['target' => $target, 'operation_key' => $operationKey],
            ['status' => $target, 'row_version' => $version + 1],
            ['administrator_publication_review']
        );
    }

    /**
     * A reschedule always returns the event to draft: producers who already applied must
     * re-read the published dates, so the record cannot stay published.
     */
    public function kadiwaReschedule(array $event, array $input): array
    {
        $data = $this->validate($input, [
            'venue' => 'required|string|max:160',
            'location' => 'required|string|max:200',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'opens_at' => 'required|date|before_or_equal:closes_at',
            'closes_at' => 'required|date|before_or_equal:starts_at',
            'reason' => 'required|string|max:' . (int) config('kadiwa.limits.reason_max', 1000),
            'version' => 'required|integer|min:0',
            'operation_key' => 'required|uuid',
            'status' => 'prohibited',
        ]);

        $current = (string) ($event['status'] ?? '');
        abort_unless(in_array($current, ['draft', 'open', 'closed'], true), 409,
            'Only an unpublished, open or closed event can be rescheduled.');
        abort_unless((int) ($event['row_version'] ?? -1) === (int) $data['version'], 409,
            'Event changed; review its current state.');

        return $this->draft($data, ['status' => 'draft', 'row_version' => (int) $data['version'] + 1],
            ['administrator_publication_review']);
    }

    /** The event must be loaded server-side; nothing about it is trusted from the request. */
    public function kadiwaApplication(User $actor, Producer $producer, array $event, array $input): array
    {
        $this->assertProducerOwner($actor, $producer);
        $this->assertOpenForApplications($event);
        $data = $this->kadiwaSubmission($input, $event, requireSubmissionKey: true);

        return $this->draft($data, [
            'event_id' => (int) $event['id'], 'producer_id' => (int) $producer->getKey(),
            'status' => 'pending', 'row_version' => 0,
        ], ['event_specific_eligibility', 'assigned_coordinator_review', 'transactional_capacity_check']);
    }

    /** Resubmission is only possible against a coordinator's documented information request. */
    public function kadiwaResubmission(User $actor, Producer $producer, array $event, array $application, array $input): array
    {
        $this->assertProducerOwner($actor, $producer);
        abort_unless((int) ($application['producer_id'] ?? 0) === (int) $producer->getKey(), 403);
        abort_unless((int) ($application['event_id'] ?? 0) === (int) $event['id'], 409,
            'Application identity changed.');
        abort_unless(($application['status'] ?? null) === 'needs_information', 409,
            'Only a requested-information application can be resubmitted.');
        $this->assertOpenForApplications($event);
        $data = $this->kadiwaSubmission($input, $event, requireSubmissionKey: false);
        $data['version'] = (int) $data['version'];
        abort_unless((int) $data['version'] === (int) ($application['row_version'] ?? -1), 409,
            'Application changed; review its current state.');

        return $this->draft($data, [
            'status' => 'pending', 'row_version' => (int) $data['version'] + 1,
        ], ['event_specific_eligibility', 'assigned_coordinator_review', 'transactional_capacity_check']);
    }

    /**
     * Reviewing a participation application. Preparing a decision reserves nothing and
     * approves nothing; capacity is checked again inside the transaction that persists it.
     */
    public function kadiwaReview(User $reviewer, array $event, array $application, array $input): array
    {
        // Use the existing KADIWA assignment policy here as the persisting service does.
        // The restricted manager role must never be added to the shared Admin/FCA helper.
        $record = \App\Models\KadiwaEvent::find((int) ($event['id'] ?? 0));
        abort_unless($record && app(\App\Policies\KadiwaWorkflowPolicy::class)->manages($reviewer, $record), 403);
        $data = $this->validate($input, [
            'decision' => ['required', Rule::in(self::KADIWA_DECISIONS)],
            'reason' => 'required|string|max:' . (int) config('kadiwa.limits.reason_max', 1000),
            'version' => 'required|integer|min:0',
            'operation_key' => 'required|uuid',
            'status' => 'prohibited', 'reviewed_by' => 'prohibited',
            'approved_count' => 'prohibited', 'capacity' => 'prohibited',
        ]);

        $current = (string) ($application['status'] ?? '');
        $from = match ($data['decision']) {
            'approve', 'reject', 'request_information' => 'pending',
            'withdraw' => $current,
        };
        abort_if($data['decision'] === 'withdraw', 403, 'Withdrawal is initiated by the producer.');
        abort_unless($from === 'pending', 409, 'That decision is not available for the current status.');
        abort_unless((int) ($event['id'] ?? 0) > 0 && (int) ($application['id'] ?? 0) > 0
            && (int) ($application['event_id'] ?? 0) === (int) $event['id'], 409,
            'Application identity changed.');
        abort_unless((int) ($application['row_version'] ?? -1) === (int) $data['version'], 409,
            'Application changed; review its current state.');
        // Approving is an event-level decision and requires the event to still be open.
        abort_if($data['decision'] === 'approve' && ($event['status'] ?? null) !== 'open', 409,
            'The event is not open for participation approval.');

        return $this->draft($data, [
            'application_id' => (int) $application['id'], 'reviewed_by' => (int) $reviewer->getKey(),
            'status' => self::KADIWA_DECISION_STATUS[$data['decision']],
            'row_version' => (int) $data['version'] + 1,
        ], ['current_application_lock', 'event_specific_eligibility', 'transactional_capacity_check']);
    }

    /** A producer may withdraw while the application can still change. */
    public function kadiwaWithdrawal(User $actor, Producer $producer, array $application, array $input): array
    {
        $this->assertProducerOwner($actor, $producer);
        $data = $this->validate($input, [
            'decision' => ['required', Rule::in(['withdraw'])],
            'reason' => 'required|string|max:' . (int) config('kadiwa.limits.reason_max', 1000),
            'version' => 'required|integer|min:0',
            'operation_key' => 'required|uuid',
            'status' => 'prohibited', 'reviewed_by' => 'prohibited',
        ]);
        abort_unless((int) ($application['producer_id'] ?? 0) === (int) $producer->getKey(), 403);
        abort_unless(in_array((string) ($application['status'] ?? ''),
            ['pending', 'approved', 'needs_information'], true), 409,
            'This application can no longer be withdrawn.');
        abort_unless((int) ($application['row_version'] ?? -1) === (int) $data['version'], 409,
            'Application changed; review its current state.');

        return $this->draft($data, [
            'application_id' => (int) $application['id'], 'reviewed_by' => (int) $actor->getKey(),
            'status' => 'withdrawn', 'row_version' => (int) $data['version'] + 1,
        ], ['current_application_lock']);
    }

    /** Shared commodity/notes payload for a first application and for a resubmission. */
    private function kadiwaSubmission(array $input, array $event, bool $requireSubmissionKey): array
    {
        $rules = [
            'commodities' => 'required|array|min:1|max:' . (int) config('kadiwa.limits.commodities', 20),
            'commodities.*' => 'required|array:commodity,category,quantity,unit',
            'commodities.*.commodity' => 'required|string|max:120',
            'commodities.*.category' => 'required|string|max:80',
            'commodities.*.quantity' => $this->quantityRules(),
            'commodities.*.unit' => ['required', 'string', 'max:30', 'regex:/\A[^\\r\\n\\x00-\\x1f]+\\z/'],
            'participation_notes' => 'nullable|string|max:' . (int) config('kadiwa.limits.notes_max', 1000),
            'version' => 'nullable|integer|min:0',
            'operation_key' => $requireSubmissionKey ? 'nullable|uuid' : 'required|uuid',
            'submission_key' => $requireSubmissionKey ? 'required|uuid' : 'prohibited',
            'event_id' => 'prohibited', 'producer_id' => 'prohibited', 'approved' => 'prohibited',
            'row_version' => 'prohibited',
        ];
        $data = $this->validate($input, $rules);

        $allowed = array_map('strval', (array) ($event['categories'] ?? []));
        $seen = [];
        foreach ($data['commodities'] as $index => $row) {
            // A category is never accepted because the client said so; it must be published on the event.
            abort_unless(in_array($row['category'], $allowed, true), 422,
                'Commodity ' . ($index + 1) . ' uses a category this event does not offer.');
            $key = mb_strtolower(trim($row['commodity'])) . '|' . mb_strtolower(trim($row['category']))
                . '|' . mb_strtolower(trim($row['unit']));
            abort_if(isset($seen[$key]), 422, 'Commodity ' . ($index + 1) . ' repeats an earlier line.');
            $seen[$key] = true;
        }

        return $data;
    }

    private function assertOpenForApplications(array $event): void
    {
        abort_unless(($event['status'] ?? null) === 'open', 422, 'This event is not open for applications.');
        $closes = $event['closes_at'] ?? null;
        $opens = $event['opens_at'] ?? null;
        abort_unless($closes && $opens, 422, 'This event has no published application window.');
        abort_if(\Carbon\Carbon::parse($opens)->isFuture(), 422, 'Applications for this event are not open yet.');
        abort_if(\Carbon\Carbon::parse($closes)->isPast(), 422, 'Applications for this event are closed.');
    }

    private function assertEditableDraft(array $event, int $version): void
    {
        abort_unless(($event['status'] ?? null) === 'draft', 409,
            'Only a draft event can be edited. Reschedule or close it first.');
        abort_unless((int) ($event['row_version'] ?? -1) === $version, 409,
            'Event changed; review its current state.');
    }

    private function assertPublishable(array $event): void
    {
        foreach (['title', 'description', 'venue', 'location'] as $field) {
            abort_unless(trim((string) ($event[$field] ?? '')) !== '', 422,
                'Complete the event ' . $field . ' before publishing.');
        }
        $categories = (array) ($event['categories'] ?? []);
        abort_if(!$categories, 422, 'Publish at least one permitted commodity category.');
        abort_if(!$this->assertScheduleOrdered($event), 422, 'Review the event schedule before publishing.');
    }

    private function assertScheduleOrdered(array $event): bool
    {
        $parse = fn ($value) => $value ? \Carbon\Carbon::parse($value) : null;
        $starts = $parse($event['starts_at'] ?? null);
        $ends = $parse($event['ends_at'] ?? null);
        $opens = $parse($event['opens_at'] ?? null);
        $closes = $parse($event['closes_at'] ?? null);
        if (!$starts || !$ends || !$opens || !$closes) return false;

        return $ends->greaterThan($starts) && $opens->lessThanOrEqualTo($closes)
            && $closes->lessThanOrEqualTo($starts);
    }

    private function lineList(array $values, int $max): array
    {
        $result = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') continue;
            abort_unless(mb_strlen($value) <= $max, 422, 'A list entry exceeds ' . $max . ' characters.');
            if (!in_array($value, $result, true)) $result[] = $value;
        }

        return $result;
    }

    public function demand(User $actor, Buyer $buyer, array $input): array
    {
        abort_unless($actor->user_type === 'Buyer' && $actor->is_active !== false
            && (int) $actor->getKey() > 0 && (int) $buyer->getKey() > 0
            && (int) $buyer->user_id === (int) $actor->getKey()
            && $buyer->verification_status === 'Verified', 403);

        $data = $this->validate($input, [
            'posting_type' => ['required', Rule::in(['ordinary', 'sagip_saka'])],
            'purpose' => 'required|string|max:2000',
            'items' => 'required|array|min:1|max:20',
            'items.*' => 'required|array:commodity,quantity,unit,specifications,max_price_per_unit',
            'items.*.commodity' => 'required|string|max:255',
            'items.*.quantity' => $this->quantityRules(),
            'items.*.unit' => 'required|string|max:50',
            'items.*.specifications' => 'required|string|max:2000',
            'items.*.max_price_per_unit' => ['nullable', 'string', 'regex:/\A\d{1,10}(?:\.\d{1,2})?\z/'],
            'offer_deadline' => 'required|date|after:now',
            'delivery_date' => 'required|date|after_or_equal:offer_deadline',
            'delivery_information' => 'required|string|max:2000',
            'eligibility_requirements' => 'required|string|max:4000',
            'partial_supply_allowed' => 'required|boolean',
            'budget_amount' => ['nullable', 'string', 'regex:/\A\d{1,12}(?:\.\d{1,2})?\z/'],
            'budget_currency' => ['required', Rule::in(['PHP'])],
            'procurement_notice_reference' => 'required_if:posting_type,sagip_saka|nullable|string|max:255',
        ]);

        return $this->draft($data, ['buyer_id' => (int) $buyer->getKey(), 'status' => 'draft'],
            $data['posting_type'] === 'sagip_saka'
                ? ['institutional_posting_authority', 'official_notice_review', 'notice_specific_supplier_eligibility', 'administrative_publication_review']
                : ['owner_publication_review']);
    }

    private function assertProducerOwner(User $actor, Producer $producer): void
    {
        abort_unless(in_array($actor->user_type, ['Farmer', 'Fisherfolk', 'Producer'], true)
            && $actor->is_active !== false && (int) $actor->getKey() > 0 && (int) $producer->getKey() > 0
            && (int) $producer->user_id === (int) $actor->getKey()
            && strtolower((string) $producer->verification_status) !== 'rejected', 403);
    }

    private function assertAdministrator(User $actor): void
    {
        abort_unless(in_array($actor->user_type, ['Admin', 'Super Admin'], true)
            && $actor->is_active !== false && (int) $actor->getKey() > 0, 403);
    }

    private function validate(array $input, array $rules): array
    {
        foreach (self::SERVER_FIELDS as $field) $rules[$field] = 'prohibited';

        return Validator::make($input, $rules)->validate();
    }

    private function quantityRules(): array
    {
        // Decimal strings preserve submitted precision; no floating-point allocation.
        return ['required', 'string', 'regex:/\A\d{1,9}(?:\.\d{1,3})?\z/', 'numeric', 'gt:0'];
    }

    private function draft(array $submitted, array $proposed, array $requiredReviews): array
    {
        return [
            'submitted_details' => $submitted, 'proposed_attributes' => $proposed,
            'required_reviews' => $requiredReviews, 'persisted' => false,
            'authorization_granted' => false, 'activation_ready' => false,
        ];
    }
}
