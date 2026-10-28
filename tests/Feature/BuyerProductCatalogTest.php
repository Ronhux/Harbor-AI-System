<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the buyer product catalog contract.
 *
 * Buyer search and filters run in the browser over this payload, so every
 * field the controls compare against has to keep arriving. Removing one of
 * them here would silently break a filter rather than fail loudly.
 */
class BuyerProductCatalogTest extends TestCase

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
            'first_name' => 'Lina',
            'last_name' => 'Seller',
            'contact_number' => self::uniqueMobile(),
            'user_type' => $type,
        ]);
    }

    private function makeProducer(string $email, string $verification = 'Verified'): Producer
    {
        $user = $this->makeUser($email);

        return Producer::create([
            'user_id' => $user->user_id,
            'location' => 'Aparri',
            'verification_status' => $verification,
            'producer_type' => 'Farmer',
        ]);
    }

    private function makeListing(int $producerId, array $overrides = []): ProductListing
    {
        return ProductListing::create(array_merge([
            'producer_id' => $producerId,
            'product_name' => 'Sweet Corn',
            'product_category' => 'Grains',
            'current_price_per_unit' => 65,
            'unit_of_measure' => 'kg',
            'quantity_available' => 25,
            'status' => 'Active',
        ], $overrides));
    }

    public function test_the_catalog_exposes_every_field_the_buyer_filters_compare(): void
    {
        $buyer = $this->makeUser('catalog-buyer@test.local', 'Buyer');
        $producer = $this->makeProducer('catalog-producer@test.local');

        $this->makeListing($producer->producer_id, [
            'description' => 'Freshly harvested sweet corn',
            'location' => 'Piat',
            'harvest_date' => '2026-09-01',
        ]);

        $product = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        // Text search reads these.
        $this->assertSame('Sweet Corn', $product['product_name']);
        $this->assertSame('Freshly harvested sweet corn', $product['description']);
        $this->assertSame('Piat', $product['location']);
        $this->assertSame('Lina Seller', $product['producer']['name']);
        $this->assertSame('Aparri', $product['producer']['location']);

        // Category chips, price and unit filters, quantity and sorting read these.
        $this->assertNotEmpty($product['product_category']);
        $this->assertEqualsWithDelta(65, $product['current_price_per_unit'], 0.001);
        $this->assertEqualsWithDelta(25, $product['quantity_available'], 0.001);
        $this->assertSame('kg', $product['unit_of_measure']);
        $this->assertSame('Active', $product['status']);
        $this->assertArrayHasKey('created_at', $product);

        // Private producer fields stay private.
        $this->assertArrayNotHasKey('email', $product['producer']);
    }

    public function test_a_listing_without_a_category_still_reports_a_usable_label(): void
    {
        $buyer = $this->makeUser('blank-category-buyer@test.local', 'Buyer');
        $producer = $this->makeProducer('blank-category-producer@test.local');

        // A legacy row with no category at either column.
        $this->makeListing($producer->producer_id, [
            'product_name' => 'Uncategorised Goods',
            'product_category' => null,
            'category' => null,
        ]);

        $product = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/products')
            ->assertOk()
            ->json('data.0');

        // The browser groups categories by a trimmed label, so it must never be blank.
        $this->assertNotEmpty($product['product_category']);
        $this->assertSame($product['product_category'], $product['category']);
    }

    public function test_the_server_side_availability_rules_are_preserved(): void
    {
        $buyer = $this->makeUser('rules-buyer@test.local', 'Buyer');
        $verified = $this->makeProducer('rules-verified@test.local', 'Verified');
        $unverified = $this->makeProducer('rules-unverified@test.local', 'Pending');

        $this->makeListing($verified->producer_id, ['product_name' => 'Visible Corn']);
        $this->makeListing($verified->producer_id, ['product_name' => 'Sold Out Corn', 'quantity_available' => 0, 'status' => 'Sold']);
        $this->makeListing($verified->producer_id, ['product_name' => 'Paused Corn', 'status' => 'Paused']);
        $this->makeListing($unverified->producer_id, ['product_name' => 'Unverified Corn']);

        $names = collect($this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/products')
            ->assertOk()
            ->json('data'))
            ->pluck('product_name')
            ->all();

        // Filters can only narrow this list, never widen it.
        $this->assertSame(['Visible Corn'], $names);
    }

    public function test_producer_directory_only_exposes_available_listings_and_does_not_invent_metrics(): void
    {
        $buyer = $this->makeUser('producer-directory-buyer@test.local', 'Buyer');
        $producer = $this->makeProducer('producer-directory@test.local');
        $this->makeListing($producer->producer_id, ['product_name' => 'Available Corn']);
        $this->makeListing($producer->producer_id, ['product_name' => 'Sold Out Corn', 'quantity_available' => 0, 'status' => 'Sold']);
        $this->makeListing($producer->producer_id, ['product_name' => 'Paused Corn', 'status' => 'Paused']);

        $producerData = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/producers')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.product_listings')
            ->assertJsonPath('data.0.active_listing_count', 1)
            ->json('data.0');

        $this->assertSame(['Available Corn'], $producerData['products']);
        $this->assertSame('Available Corn', $producerData['product_listings'][0]['product_name']);
        $this->assertArrayNotHasKey('price', $producerData);
        $this->assertArrayNotHasKey('rating', $producerData);
        $this->assertArrayNotHasKey('rsbsa_number', $producerData);
        $this->assertArrayNotHasKey('orders', $producerData);
    }

    public function test_approved_producers_are_visible_when_legacy_status_uses_lowercase(): void
    {
        $buyer = $this->makeUser('lowercase-status-buyer@test.local', 'Buyer');
        $producer = $this->makeProducer('lowercase-status-producer@test.local', 'verified');
        $this->makeListing($producer->producer_id, ['product_name' => 'Case-Safe Rice']);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/producers')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Lina Seller');

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/products')
            ->assertOk()
            ->assertJsonPath('data.0.product_name', 'Case-Safe Rice')
            ->assertJsonPath('data.0.producer.name', 'Lina Seller');
    }
    public function test_producer_directory_and_buyer_order_actions_refuse_other_roles(): void
    {
        foreach (['Admin', 'Super Admin', 'Farmer'] as $index => $role) {
            $user = $this->makeUser('wrong-buyer-role-'.$index.'@example.test', $role);
            $this->actingAs($user, 'sanctum')->getJson('/api/buyer/producers')->assertForbidden();
            $this->getJson('/api/buyer/orders')->assertForbidden();
            $this->postJson('/api/orders', [])->assertForbidden();
        }
    }
}
