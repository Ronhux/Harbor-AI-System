<?php

namespace App\Policies;

use App\Models\KadiwaApplication;
use App\Models\KadiwaEvent;
use App\Models\KadiwaEventCoordinator;
use App\Models\Producer;
use App\Models\RegisteredProducer;
use App\Policies\KadiwaAuthorization as KadiwaAccess;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * KADIWA authorization.
 *
 * Three separated authorities, none inferred from a role alone:
 *  - manager          : explicit kadiwa_manager_user_ids assignment (may create/publish/close events)
 *  - coordinator      : explicit kadiwa_event_coordinators row for that one event (may read its queue only)
 *  - inquiry reviewer : explicit kadiwa_inquiry_reviewer_user_ids assignment
 *
 * A KADIWA approval permits a producer to sell at a venue. It is not a purchase, award,
 * procurement decision, payment or reservation of any kind, and grants no procurement authority.
 */
final class KadiwaWorkflowPolicy
{
    private const REQUIRED_TABLES = [
        'kadiwa_events', 'kadiwa_applications', 'kadiwa_inquiries',
        'kadiwa_event_coordinators', 'kadiwa_participation_reviews',
        'kadiwa_application_status_events', 'kadiwa_event_audits',
    ];

    public function authorization(): KadiwaAccess
    {
        return app(KadiwaAccess::class);
    }

    public function enabled(): void
    {
        $this->authorization()->enabled();
    }

    /** Schema presence is a service dependency, never a grant. */
    public function ready(): void
    {
        foreach (self::REQUIRED_TABLES as $table) {
            abort_unless(Schema::hasTable($table), 503, 'KADIWA dependencies are not available.');
        }
        abort_unless(Schema::hasColumns('kadiwa_applications',
            ['event_id', 'producer_id', 'submission_key', 'submission_hash', 'details', 'status', 'row_version']),
            503, 'KADIWA application schema is incomplete.');
    }

    public function manager(User $actor): bool
    {
        return $this->authorization()->manager($actor);
    }

    public function inquiryReviewer(User $actor): bool
    {
        return $this->authorization()->inquiryReviewer($actor);
    }

    public function administrator(User $actor): bool
    {
        return $this->manager($actor) || $this->inquiryReviewer($actor);
    }

    /**
     * Event-scoped management authority. A coordinator reaches only the events they are
     * explicitly assigned to and may not create, publish or close events.
     */
    public function manages(User $actor, KadiwaEvent $event): bool
    {
        return $this->manager($actor) || $this->assignedTo($actor, $event);
    }

    public function assignedTo(User $actor, KadiwaEvent $event): bool
    {
        return $this->authorization()->assignedTo($actor, $event);
    }

    /** Coordinator review of participation is limited to assigned events; managers reach all. */
    public function reviews(User $actor, KadiwaApplication $application): bool
    {
        $event = $application->event;
        if (!$event) return $this->manager($actor);

        return $this->manages($actor, $event);
    }

    /**
     * Participation is limited to an approved producer account under the current release
     * scope: an active Farmer account that owns an Admin-approved producer record.
     * Unregistered-farmer KADIWA-only accounts do not exist and are not created here.
     */
    public function participant(User $actor): Producer
    {
        $producer = Producer::where('user_id', $actor->getKey())->first();
        abort_unless($producer && app(MaoIdentityPolicy::class)->producer($actor, $producer), 403,
            'An approved producer account is required to participate.');

        return $producer;
    }

    /**
     * RSBSA registry match against the read-only registered_producers registry.
     * There is no RSBSA verification-status column on this installation, so a match means
     * "a registry record with this RSBSA number exists" - never a verification decision.
     */
    public function rsbsaMatched(Producer $producer): bool
    {
        $number = trim((string) $producer->rsbsa_number);

        return $number !== ''
            && RegisteredProducer::where('rsbsa_number', $number)->exists();
    }

    /** Participation requires the registry match only when the published event asks for it. */
    public function meetsEventEligibility(Producer $producer, KadiwaEvent $event): void
    {
        abort_if($event->rsbsa_required && !$this->rsbsaMatched($producer), 422,
            'This event requires a producer whose RSBSA registry record can be matched.');
    }

    public function viewsEvent(User $actor, KadiwaEvent $event): bool
    {
        // Only published events are participation opportunities. Drafts stay private to staff.
        if ($event->status !== 'draft') return true;

        return $this->manages($actor, $event);
    }

    public function viewsApplication(User $actor, KadiwaApplication $application): bool
    {
        $producer = $application->producer;
        $owner = $producer
            && $actor->is_active === true
            && (int) $producer->user_id === (int) $actor->getKey()
            && app(MaoIdentityPolicy::class)->producer($actor, $producer);

        return $owner || $this->reviews($actor, $application);
    }
}