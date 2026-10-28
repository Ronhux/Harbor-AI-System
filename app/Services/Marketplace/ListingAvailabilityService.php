<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\ProductListing;
use App\Support\WorkflowTimestamp;
use Illuminate\Support\Facades\DB;

/**
 * Derived listing and transaction state.
 *
 * WHY THIS IS DERIVED, NOT STORED
 * --------------------------------
 * Stock is already decremented exactly once, under a row lock, when an order is created
 * (NegotiationController) and restored exactly once when that order is cancelled. This
 * service never touches quantity_available, so it cannot double-deduct or resurrect stock.
 * It only READS the existing order records and derives what a producer should see:
 *
 *   available = product_listings.quantity_available   (already net of every live order)
 *   reserved  = ordered, buyer committed, not yet delivered
 *   delivered = ordered and actually delivered/completed
 *   sold      = reserved + delivered  (everything still committed to a buyer)
 *
 * FULFILLMENT AND PAYMENT STAY SEPARATE
 * --------------------------------------
 * A delivered order is delivered. It is NOT paid. Revenue and any "paid" label remain the
 * responsibility of payment_status, which this service reads but never infers from.
 *
 * A listing is only reported Sold Out when no stock remains. A partially sold listing keeps
 * its remaining quantity Available.
 */
final class ListingAvailabilityService
{
    /** Committed but not yet delivered. */
    public const RESERVED_STATUSES = [
        'Order Submitted', 'Agreement Confirmed', 'Preparing Order',
        'Ready for Shipment', 'Shipped',
    ];

    /** Actually delivered or closed as complete. */
    public const DELIVERED_STATUSES = ['Delivered', 'Completed'];

    /** Never consume stock permanently. */
    public const UNFULFILLED_STATUSES = ['Cancelled', 'Rejected'];

    /**
     * Per-listing committed/delivered quantities for one producer.
     *
     * @return array<int, array{reserved: float, delivered: float, cancelled: float, statuses: array<int,string>}>
     */
    public function forProducer(int $producerId): array
    {
        $rows = DB::table('order_details')
            ->join('orders', 'orders.order_id', '=', 'order_details.order_id')
            ->join('product_listings', 'product_listings.listing_id', '=', 'order_details.listing_id')
            ->where('product_listings.producer_id', $producerId)
            ->selectRaw('order_details.listing_id, orders.fulfillment_status, order_details.quantity')
            ->get();

        return $this->group($rows);
    }

    /**
     * Per-listing committed/delivered quantities for a buyer, scoped to that buyer's orders.
     * A buyer can never read another buyer's quantities.
     *
     * @return array<int, array{reserved: float, delivered: float, cancelled: float, statuses: array<int,string>}>
     */
    public function forBuyer(int $buyerId): array
    {
        $rows = DB::table('order_details')
            ->join('orders', 'orders.order_id', '=', 'order_details.order_id')
            ->where('orders.buyer_id', $buyerId)
            ->selectRaw('order_details.listing_id, orders.fulfillment_status, order_details.quantity')
            ->get();

        return $this->group($rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<int, array{reserved: float, delivered: float, cancelled: float, statuses: array<int,string>}>
     */
    private function group($rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row->listing_id;
            $status = (string) $row->fulfillment_status;
            $quantity = (float) $row->quantity;

            $grouped[$id] ??= ['reserved' => 0.0, 'delivered' => 0.0, 'cancelled' => 0.0, 'statuses' => []];

            if (in_array($status, self::DELIVERED_STATUSES, true)) {
                $grouped[$id]['delivered'] += $quantity;
                $grouped[$id]['sold'] = $grouped[$id]['reserved'] + $grouped[$id]['delivered'];
            } elseif (in_array($status, self::UNFULFILLED_STATUSES, true)) {
                // Cancelled/rejected stock was already returned to quantity_available.
                $grouped[$id]['cancelled'] += $quantity;
            } else {
                $grouped[$id]['reserved'] += $quantity;
                $grouped[$id]['sold'] = $grouped[$id]['reserved'] + $grouped[$id]['delivered'];
            }

            if (!in_array($status, $grouped[$id]['statuses'], true)) {
                $grouped[$id]['statuses'][] = $status;
            }
        }

        foreach ($grouped as &$entry) {
            $entry['sold'] = round($entry['reserved'] + $entry['delivered'], 3);
            $entry['reserved'] = round($entry['reserved'], 3);
            $entry['delivered'] = round($entry['delivered'], 3);
            $entry['cancelled'] = round($entry['cancelled'], 3);
        }

        return $grouped;
    }

    /**
     * Build the display block for one listing.
     *
     * @param  array<int, array<string, mixed>>  $totals
     * @return array<string, mixed>
     */
    public function present(ProductListing $listing, array $totals): array
    {
        $entry = $totals[$listing->getKey()] ?? ['reserved' => 0.0, 'delivered' => 0.0, 'cancelled' => 0.0, 'sold' => 0.0, 'statuses' => []];
        $available = (float) ($listing->quantity_available ?? $listing->quantity ?? 0);
        $stored = strtolower((string) ($listing->status ?? ''));

        // A listing is only Sold Out when NO stock remains. A partially sold listing keeps
        // its remaining quantity Available, and "Reserved" only applies when every remaining
        // unit is already committed to a buyer. Reserved/sold quantities are reported as
        // separate fields so the producer can see both at once.
        if ($stored === 'expired') {
            $badge = 'Expired';
        } elseif ($available <= 0.0) {
            $badge = $entry['sold'] > 0 || $entry['cancelled'] > 0 ? 'Sold Out' : 'Available';
        } else {
            $badge = 'Available';
        }

        return [
            'availability_badge' => $badge,
            'stored_listing_status' => $listing->status,
            'quantity_available' => round($available, 3),
            'quantity_reserved' => (float) $entry['reserved'],
            'quantity_delivered' => (float) $entry['delivered'],
            'quantity_sold' => (float) $entry['sold'],
            'quantity_returned_cancelled' => (float) $entry['cancelled'],
            'transaction_statuses' => array_values((array) $entry['statuses']),
        ];
    }

    /**
     * Payment state is reported from the order's own payment_status and is never inferred
     * from fulfillment. A delivered order that has not been paid is explicitly "unpaid".
     *
     * @return array<string, mixed>
     */
    public function presentOrder(Order $order): array
    {
        $fulfillment = (string) $order->fulfillment_status;
        $payment = (string) $order->payment_status;
        $delivered = in_array($fulfillment, self::DELIVERED_STATUSES, true);
        $cancelled = in_array($fulfillment, self::UNFULFILLED_STATUSES, true);

        return [
            'fulfillment_status' => $fulfillment,
            'payment_status' => $payment,
            'is_delivered' => $delivered,
            'is_cancelled' => $cancelled,
            // Delivered is a fulfillment fact; paid is a separate money fact.
            'is_paid' => mb_strtolower($payment) === 'paid',
            'delivered_but_unpaid' => $delivered && mb_strtolower($payment) !== 'paid',
            'order_badge' => match (true) {
                $cancelled => 'Cancelled',
                $delivered && mb_strtolower($payment) === 'paid' => 'Delivered',
                $delivered => 'Delivered',
                in_array($fulfillment, self::RESERVED_STATUSES, true) => 'Confirmed',
                default => $fulfillment,
            },
        ];
    }
}