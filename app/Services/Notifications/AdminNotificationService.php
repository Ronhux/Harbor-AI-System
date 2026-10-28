<?php

namespace App\Services\Notifications;

use App\Models\TransactionNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Operational notifications for administrators.
 *
 * WHY THIS EXISTS
 * ---------------
 * Transaction notifications only ever addressed the buyer or producer on the other side of a
 * negotiation or order. An administrator therefore had no queue at all: nothing told them a
 * demand request had arrived or that a new producer was awaiting verification, even though
 * those are precisely the items sitting in their review queue.
 *
 * WHAT IT WILL NOT DO
 * -------------------
 * - It does not invent activity. Every notification is created from a record that was just
 *   written, inside the same request that wrote it.
 * - It does not assign authority. Addressing a notification to an existing Admin or Super Admin
 *   grants that person nothing they did not already hold.
 * - It does not carry private commercial detail. Bodies state what happened, which record to
 *   open and where the reviewer must go for the details. Names, contact numbers, addresses and
 *   amounts are deliberately left in the record itself, behind the reviewer's own authorization.
 * - It does not notify producers or buyers. Recipients are filtered to active administrator
 *   accounts only.
 */
final class AdminNotificationService
{
    public const ADMIN_TYPES = ['Admin', 'Super Admin'];

    /**
     * Recipients for an administrative notice.
     *
     * Only active administrator accounts qualify, and the caller's own account is excluded
     * when they are already the actor, so acting on a queue does not notify you about it.
     *
     * @return list<int>
     */
    public function recipients(?int $excludeUserId = null): array
    {
        return User::query()
            ->whereIn('user_type', self::ADMIN_TYPES)
            ->where('is_active', true)
            ->when($excludeUserId !== null, fn ($q) => $q->where('user_id', '!=', $excludeUserId))
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Create one notice per recipient. Returns how many were created.
     *
     * @param  array{negotiation_id?: int|null, order_id?: int|null}  $context
     */
    public function notifyAdministrators(
        string $title,
        string $body,
        ?int $excludeUserId = null,
        array $context = []
    ): int {
        $recipients = $this->recipients($excludeUserId);
        if ($recipients === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(fn (int $userId) => [
            'recipient_user_id' => $userId,
            'negotiation_id' => $context['negotiation_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'title' => mb_substr($title, 0, 160),
            'body' => $body,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $recipients);

        DB::table('transaction_notifications')->insert($rows);

        return count($rows);
    }

    /**
     * A demand request entered the review queue.
     *
     * Only the operational facts are described. The requesting institution, contact details and
     * commercial terms stay in the record and are only visible to a reviewer who is already
     * authorized to open it.
     */
    public function demandRequestSubmitted(Model $request, ?int $actorUserId = null): int
    {
        $id = $request->getKey();
        $commodity = $request->product_name ?: 'an unspecified commodity';
        $quantity = $request->quantity_needed ?: $request->required_quantity;
        $unit = $request->unit ?: 'units';
        $where = $request->location ?: 'no location recorded';

        return $this->notifyAdministrators(
            'New demand request awaiting review',
            "Demand request #{$id} for {$commodity} ({$quantity} {$unit}, {$where}) has entered the "
            . "administrative review queue. Open the demand review queue to view the requesting "
            . "institution and its commercial terms.",
            $actorUserId
        );
    }

    /**
     * A new producer account is awaiting verification.
     */
    public function producerAwaitingVerification(Model $producer, ?int $actorUserId = null): int
    {
        $id = $producer->getKey();

        return $this->notifyAdministrators(
            'New producer awaiting verification',
            "Producer record #{$id} has been registered and is awaiting administrative "
            . "verification. Open the producer registry to review the submitted details.",
            $actorUserId
        );
    }

    /**
     * A buyer placed a real order that an administrator may need to be aware of.
     *
     * The order id is recorded so the notice is traceable; no amounts are reproduced here.
     */
    public function orderPlaced(Model $order, ?int $actorUserId = null): int
    {
        $id = $order->getKey();

        return $this->notifyAdministrators(
            'New order recorded',
            "Order #{$id} has been placed against a live listing. Open the order record to review "
            . "its fulfillment and payment status.",
            $actorUserId,
            ['order_id' => (int) $id]
        );
    }

    /** Unread count for one recipient. Never aggregated across recipients. */
    public function unreadCount(int $userId): int
    {
        return TransactionNotification::query()
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}