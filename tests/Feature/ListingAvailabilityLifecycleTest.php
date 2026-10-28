<?php

namespace Tests\Feature;

use App\Models\{Buyer, Order, OrderDetail, Producer, ProductListing, User};
use App\Services\Marketplace\ListingAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Producer listing and transaction state.
 *
 * Stock is decremented once when an order is created and restored once when it is cancelled,
 * under a row lock. These tests prove the DERIVED view never double-deducts, never reports a
 * partially sold listing as Sold Out, never treats Delivered as Paid, and never leaks one
 * account's quantities to another.
 */
class ListingAvailabilityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Buyer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $producerUser = User::create([
            'first_name' => 'Ana', 'last_name' => 'Rizal',
            'email' => 'ana.rizal@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180000001', 'user_type' => 'Farmer', 'is_active' => true,
        ]);
        $this->producer = Producer::create([
            'user_id' => $producerUser->user_id, 'verification_status' => 'Verified',
        ]);

        $buyerUser = User::create([
            'first_name' => 'Ben', 'last_name' => 'Cruz',
            'email' => 'ben.cruz@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180000002', 'user_type' => 'Buyer', 'is_active' => true,
        ]);
        $this->buyer = Buyer::create(['user_id' => $buyerUser->user_id]);
    }

    private function listing(float $original, float $available): ProductListing
    {
        return ProductListing::create([
            'producer_id' => $this->producer->producer_id,
            'product_name' => 'Cabbage', 'product_category' => 'Vegetables',
            'current_price_per_unit' => 40, 'unit_of_measure' => 'kg',
            'quantity' => $original, 'quantity_available' => $available,
            'status' => 'Active',
        ]);
    }

    private function order(ProductListing $listing, float $quantity, string $fulfillment, string $payment = 'Pending'): Order
    {
        $order = Order::create([
            'buyer_id' => $this->buyer->buyer_id, 'product_listing_id' => $listing->listing_id,
            'quantity' => (int) $quantity, 'total_amount' => $quantity * 40,
            'fulfillment_status' => $fulfillment, 'payment_status' => $payment,
            'order_date' => now()->toDateString(),
        ]);
        OrderDetail::create([
            'order_id' => $order->order_id, 'listing_id' => $listing->listing_id,
            'quantity' => $quantity, 'unit_price' => 40, 'subtotal' => $quantity * 40,
        ]);

        return $order;
    }

    private function present(ProductListing $listing): array
    {
        $service = app(ListingAvailabilityService::class);

        return $service->present($listing->fresh(), $service->forProducer((int) $this->producer->producer_id));
    }

    public function test_a_fresh_listing_is_available_with_full_stock(): void
    {
        $result = $this->present($this->listing(100, 100));

        $this->assertSame('Available', $result['availability_badge']);
        $this->assertSame(100.0, $result['quantity_available']);
        $this->assertSame(0.0, $result['quantity_reserved']);
        $this->assertSame(0.0, $result['quantity_sold']);
    }

    public function test_a_partially_sold_listing_stays_available_and_keeps_remaining_stock(): void
    {
        $listing = $this->listing(100, 100);
        // Buyer committed to 30; stock model already reduced availability to 70.
        $listing->quantity_available = 70;
        $listing->save();
        $this->order($listing, 30, 'Agreement Confirmed');

        $result = $this->present($listing);

        // NOT Sold Out, because stock remains.
        $this->assertSame('Available', $result['availability_badge']);
        $this->assertSame(70.0, $result['quantity_available']);
        $this->assertSame(30.0, $result['quantity_sold']);
        $this->assertSame(30.0, $result['quantity_reserved']);
        $this->assertSame(0.0, $result['quantity_delivered']);
    }

    public function test_a_fully_reserved_listing_shows_sold_out_with_every_unit_reserved(): void
    {
        $listing = $this->listing(100, 0);
        $this->order($listing, 100, 'Preparing Order');

        $result = $this->present($listing);

        // No stock remains and every unit is committed, so nothing is Available.
        $this->assertSame('Sold Out', $result['availability_badge']);
        $this->assertSame(0.0, $result['quantity_available']);
        $this->assertSame(100.0, $result['quantity_reserved']);
    }

    public function test_reserved_badge_requires_no_remaining_stock(): void
    {
        // Guard against a regression where a partial reservation hides remaining stock.
        $partial = $this->listing(100, 40);
        $this->order($partial, 60, 'Preparing Order');
        $this->assertSame('Available', $this->present($partial)['availability_badge']);
        $this->assertSame(60.0, $this->present($partial)['quantity_reserved']);
    }

    public function test_a_fully_sold_listing_reports_sold_out(): void
    {
        $listing = $this->listing(50, 0);
        $this->order($listing, 25, 'Delivered', 'Paid');
        $this->order($listing, 25, 'Shipped');

        $result = $this->present($listing);

        $this->assertSame('Sold Out', $result['availability_badge']);
        $this->assertSame(50.0, $result['quantity_sold']);
        $this->assertSame(25.0, $result['quantity_delivered']);
        $this->assertSame(25.0, $result['quantity_reserved']);
    }

    public function test_delivered_quantity_is_tracked_separately_from_reserved(): void
    {
        $listing = $this->listing(100, 40);
        $this->order($listing, 30, 'Delivered', 'Paid');
        $this->order($listing, 30, 'Shipped');

        $result = $this->present($listing);

        $this->assertSame(30.0, $result['quantity_delivered']);
        $this->assertSame(30.0, $result['quantity_reserved']);
        $this->assertSame(60.0, $result['quantity_sold']);
        $this->assertSame(40.0, $result['quantity_available']);
    }

    public function test_a_cancelled_order_does_not_consume_stock_and_is_reported_separately(): void
    {
        $listing = $this->listing(100, 100);
        // Cancelled stock was already returned to quantity_available by the order service.
        $this->order($listing, 40, 'Cancelled');

        $result = $this->present($listing);

        $this->assertSame('Available', $result['availability_badge']);
        $this->assertSame(100.0, $result['quantity_available'], 'cancelled stock must be available again');
        $this->assertSame(0.0, $result['quantity_sold'], 'a cancelled order is not a sale');
        $this->assertSame(0.0, $result['quantity_reserved']);
        $this->assertSame(40.0, $result['quantity_returned_cancelled']);
    }

    public function test_a_rejected_order_is_treated_like_a_cancellation(): void
    {
        $listing = $this->listing(100, 100);
        $this->order($listing, 25, 'Rejected');

        $result = $this->present($listing);
        $this->assertSame(0.0, $result['quantity_sold']);
        $this->assertSame(100.0, $result['quantity_available']);
    }

    /** Delivered is a fulfillment fact. It must never be reported as paid. */
    public function test_delivery_is_never_treated_as_payment(): void
    {
        $service = app(ListingAvailabilityService::class);

        $deliveredUnpaid = $service->presentOrder(new Order([
            'fulfillment_status' => 'Delivered', 'payment_status' => 'Pending',
        ]));
        $this->assertTrue($deliveredUnpaid['is_delivered']);
        $this->assertFalse($deliveredUnpaid['is_paid']);
        $this->assertTrue($deliveredUnpaid['delivered_but_unpaid']);
        $this->assertSame('Delivered', $deliveredUnpaid['order_badge']);

        $deliveredPaid = $service->presentOrder(new Order([
            'fulfillment_status' => 'Delivered', 'payment_status' => 'Paid',
        ]));
        $this->assertTrue($deliveredPaid['is_paid']);
        $this->assertFalse($deliveredPaid['delivered_but_unpaid']);

        $cancelled = $service->presentOrder(new Order([
            'fulfillment_status' => 'Cancelled', 'payment_status' => 'Paid',
        ]));
        $this->assertTrue($cancelled['is_cancelled']);
        $this->assertSame('Cancelled', $cancelled['order_badge']);
    }

    /** Reading the derived view repeatedly must never change stock. */
    public function test_deriving_state_never_deducts_stock_twice(): void
    {
        $listing = $this->listing(100, 70);
        $this->order($listing, 30, 'Agreement Confirmed');
        $before = (float) ProductListing::find($listing->listing_id)->quantity_available;

        $service = app(ListingAvailabilityService::class);
        for ($i = 0; $i < 5; $i++) {
            $service->present($listing->fresh(), $service->forProducer((int) $this->producer->producer_id));
        }

        $after = (float) ProductListing::find($listing->listing_id)->quantity_available;
        $this->assertSame($before, $after, 'deriving state must be read-only');
        $this->assertSame(70.0, $after);
    }

    public function test_buyer_scoped_totals_never_expose_another_buyers_quantities(): void
    {
        $listing = $this->listing(100, 70);
        $this->order($listing, 30, 'Shipped');

        $service = app(ListingAvailabilityService::class);

        $otherBuyerUser = User::create([
            'first_name' => 'Cara', 'last_name' => 'Diaz',
            'email' => 'cara.diaz@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180000003', 'user_type' => 'Buyer', 'is_active' => true,
        ]);
        $otherBuyer = Buyer::create(['user_id' => $otherBuyerUser->user_id]);

        $mine = $service->forBuyer((int) $this->buyer->buyer_id);
        $theirs = $service->forBuyer((int) $otherBuyer->buyer_id);

        $this->assertSame(30.0, $mine[$listing->listing_id]['reserved']);
        $this->assertArrayNotHasKey($listing->listing_id, $theirs, 'a buyer must not see another buyer\'s quantities');
    }

    public function test_order_details_are_never_removed_when_stock_is_fully_sold(): void
    {
        $listing = $this->listing(10, 0);
        $order = $this->order($listing, 10, 'Delivered', 'Paid');

        // The transaction record is preserved so the producer can see what was sold.
        $this->assertSame(1, OrderDetail::where('order_id', $order->order_id)->count());
        $this->assertSame('Delivered', Order::find($order->order_id)->fulfillment_status);
        $this->assertSame('Sold Out', $this->present($listing)['availability_badge']);
    }
}