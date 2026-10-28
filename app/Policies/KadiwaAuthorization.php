<?php

namespace App\Policies;

use App\Models\KadiwaEvent;
use App\Models\KadiwaEventCoordinator;
use App\Models\User;

/**
 * KADIWA authorization, deliberately kept separate from MaoIdentityPolicy::administrator().
 *
 * WHY A SEPARATE POLICY
 * --------------------
 * MaoIdentityPolicy::administrator() accepts only 'Admin' and 'Super Admin' and is shared by
 * the FCA reviewer, demand reviewer and institutional-buyer assignments. Widening it to admit
 * a KADIWA role would silently hand that role authority over FCA representative decisions,
 * institutional demand approval and buyer verification. That is exactly the broadening this
 * policy exists to avoid.
 *
 * The KADIWA Manager role therefore:
 *   - is its own user_type, not Admin or Super Admin
 *   - is checked only here, only for kadiwa_manager_user_ids and
 *     kadiwa_inquiry_reviewer_user_ids
 *   - cannot satisfy fca_reviewer_user_ids, demand_reviewer_user_ids or
 *     institutional_buyer_user_ids
 *   - is not accepted by any EnsureUserType('Admin') route group
 *
 * Inquiry-review authority is a SEPARATE assignment. Holding the manager capability alone
 * never grants it.
 */
final class KadiwaAuthorization
{
    public const ROLE = 'KADIWA Manager';

    public const MANAGER_ASSIGNMENT = 'kadiwa_manager_user_ids';

    public const INQUIRY_ASSIGNMENT = 'kadiwa_inquiry_reviewer_user_ids';

    public function enabled(): void
    {
        abort_unless(config('mao_workflows.kadiwa_enabled') === true, 404, 'KADIWA integration is not enabled.');
    }

    /** Accounts that may hold a KADIWA assignment at all: the restricted role plus existing admins. */
    public const ELIGIBLE_TYPES = ['Admin', 'Super Admin', self::ROLE];

    public function isActive(User $actor): bool
    {
        return $actor->is_active === true
            && (int) $actor->getKey() > 0
            && in_array($actor->user_type, self::ELIGIBLE_TYPES, true);
    }

    /** True only for the restricted KADIWA Manager role itself. */
    public function hasRole(User $actor): bool
    {
        return $actor->is_active === true
            && (int) $actor->getKey() > 0
            && $actor->user_type === self::ROLE;
    }

    /**
     * Manager authority still requires an EXPLICIT id assignment. Holding the restricted
     * role by itself grants nothing; holding an administrator role by itself grants nothing.
     */
    public function manager(User $actor): bool
    {
        return $this->isActive($actor)
            && in_array((int) $actor->getKey(), $this->assigned(self::MANAGER_ASSIGNMENT), true);
    }

    public function inquiryReviewer(User $actor): bool
    {
        return $this->isActive($actor)
            && in_array((int) $actor->getKey(), $this->assigned(self::INQUIRY_ASSIGNMENT), true);
    }

    /** Manager capability OR explicit per-event coordinator assignment. */
    public function manages(User $actor, KadiwaEvent $event): bool
    {
        return $this->manager($actor) || $this->assignedTo($actor, $event);
    }

    public function assignedTo(User $actor, KadiwaEvent $event): bool
    {
        if (!$this->isActive($actor)) return false;

        return KadiwaEventCoordinator::where('event_id', $event->getKey())
            ->where('user_id', $actor->getKey())
            ->where('active', true)
            ->exists();
    }

    public function isCoordinator(User $actor): bool
    {
        return KadiwaEventCoordinator::where('user_id', $actor->getKey())
            ->where('active', true)
            ->exists();
    }

    /** @return list<int> */
    public function assigned(string $key): array
    {
        $ids = config('mao_workflows.' . $key, []);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /** @return list<int> */
    public function assignedEventIds(User $actor): array
    {
        return KadiwaEventCoordinator::where('user_id', $actor->getKey())
            ->where('active', true)->orderBy('event_id')
            ->pluck('event_id')->map(fn ($id) => (int) $id)->all();
    }
}