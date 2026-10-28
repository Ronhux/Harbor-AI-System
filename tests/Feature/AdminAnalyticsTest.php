<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\DemandRequest;
use App\Models\Order;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-06 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $email, string $type, string $createdAt = '2026-10-03 12:00:00'): User
    {
        $user = User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'user_type' => $type,
        ]);
        DB::table('users')->where('user_id', $user->user_id)->update([
            User::accountCreatedAtColumn() => $createdAt,
        ]);
        return $user->fresh();
    }

    private function makeProducer(string $email, string $status, string $createdAt = '2026-10-03 12:00:00'): Producer
    {
        $user = $this->makeUser($email, 'Farmer', $createdAt);
        return Producer::create(['user_id' => $user->user_id, 'verification_status' => $status, 'producer_type' => 'Farmer']);
    }

    private function makeBuyer(string $email, string $createdAt = '2026-10-03 12:00:00'): Buyer
    {
        $user = $this->makeUser($email, 'Buyer', $createdAt);
        return Buyer::create(['user_id' => $user->user_id, 'organization_name' => 'Test Institution', 'buyer_type' => 'Institution', 'verification_status' => 'Verified']);
    }

    private function makeListing(Producer $producer, array $overrides = []): ProductListing
    {
        return ProductListing::create(array_merge([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Rice',
            'product_category' => 'Grains',
            'category' => 'Grains',
            'quantity_available' => 10,
            'quantity' => 10,
            'unit_of_measure' => 'kg',
            'unit' => 'kg',
            'status' => 'Active',
        ], $overrides));
    }

    private function makeDemand(Buyer $buyer, array $overrides = []): DemandRequest
    {
        return DemandRequest::create(array_merge([
            'buyer_id' => $buyer->buyer_id,
            'product_name' => 'Rice',
            'category' => 'Grains',
            'quantity_needed' => 20,
            'unit' => 'kg',
            'deadline' => '2026-10-30',
            'status' => 'Active',
        ], $overrides));
    }

    private function businessSnapshot(): array
    {
        $tables = [
            'users' => 'user_id',
            'producers' => 'producer_id',
            'buyers' => 'buyer_id',
            'product_listings' => 'listing_id',
            'orders' => 'order_id',
            'demand_requests' => 'request_id',
        ];
        $snapshot = [];
        foreach ($tables as $table => $key) {
            $snapshot[$table] = DB::table($table)->orderBy($key)->get()->toArray();
        }
        return $snapshot;
    }

    public function test_admin_endpoints_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/analytics/monitoring')->assertUnauthorized();
        $this->getJson('/api/admin/analytics/supply-demand')->assertUnauthorized();

        foreach (['Farmer', 'Buyer', 'Super Admin'] as $role) {
            $user = $this->makeUser(strtolower(str_replace(' ', '-', $role)) . '@analytics.test', $role);
            $this->actingAs($user, 'sanctum')->getJson('/api/admin/analytics/monitoring')->assertForbidden();
            $this->getJson('/api/admin/analytics/supply-demand')->assertForbidden();
        }
    }

    public function test_monitoring_metrics_use_roles_statuses_and_inclusive_period_boundaries(): void
    {
        $verifiedProducer = $this->makeProducer('verified-1@analytics.test', 'Verified', '2026-10-01 00:00:00');
        $pendingProducer = $this->makeProducer('pending@analytics.test', 'Pending', '2026-10-06 23:59:59');
        $this->makeProducer('verified-2@analytics.test', 'Verified', '2026-09-30 23:59:59');
        $this->makeUser('fisher@analytics.test', 'Fisherfolk', '2026-10-02 10:00:00');
        $this->makeUser('admin-not-producer@analytics.test', 'Admin', '2026-10-02 10:00:00');
        $buyer = $this->makeBuyer('buyer-in-range@analytics.test', '2026-10-06 23:59:59');
        $this->makeBuyer('buyer-outside@analytics.test', '2026-09-30 23:59:59');

        $this->makeListing($verifiedProducer, ['product_name' => 'Rice', 'status' => 'Active', 'quantity_available' => 10, 'created_at' => '2026-10-01 00:00:00']);
        $this->makeListing($verifiedProducer, ['product_name' => 'Verified Empty Corn', 'status' => 'Active', 'quantity_available' => 0, 'created_at' => '2026-10-04 10:00:00']);
        $this->makeListing($verifiedProducer, ['product_name' => 'Paused Corn', 'status' => 'Paused', 'quantity_available' => 30, 'created_at' => '2026-10-03 10:00:00']);
        $this->makeListing($pendingProducer, ['product_name' => 'No Stock Rice', 'status' => 'Available', 'quantity_available' => 0, 'created_at' => '2026-10-06 23:59:59']);
        $outsideListing = $this->makeListing($verifiedProducer, ['product_name' => 'Outside Rice']);
        DB::table('product_listings')->where('listing_id', $outsideListing->listing_id)->update(['created_at' => '2026-09-30 23:59:59']);

        foreach ([['Order Submitted', '2026-10-01'], ['Completed', '2026-10-06'], ['Mystery', '2026-10-05'], ['Cancelled', '2026-09-30']] as [$status, $date]) {
            Order::create(['buyer_id' => $buyer->buyer_id, 'order_date' => $date, 'created_at' => $date . ' 12:00:00', 'fulfillment_status' => $status, 'payment_status' => 'Pending']);
        }

        $this->makeDemand($buyer, ['product_name' => 'Active Rice', 'status' => 'Active', 'deadline' => '2026-10-06', 'created_at' => '2026-10-01 00:00:00']);
        $this->makeDemand($buyer, ['product_name' => 'Expired Open Rice', 'status' => 'open', 'deadline' => '2026-10-05', 'created_at' => '2026-10-03 12:00:00']);
        $this->makeDemand($buyer, ['product_name' => 'Closed Rice', 'status' => 'Completed', 'created_at' => '2026-10-06 23:59:59']);

        DB::table('admin_activity_logs')->insert([
            'user_id' => null,
            'action' => 'Persisted test audit event',
            'subject' => 'test subject',
            'status' => 'failure',
            'created_at' => '2026-10-06 11:00:00',
        ]);
        $userCreatedAt = User::accountCreatedAtColumn();
        $producerFixtureRows = DB::table('users')
            ->whereIn('user_type', ['Farmer', 'Fisherfolk', 'Producer'])
            ->whereBetween($userCreatedAt, ['2026-10-01 00:00:00', '2026-10-06 23:59:59.999999'])
            ->get(['user_type', $userCreatedAt]);
        $this->assertCount(3, $producerFixtureRows, $producerFixtureRows->toJson());
        $admin = $this->makeUser('analytics-admin@analytics.test', 'Admin');
        $before = $this->businessSnapshot();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics/monitoring?start_date=2026-10-01&end_date=2026-10-06');
        $response->assertOk()
            ->assertJsonPath('meta.timezone', 'UTC')
            ->assertJsonPath('meta.start_date', '2026-10-01')
            ->assertJsonPath('metrics.producer_accounts.created_in_period', 3)
            ->assertJsonPath('metrics.producer_accounts.verified_in_period', 1)
            ->assertJsonPath('metrics.producer_accounts.verification_status_counts.0.status', 'Not assessed')
            ->assertJsonPath('metrics.producer_accounts.verification_status_counts.1.status', 'Pending')
            ->assertJsonPath('metrics.producer_accounts.verification_status_counts.2.status', 'Verified')
            ->assertJsonPath('metrics.institutional_buyer_accounts.created_in_period', 1)
            ->assertJsonPath('metrics.product_listings.created_in_period', 4)
            ->assertJsonPath('metrics.product_listings.currently_available_in_period', 1)
            ->assertJsonPath('metrics.product_listings.currently_out_of_stock_in_period', 1)
            ->assertJsonPath('metrics.orders.created_in_period', 3)
            ->assertJsonPath('metrics.orders.in_progress_in_period', 2)
            ->assertJsonPath('metrics.orders.current_status_counts.0.status', 'Completed')
            ->assertJsonPath('metrics.orders.current_status_counts.1.status', 'Mystery')
            ->assertJsonPath('metrics.demand_requests.created_in_period', 3)
            ->assertJsonPath('metrics.demand_requests.open_now_in_period', 1)
            ->assertJsonPath('monthly.0.producer_accounts', 3)
            ->assertJsonPath('monthly.0.institutional_buyer_accounts', 1)
            ->assertJsonPath('monthly.0.product_listings_created', 4)
            ->assertJsonPath('monthly.0.orders_created', 3)
            ->assertJsonPath('monthly.0.demand_requests_created', 3)
            ->assertJsonPath('recent_activity.available', true)
            ->assertJsonPath('recent_activity.events.0.action', 'Persisted test audit event')
            ->assertJsonPath('recent_activity.events.0.status', 'failure')
            ->assertJsonPath('metrics.pending_actions.available', false);

        $this->assertEquals($before, $this->businessSnapshot());
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->user_id,
            'action' => 'GET api/admin/analytics/monitoring',
            'status' => 'success',
        ]);
    }

    public function test_supply_demand_keeps_units_separate_and_uses_current_business_rules(): void
    {
        $verifiedProducer = $this->makeProducer('supply-verified@analytics.test', 'Verified');
        $pendingProducer = $this->makeProducer('supply-pending@analytics.test', 'Pending');
        $buyer = $this->makeBuyer('supply-buyer@analytics.test');

        $kgListing = $this->makeListing($verifiedProducer, ['product_name' => 'Tomato', 'quantity_available' => 20, 'quantity' => 20, 'unit' => 'kg', 'unit_of_measure' => 'kg']);
        $crateListing = $this->makeListing($verifiedProducer, ['product_name' => 'tomato', 'quantity_available' => 3, 'quantity' => 3, 'unit' => 'Crate', 'unit_of_measure' => 'Crate']);
        $this->makeListing($pendingProducer, ['product_name' => 'Tomato', 'quantity_available' => 999, 'quantity' => 999]);
        $this->makeListing($verifiedProducer, ['product_name' => 'Tomato', 'status' => 'Paused', 'quantity_available' => 500]);
        DB::table('product_listings')->where('listing_id', $kgListing->listing_id)->update(['created_at' => '2026-09-15 12:00:00']);
        DB::table('product_listings')->where('listing_id', $crateListing->listing_id)->update(['created_at' => '2026-10-02 12:00:00']);

        $kgDemand = $this->makeDemand($buyer, ['product_name' => 'TOMATO', 'unit' => 'KG', 'quantity_needed' => 100, 'status' => 'Offers Received']);
        $crateDemand = $this->makeDemand($buyer, ['product_name' => 'Tomato', 'unit' => 'crate', 'quantity_needed' => 4, 'status' => 'open']);
        $this->makeDemand($buyer, ['product_name' => 'Tomato', 'unit' => 'kg', 'quantity_needed' => 800, 'status' => 'Active', 'deadline' => '2026-10-05']);
        $this->makeDemand($buyer, ['product_name' => 'Tomato', 'unit' => 'kg', 'quantity_needed' => 900, 'status' => 'Completed']);
        DB::table('demand_requests')->where('request_id', $kgDemand->request_id)->update(['created_at' => '2026-09-20 12:00:00']);
        DB::table('demand_requests')->where('request_id', $crateDemand->request_id)->update(['created_at' => '2026-10-03 12:00:00']);

        $admin = $this->makeUser('supply-admin@analytics.test', 'Admin');
        $before = $this->businessSnapshot();
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/analytics/supply-demand?start_date=2026-09-01&end_date=2026-10-06');
        $response->assertOk()
            ->assertJsonPath('meta.timezone', 'UTC')
            ->assertJsonPath('definitions.fulfillment', 'No fulfillment, shortage, surplus, completed sales, or price prediction is inferred.');

        $snapshot = $response->json('current_snapshot');
        $kg = collect($snapshot)->firstWhere('unit_key', 'kg');
        $crate = collect($snapshot)->firstWhere('unit_key', 'crate');
        $this->assertNotNull($kg);
        $this->assertNotNull($crate);
        $this->assertSame('tomato', $kg['product_key']);
        $this->assertSame(1, $kg['available_listing_count']);
        $this->assertEquals(20, $kg['available_supply_quantity']);
        $this->assertSame(1, $kg['active_demand_request_count']);
        $this->assertEquals(100, $kg['requested_demand_quantity']);
        $this->assertSame(1, $crate['available_listing_count']);
        $this->assertEquals(3, $crate['available_supply_quantity']);
        $this->assertSame(1, $crate['active_demand_request_count']);
        $this->assertEquals(4, $crate['requested_demand_quantity']);

        $monthly = collect($response->json('monthly_activity'));
        $this->assertSame(1, $monthly->where('month', '2026-09')->where('unit_key', 'kg')->first()['listings_created']);
        $this->assertSame(1, $monthly->where('month', '2026-09')->where('unit_key', 'kg')->first()['demand_requests_created']);
        $this->assertSame(1, $monthly->where('month', '2026-10')->where('unit_key', 'crate')->first()['listings_created']);
        $this->assertArrayNotHasKey('quantity', $monthly->first());
        $this->assertEquals($before, $this->businessSnapshot());
    }

    public function test_empty_analytics_returns_zero_counts_empty_snapshots_and_valid_months(): void
    {
        $admin = $this->makeUser('empty-admin@analytics.test', 'Admin');
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/analytics/monitoring?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()
            ->assertJsonPath('metrics.producer_accounts.created_in_period', 0)
            ->assertJsonPath('metrics.institutional_buyer_accounts.created_in_period', 0)
            ->assertJsonPath('metrics.product_listings.created_in_period', 0)
            ->assertJsonPath('metrics.orders.created_in_period', 0)
            ->assertJsonPath('metrics.demand_requests.created_in_period', 0)
            ->assertJsonPath('monthly.0.month', '2026-08')
            ->assertJsonPath('monthly.0.orders_created', 0);

        $this->getJson('/api/admin/analytics/supply-demand?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()
            ->assertJsonPath('months.0.month', '2026-08')
            ->assertJsonCount(0, 'monthly_activity')
            ->assertJsonCount(0, 'current_snapshot');
    }

    public function test_monitoring_uses_date_joined_for_legacy_users_schema(): void
    {
        Schema::table('users', fn ($table) => $table->renameColumn('created_at', 'date_joined'));

        $producer = $this->makeProducer('legacy-date-producer@analytics.test', 'Verified', '2026-10-03');
        $buyer = $this->makeBuyer('legacy-date-buyer@analytics.test', '2026-10-04');
        $admin = $this->makeUser('legacy-date-admin@analytics.test', 'Admin', '2026-10-05');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/analytics/monitoring?start_date=2026-10-01&end_date=2026-10-06')
            ->assertOk()
            ->assertJsonPath('metrics.producer_accounts.created_in_period', 1)
            ->assertJsonPath('metrics.producer_accounts.verified_in_period', 1)
            ->assertJsonPath('metrics.institutional_buyer_accounts.created_in_period', 1)
            ->assertJsonPath('monthly.0.producer_accounts', 1)
            ->assertJsonPath('monthly.0.institutional_buyer_accounts', 1);

        $this->assertSame('date_joined', User::accountCreatedAtColumn());
        $this->assertSame('Farmer', $producer->user()->value('user_type'));
        $this->assertSame('Buyer', $buyer->user()->value('user_type'));
    }

    public function test_current_demand_snapshot_uses_today_not_historical_range_end(): void
    {
        $buyer = $this->makeBuyer('historical-buyer@analytics.test');
        $pastDeadline = $this->makeDemand($buyer, [
            'product_name' => 'Past Deadline',
            'status' => 'Active',
            'deadline' => '2026-10-04',
        ]);
        $futureDeadline = $this->makeDemand($buyer, [
            'product_name' => 'Future Deadline',
            'status' => 'Active',
            'deadline' => '2026-10-20',
        ]);
        DB::table('demand_requests')->whereIn('request_id', [$pastDeadline->request_id, $futureDeadline->request_id])
            ->update(['created_at' => '2026-09-30 12:00:00']);
        $admin = $this->makeUser('historical-admin@analytics.test', 'Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/analytics/monitoring?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('current_snapshot_as_of', '2026-10-06')
            ->assertJsonPath('metrics.demand_requests.created_in_period', 2)
            ->assertJsonPath('metrics.demand_requests.open_now_in_period', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/analytics/supply-demand?start_date=2026-09-01&end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('current_snapshot_as_of', '2026-10-06');
    }

    public function test_date_filters_are_validated_and_capped_at_24_calendar_months(): void
    {
        $admin = $this->makeUser('range-admin@analytics.test', 'Admin');
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/analytics/monitoring?start_date=not-a-date&end_date=2026-10-06')->assertUnprocessable();
        $this->getJson('/api/admin/analytics/supply-demand?start_date=2024-01-01&end_date=2026-10-06')->assertUnprocessable();
        $this->getJson('/api/admin/analytics/monitoring?start_date=2026-10-07&end_date=2026-10-06')->assertUnprocessable();
        $this->getJson('/api/admin/analytics/supply-demand?unexpected=value')->assertUnprocessable();
    }
}
