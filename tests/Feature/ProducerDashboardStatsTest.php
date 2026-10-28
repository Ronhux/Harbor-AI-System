<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProducerDashboardStatsTest extends TestCase

{

    /**
     * Deterministic, unique synthetic Philippine mobile number.
     *
     * Mirrors the helper already used by DebugExactTest,
     * DebugServiceExceptionTest and FcaApplicationPersistenceTest. Each call
     * yields a distinct 11-digit number so the unique users.login_number
     * index is satisfied without weakening the constraint.
     */
    private static function uniqueMobile(): string
    {
        static $seq = 0;
        return sprintf('0918%07d', ++$seq);
    }

    use RefreshDatabase;

    private function makeUser(string $email, string $type = 'Farmer'): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Ana',
            'last_name' => 'Dela Cruz',
            'contact_number' => self::uniqueMobile(),
            'user_type' => $type,
        ]);
    }

    private function makeProducer(string $email): array
    {
        $user = $this->makeUser($email);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-' . $user->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        return [$user, $producer];
    }

    private function makeListing(int $producerId, array $overrides = []): ProductListing
    {
        return ProductListing::create(array_merge([
            'producer_id' => $producerId,
            'product_name' => 'Premium Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 120,
            'unit_of_measure' => 'kg',
            'quantity_available' => 100,
            'status' => 'Active',
        ], $overrides));
    }

    private function makeBuyer(): Buyer
    {
        return Buyer::create([
            'user_id' => $this->makeUser('buyer@test.local', 'Buyer')->user_id,
            'organization_name' => 'Aparri LGU',
            'buyer_type' => 'LGU',
        ]);
    }

    private function makeOrder(int $buyerId, string $status, ?string $orderDate): Order
    {
        return Order::create([
            'buyer_id' => $buyerId,
            'order_date' => $orderDate,
            'fulfillment_status' => $status,
            'payment_status' => 'Paid',
        ]);
    }

    private function addDetail(Order $order, ProductListing $listing, float $subtotal): void
    {
        OrderDetail::create([
            'order_id' => $order->order_id,
            'listing_id' => $listing->listing_id,
            'quantity' => 10,
            'unit_price' => $subtotal / 10,
            'subtotal' => $subtotal,
        ]);
    }

    public function test_unpaid_failed_and_cancelled_orders_never_contribute_revenue(): void
    {
        [$user, $producer] = $this->makeProducer('payments@test.local');
        $buyer = $this->makeBuyer();
        $listing = $this->makeListing($producer->producer_id);
        foreach (['Pending', 'Failed', 'Unpaid'] as $payment) {
            $order = $this->makeOrder($buyer->buyer_id, 'Delivered', now()->toDateString());
            $order->update(['payment_status' => $payment]);
            $this->addDetail($order, $listing, 900);
        }
        $this->addDetail($this->makeOrder($buyer->buyer_id, 'Cancelled', now()->toDateString()), $listing, 500);
        $this->addDetail($this->makeOrder($buyer->buyer_id, 'Completed', now()->toDateString()), $listing, 120);
        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')->assertOk()->assertJsonPath('stats.total_revenue', 120);
    }

    public function test_mixed_units_are_averaged_separately(): void
    {
        [$user, $producer] = $this->makeProducer('units@test.local');
        $this->makeListing($producer->producer_id, ['unit_of_measure'=>'kg', 'current_price_per_unit'=>100]);
        $this->makeListing($producer->producer_id, ['unit_of_measure'=>'kg', 'current_price_per_unit'=>200]);
        $this->makeListing($producer->producer_id, ['unit_of_measure'=>'piece', 'current_price_per_unit'=>25]);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')->assertOk();
        $averages = collect($response->json('stats.average_prices_by_unit'))->pluck('price', 'unit')->all();
        $this->assertEquals(['kg'=>150, 'piece'=>25], $averages);
        $response->assertJsonPath('stats.average_listing_price', 0);
    }

    public function test_a_producer_without_listings_reports_zeroed_statistics(): void
    {
        [$user] = $this->makeProducer('empty@test.local');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard');

        $response->assertOk()
            ->assertJsonPath('stats.total_revenue', 0)
            ->assertJsonPath('stats.active_listings', 0)
            ->assertJsonPath('stats.pending_orders', 0)
            ->assertJsonPath('stats.total_orders', 0)
            ->assertJsonPath('stats.total_views', 0)
            ->assertJsonPath('stats.average_listing_price', 0)
            ->assertJsonCount(0, 'product_mix')
            ->assertJsonCount(0, 'recent_orders')
            // Six consecutive calendar months are always present.
            ->assertJsonCount(6, 'revenue_by_month');

        foreach ($response->json('revenue_by_month') as $month) {
            $this->assertSame(0, $month['revenue']);
        }

        // Listing metrics have no history to compare against.
        $response->assertJsonPath('stats.changes.active_listings.available', false)
            ->assertJsonPath('stats.changes.active_listings.percent', null)
            ->assertJsonPath('stats.changes.average_listing_price.available', false);
    }

    public function test_active_listings_require_an_active_status_and_stock_on_hand(): void
    {
        [$user, $producer] = $this->makeProducer('stock@test.local');

        $this->makeListing($producer->producer_id, ['product_name' => 'Sellable', 'status' => 'Active', 'quantity_available' => 5]);
        $this->makeListing($producer->producer_id, ['product_name' => 'Available status', 'status' => 'Available', 'quantity_available' => 5]);
        // Active but sold out.
        $this->makeListing($producer->producer_id, ['product_name' => 'Sold out', 'status' => 'Active', 'quantity_available' => 0]);
        // In stock but not sellable.
        $this->makeListing($producer->producer_id, ['product_name' => 'Paused', 'status' => 'Paused', 'quantity_available' => 40]);

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.active_listings', 2);
    }

    public function test_average_listing_price_ignores_inactive_and_unpriced_listings(): void
    {
        [$user, $producer] = $this->makeProducer('price@test.local');

        $this->makeListing($producer->producer_id, ['product_name' => 'Rice', 'current_price_per_unit' => 100, 'quantity_available' => 10]);
        $this->makeListing($producer->producer_id, ['product_name' => 'Corn', 'current_price_per_unit' => 200, 'quantity_available' => 10]);
        // Excluded: no usable price.
        $this->makeListing($producer->producer_id, ['product_name' => 'Unpriced', 'current_price_per_unit' => null, 'price_per_unit' => null, 'quantity_available' => 10]);
        // Excluded: not sellable.
        $this->makeListing($producer->producer_id, ['product_name' => 'Sold out', 'current_price_per_unit' => 9999, 'quantity_available' => 0]);

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.average_listing_price', 150);
    }

    public function test_pending_orders_count_distinct_orders_and_exclude_terminal_statuses(): void
    {
        [$user, $producer] = $this->makeProducer('pending@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;

        $submitted = $this->makeOrder($buyerId, 'Order Submitted', now()->toDateString());
        $this->addDetail($submitted, $this->makeListing($producer->producer_id), 1000);
        // A second product from the same producer must not double count the order.
        $this->addDetail($submitted, $this->makeListing($producer->producer_id, ['product_name' => 'Second']), 500);

        // Legacy column default, still in progress.
        $legacy = $this->makeOrder($buyerId, 'Pending', now()->toDateString());
        $this->addDetail($legacy, $this->makeListing($producer->producer_id, ['product_name' => 'Legacy']), 300);

        $this->addDetail($this->makeOrder($buyerId, 'Shipped', now()->toDateString()), $this->makeListing($producer->producer_id), 100);

        // Terminal statuses are not pending.
        foreach (['Delivered', 'Completed', 'Cancelled', 'Rejected'] as $terminal) {
            $order = $this->makeOrder($buyerId, $terminal, now()->toDateString());
            $this->addDetail($order, $this->makeListing($producer->producer_id), 100);
        }

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')
            ->assertOk()
            // The multi detail order, the legacy Pending order and the Shipped order.
            ->assertJsonPath('stats.pending_orders', 3);
    }

    public function test_pending_orders_ignore_orders_that_only_contain_other_producers_products(): void
    {
        [$user] = $this->makeProducer('mine@test.local');
        [, $other] = $this->makeProducer('theirs@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;

        $order = $this->makeOrder($buyerId, 'Order Submitted', now()->toDateString());
        $this->addDetail($order, $this->makeListing($other->producer_id), 5000);

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.pending_orders', 0)
            ->assertJsonPath('stats.total_revenue', 0);
    }

    public function test_total_orders_matches_the_orders_page_scope_and_counts_each_order_once(): void
    {
        [$user, $producer] = $this->makeProducer('total-orders@test.local');
        [, $otherProducer] = $this->makeProducer('other-total-orders@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;

        // Several own lines still make this one order, including terminal orders.
        $shared = $this->makeOrder($buyerId, 'Order Submitted', now()->toDateString());
        $this->addDetail($shared, $this->makeListing($producer->producer_id), 1000);
        $this->addDetail($shared, $this->makeListing($producer->producer_id, ['product_name' => 'Corn']), 500);

        $cancelled = $this->makeOrder($buyerId, 'Cancelled', now()->toDateString());
        $this->addDetail($cancelled, $this->makeListing($producer->producer_id, ['product_name' => 'Fish']), 300);

        $otherOnly = $this->makeOrder($buyerId, 'Delivered', now()->toDateString());
        $this->addDetail($otherOnly, $this->makeListing($otherProducer->producer_id), 9000);

        $dashboard = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk();
        $ordersPage = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/orders')
            ->assertOk()
            ->json();

        $dashboard->assertJsonPath('stats.total_orders', count($ordersPage));
        $this->assertSame(2, count($ordersPage));
    }

    public function test_total_revenue_uses_only_this_producers_subtotals_and_completed_statuses(): void
    {
        [$user, $producer] = $this->makeProducer('revenue@test.local');
        [, $other] = $this->makeProducer('other@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;

        $mine = $this->makeListing($producer->producer_id);
        $theirs = $this->makeListing($other->producer_id);

        // A multi producer order contributes only this producer's own subtotal.
        $mixed = $this->makeOrder($buyerId, 'Delivered', now()->toDateString());
        $this->addDetail($mixed, $mine, 2500);
        $this->addDetail($mixed, $theirs, 9000);

        $completed = $this->makeOrder($buyerId, 'Completed', now()->subMonths(2)->toDateString());
        $this->addDetail($completed, $mine, 1000);

        // Unfinished, cancelled and rejected orders never count as revenue.
        foreach (['Order Submitted', 'Shipped', 'Cancelled', 'Rejected'] as $status) {
            $order = $this->makeOrder($buyerId, $status, now()->toDateString());
            $this->addDetail($order, $mine, 700);
        }

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.total_revenue', 3500);
    }

    public function test_revenue_trend_covers_six_calendar_months_in_order_with_zero_months(): void
    {
        [$user, $producer] = $this->makeProducer('trend@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;
        $listing = $this->makeListing($producer->producer_id);

        $this->addDetail($this->makeOrder($buyerId, 'Delivered', now()->startOfMonth()->toDateString()), $listing, 500);
        $this->addDetail($this->makeOrder($buyerId, 'Delivered', now()->subMonths(2)->startOfMonth()->toDateString()), $listing, 300);
        // Older than the window, so it must not appear.
        $this->addDetail($this->makeOrder($buyerId, 'Delivered', now()->subMonths(9)->startOfMonth()->toDateString()), $listing, 999);

        $months = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk()
            ->json('revenue_by_month');

        $this->assertCount(6, $months);

        $keys = array_column($months, 'month');
        $sorted = $keys;
        sort($sorted);
        $this->assertSame($sorted, $keys, 'Months must be chronological, oldest first.');

        $this->assertSame(
            now()->subMonths(5)->format('Y-m'),
            $keys[0]
        );
        $this->assertSame(now()->format('Y-m'), $keys[5]);

        $byKey = array_column($months, 'revenue', 'month');

        // PHP encodes a whole float without its decimal part, so these are
        // compared numerically rather than by type.
        $this->assertEqualsWithDelta(300.0, $byKey[now()->subMonths(2)->format('Y-m')], 0.001);
        $this->assertEqualsWithDelta(500.0, $byKey[now()->format('Y-m')], 0.001);
        // The month in between has no revenue and is still reported.
        $this->assertEqualsWithDelta(0.0, $byKey[now()->subMonth()->format('Y-m')], 0.001);
    }

    public function test_month_over_month_change_is_real_and_never_divides_by_zero(): void
    {
        [$user, $producer] = $this->makeProducer('mom@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;
        $listing = $this->makeListing($producer->producer_id);

        // Previous month 1000, this month 1500 => +50%.
        $this->addDetail($this->makeOrder($buyerId, 'Delivered', now()->subMonth()->startOfMonth()->toDateString()), $listing, 1000);
        $this->addDetail($this->makeOrder($buyerId, 'Delivered', now()->startOfMonth()->toDateString()), $listing, 1500);

        $changes = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk()
            ->json('stats.changes');

        $this->assertTrue($changes['total_revenue']['available']);
        $this->assertEqualsWithDelta(1500.0, $changes['total_revenue']['current'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $changes['total_revenue']['previous'], 0.001);
        $this->assertEqualsWithDelta(50.0, $changes['total_revenue']['percent'], 0.001);
        $this->assertSame('up', $changes['total_revenue']['direction']);

        // No pending orders in either month, so no percentage can be produced.
        $this->assertFalse($changes['pending_orders']['available']);
        $this->assertNull($changes['pending_orders']['percent']);
    }

    public function test_percentage_comparison_excludes_later_days_of_previous_month(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-10 12:00:00'));
        [$user, $producer] = $this->makeProducer('comparable@test.local');
        $buyer = $this->makeBuyer();
        $listing = $this->makeListing($producer->producer_id);
        foreach (['2026-09-02'=>100, '2026-09-20'=>900, '2026-10-02'=>150] as $date=>$amount) {
            $this->addDetail($this->makeOrder($buyer->buyer_id,'Delivered',$date),$listing,$amount);
        }
        $this->actingAs($user,'sanctum')->getJson('/api/producer/dashboard')->assertOk()
            ->assertJsonPath('stats.changes.total_revenue.percent',50);
    }

    public function test_product_mix_groups_eligible_listings_by_category(): void
    {
        [$user, $producer] = $this->makeProducer('mix@test.local');

        $this->makeListing($producer->producer_id, ['product_category' => 'Grains']);
        $this->makeListing($producer->producer_id, ['product_category' => 'Grains']);
        $this->makeListing($producer->producer_id, ['product_name' => 'Tilapia', 'product_category' => 'Fish']);
        $this->makeListing($producer->producer_id, ['product_name' => 'Unlabelled', 'product_category' => null, 'category' => null]);
        // Not eligible, so it is left out of the mix.
        $this->makeListing($producer->producer_id, ['product_name' => 'Sold out', 'product_category' => 'Grains', 'quantity_available' => 0]);

        $mix = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk()
            ->json('product_mix');

        $this->assertSame(
            [
                ['category' => 'Grains', 'listings' => 2],
                ['category' => 'Fish', 'listings' => 1],
                ['category' => 'Uncategorized', 'listings' => 1],
            ],
            $mix
        );
    }

    public function test_listing_order_counts_are_distinct_and_skip_cancelled_orders(): void
    {
        [$user, $producer] = $this->makeProducer('counts@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;

        $listing = $this->makeListing($producer->producer_id, ['product_name' => 'Rice']);
        $unsold = $this->makeListing($producer->producer_id, ['product_name' => 'Never ordered']);

        $this->addDetail($this->makeOrder($buyerId, 'Order Submitted', now()->toDateString()), $listing, 100);
        $this->addDetail($this->makeOrder($buyerId, 'Delivered', now()->toDateString()), $listing, 200);
        // Cancelled orders returned their stock and never became a sale.
        $cancelled = $this->makeOrder($buyerId, 'Cancelled', now()->toDateString());
        $this->addDetail($cancelled, $listing, 300);
        // A repeated detail row for the same order is one order, not two.
        $repeated = $this->makeOrder($buyerId, 'Shipped', now()->toDateString());
        $this->addDetail($repeated, $listing, 400);
        $this->addDetail($repeated, $listing, 400);

        $listings = collect($this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk()
            ->json('product_listings'))
            ->keyBy('product_name');

        $this->assertSame(3, $listings['Rice']['orders']);
        $this->assertSame(0, $listings['Never ordered']['orders']);

        // The producer dashboard now reports a real view count. This listing was
        // never viewed, so it must start at zero rather than be missing.
        $this->assertSame(0, $listings['Rice']['views']);
    }

    public function test_recent_orders_expose_unit_and_organization_name(): void
    {
        [$user, $producer] = $this->makeProducer('recent@test.local');
        $buyer = $this->makeBuyer();
        $listing = $this->makeListing($producer->producer_id, ['unit_of_measure' => 'kg']);

        $order = $this->makeOrder($buyer->buyer_id, 'Order Submitted', '2026-03-09');
        $this->addDetail($order, $listing, 12500);

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')
            ->assertOk()
            ->assertJsonPath('recent_orders.0.order_id', $order->order_id)
            ->assertJsonPath('recent_orders.0.fulfillment_status', 'Order Submitted')
            ->assertJsonPath('recent_orders.0.buyer.organization_name', 'Aparri LGU')
            ->assertJsonPath('recent_orders.0.details.0.product_name', 'Premium Rice')
            ->assertJsonPath('recent_orders.0.details.0.unit', 'kg')
            ->assertJsonPath('recent_orders.0.details.0.quantity', 10)
            ->assertJsonPath('recent_orders.0.producer_subtotal', 12500);
    }

    public function test_recent_orders_report_only_the_producers_own_subtotal(): void
    {
        [$user, $producer] = $this->makeProducer('mixed@test.local');
        [, $other] = $this->makeProducer('mixedother@test.local');
        $buyerId = $this->makeBuyer()->buyer_id;

        $order = $this->makeOrder($buyerId, 'Delivered', now()->toDateString());
        $this->addDetail($order, $this->makeListing($producer->producer_id), 4000);
        $this->addDetail($order, $this->makeListing($other->producer_id), 7000);

        $recent = $this->actingAs($user, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk()
            ->json('recent_orders.0');

        $this->assertSame(4000, $recent['producer_subtotal']);
        $this->assertCount(1, $recent['details']);
    }

    public function test_dashboard_is_refused_for_a_buyer_account(): void
    {
        $buyer = $this->makeUser('refused@test.local', 'Buyer');

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertStatus(403);
    }

    public function test_dashboard_still_requires_authentication(): void
    {
        $this->getJson('/api/producer/dashboard')->assertStatus(401);
    }

    public function test_dashboard_does_not_fan_out_a_query_per_listing(): void
    {
        [$user, $producer] = $this->makeProducer('queries@test.local');

        // Record policy acceptances up front so agreement bookkeeping and
        // first-time inserts stay outside the counted request windows.
        app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($user);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        for ($index = 0; $index < 3; $index++) {
            $this->makeListing($producer->producer_id, ['product_name' => 'Listing ' . $index]);
        }

        // Only the requests are counted, not the fixture writes.
        $queries = 0;

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')->assertOk();
        $withFewListings = $queries;

        for ($index = 0; $index < 15; $index++) {
            $this->makeListing($producer->producer_id, ['product_name' => 'Extra ' . $index]);
        }

        // Only the requests are counted, not the fixture writes above.
        $queries = 0;

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard')->assertOk();
        $withManyListings = $queries;

        $this->assertSame(
            $withFewListings,
            $withManyListings,
            'The dashboard must not run an extra query per listing.'
        );
    }
}
