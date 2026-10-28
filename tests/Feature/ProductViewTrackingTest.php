<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductViewTrackingTest extends TestCase

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

    private function makeProducer(string $email, string $verification = 'Verified'): array
    {
        $user = $this->makeUser($email);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => $verification,
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

    private function makeBuyer(string $email): User
    {
        return $this->makeUser($email, 'Buyer');
    }

    public function test_an_eligible_buyer_view_increments_the_count(): void
    {
        $buyer = $this->makeBuyer('view-buyer@test.local');
        [, $producer] = $this->makeProducer('view-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        $this->assertSame(0, (int) $listing->fresh()->views);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertCreated();

        $this->assertSame(1, (int) $listing->fresh()->views);

        // Repeated genuine views keep accumulating on the server side.
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertCreated();

        $this->assertSame(2, (int) $listing->fresh()->views);
    }

    public function test_an_unknown_listing_is_handled_without_a_server_error(): void
    {
        $buyer = $this->makeBuyer('missing-buyer@test.local');

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/buyer/products/999999/view')
            ->assertStatus(404);
    }

    public function test_the_view_endpoint_requires_an_authenticated_buyer(): void
    {
        [, $producer] = $this->makeProducer('auth-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        $this->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertStatus(401);

        // A producer is not a buyer, so the buyer guard refuses the call.
        $producerUser = User::where('user_id', $producer->user_id)->firstOrFail();
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertStatus(403);
    }

    public function test_an_unverified_producer_listing_gains_no_views(): void
    {
        $buyer = $this->makeBuyer('unverified-buyer@test.local');
        [, $producer] = $this->makeProducer('unverified-producer@test.local', 'Pending');
        $listing = $this->makeListing($producer->producer_id);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertStatus(422);

        $this->assertSame(0, (int) $listing->fresh()->views);
    }

    public function test_a_paused_listing_gains_no_views(): void
    {
        $buyer = $this->makeBuyer('paused-buyer@test.local');
        [, $producer] = $this->makeProducer('paused-producer@test.local');
        $listing = $this->makeListing($producer->producer_id, ['status' => 'Paused']);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertStatus(422);

        $this->assertSame(0, (int) $listing->fresh()->views);
    }

    public function test_an_out_of_stock_listing_gains_no_views(): void
    {
        $buyer = $this->makeBuyer('stock-buyer@test.local');
        [, $producer] = $this->makeProducer('stock-producer@test.local');
        $listing = $this->makeListing($producer->producer_id, ['quantity_available' => 0]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertStatus(422);

        $this->assertSame(0, (int) $listing->fresh()->views);
    }

    public function test_a_producer_viewing_their_own_listing_is_not_counted(): void
    {
        [$producerUser, $producer] = $this->makeProducer('owner-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        // The producer role cannot reach the buyer endpoint at all.
        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertStatus(403);

        // A dual role account that owns the listing is treated as its owner and
        // skipped rather than counted.
        [$dualRole, $dualProducer] = $this->makeProducer('dual-role@test.local', 'Verified');
        $dualRole->update(['user_type' => 'Buyer']);

        $ownListing = $this->makeListing($dualProducer->producer_id);

        $this->actingAs($dualRole, 'sanctum')
            ->postJson("/api/buyer/products/{$ownListing->listing_id}/view")
            ->assertStatus(204);

        $this->assertSame(0, (int) $ownListing->fresh()->views);

        // The same account viewing somebody else's listing still counts.
        $otherListing = $this->makeListing($producer->producer_id);

        $this->actingAs($dualRole, 'sanctum')
            ->postJson("/api/buyer/products/{$otherListing->listing_id}/view")
            ->assertCreated();

        $this->assertSame(1, (int) $otherListing->fresh()->views);
    }

    public function test_a_client_supplied_view_count_cannot_overwrite_the_stored_count(): void
    {
        $buyer = $this->makeBuyer('tamper-buyer@test.local');
        [, $producer] = $this->makeProducer('tamper-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        // Any submitted count is ignored entirely.
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view", [
                'views' => 9999,
                'view_count' => 9999,
                'count' => 9999,
            ])
            ->assertCreated();

        $this->assertSame(1, (int) $listing->fresh()->views);

        // The listing cannot be mass assigned a count through the producer API.
        $producerUser = User::where('user_id', $producer->user_id)->firstOrFail();
        $this->actingAs($producerUser, 'sanctum')
            ->putJson("/api/producer/listings/{$listing->listing_id}", [
                'product_name' => 'Renamed Rice',
                'category' => 'Grains',
                'quantity' => 100,
                'price_per_unit' => 130,
                'unit' => 'kg',
                'views' => 5000,
            ])
            ->assertOk();

        $this->assertSame(1, (int) $listing->fresh()->views);
    }

    public function test_the_buyer_catalog_never_exposes_view_counts(): void
    {
        $buyer = $this->makeBuyer('catalog-buyer@test.local');
        [, $producer] = $this->makeProducer('catalog-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertCreated();

        $this->assertSame(1, (int) $listing->fresh()->views);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/products')
            ->assertOk()
            ->assertJsonPath('data.0.product_name', 'Premium Rice');

        $payload = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/products')
            ->json('data.0');

        // View counts are producer only and must not leak to buyers.
        $this->assertArrayNotHasKey('views', $payload);
        $this->assertArrayNotHasKey('view_count', $payload);
    }

    public function test_producer_endpoints_report_the_stored_view_count(): void
    {
        $buyer = $this->makeBuyer('report-buyer@test.local');
        [$producerUser, $producer] = $this->makeProducer('report-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertCreated();
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertCreated();

        $dashboard = collect($this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk()
            ->json('product_listings.0'));

        $this->assertSame(2, $dashboard['views']);

        $listings = collect($this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/listings')
            ->assertOk()
            ->json('data.0'));

        $this->assertSame(2, $listings['views']);

        // Order counts and the rest of the payload stay untouched by this work.
        $this->assertSame(0, $dashboard['orders']);
        $this->assertSame(120, $dashboard['current_price_per_unit']);
    }

    public function test_dashboard_total_views_sum_only_this_producers_stored_listing_counts(): void
    {
        $buyer = $this->makeBuyer('aggregate-view-buyer@test.local');
        [$producerUser, $producer] = $this->makeProducer('aggregate-view-producer@test.local');
        [, $otherProducer] = $this->makeProducer('aggregate-view-other@test.local');

        $firstListing = $this->makeListing($producer->producer_id);
        $secondListing = $this->makeListing($producer->producer_id, ['product_name' => 'Corn']);
        $otherListing = $this->makeListing($otherProducer->producer_id, ['product_name' => 'Other producer rice']);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$firstListing->listing_id}/view")
            ->assertCreated();
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$firstListing->listing_id}/view")
            ->assertCreated();
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$secondListing->listing_id}/view")
            ->assertCreated();
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$otherListing->listing_id}/view")
            ->assertCreated();

        $dashboard = $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertOk();

        $this->assertSame(3, $dashboard->json('stats.total_views'));
        $this->assertSame(
            3,
            collect($dashboard->json('product_listings'))->sum('views'),
            'The total must equal the sum of this producer\'s listing counters.'
        );
    }

    public function test_the_listing_update_response_preserves_the_view_count(): void
    {
        $buyer = $this->makeBuyer('update-buyer@test.local');
        [$producerUser, $producer] = $this->makeProducer('update-producer@test.local');
        $listing = $this->makeListing($producer->producer_id);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/buyer/products/{$listing->listing_id}/view")
            ->assertCreated();

        // My Listings merges this response into its local list, so the count
        // must survive an edit instead of resetting to zero.
        $updated = $this->actingAs($producerUser, 'sanctum')
            ->putJson("/api/producer/listings/{$listing->listing_id}", [
                'product_name' => 'Renamed Rice',
                'category' => 'Grains',
                'quantity' => 100,
                'price_per_unit' => 130,
                'unit' => 'kg',
            ])
            ->assertOk()
            ->json('listing');

        $this->assertSame(1, $updated['views']);
    }
}
