<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProducerListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_dashboard_returns_product_listings(): void
    {
        $user = User::create([
            'email' => 'farmer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Ana',
            'last_name' => 'Dela Cruz',
            'contact_number' => '09171234567',
            'user_type' => 'Farmer',
        ]);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-001',
            'verification_status' => 'Verified',
            'producer_type' => 'Rice Farmer',
        ]);

        ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Premium Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 120,
            'unit_of_measure' => 'kg',
            'quantity_available' => 300,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/producer/dashboard');

        $response->assertOk();
        $response->assertJsonPath('producer.user_id', $user->user_id);
        $response->assertJsonPath('product_listings.0.product_name', 'Premium Rice');
    }

    public function test_producer_can_list_their_own_listings_with_images(): void
    {
        $user = User::create([
            'email' => 'listfarmer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Dina',
            'last_name' => 'Lopez',
            'contact_number' => '09189998888',
            'user_type' => 'Farmer',
        ]);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-005',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $other = Producer::create([
            'user_id' => User::create([
                'email' => 'otherfarmer@example.com',
                'password_hash' => bcrypt('secret123'),
                'first_name' => 'Other',
                'last_name' => 'Farmer',
                'contact_number' => '09189997777',
                'user_type' => 'Farmer',
            ])->user_id,
            'rsbsa_number' => 'RSBSA-006',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $mine = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Native Mango',
            'product_category' => 'Fruits',
            'current_price_per_unit' => 65,
            'unit_of_measure' => 'kg',
            'quantity_available' => 80,
            'status' => 'Active',
        ]);
        $mine->images()->create([
            'path' => UploadedFile::fake()->createWithContent('mango.png', 'fake-png')->store('product_images', 'public'),
            'is_primary' => true,
        ]);

        ProductListing::create([
            'producer_id' => $other->producer_id,
            'product_name' => 'Not Mine',
            'product_category' => 'Grains',
            'current_price_per_unit' => 50,
            'unit_of_measure' => 'kg',
            'quantity_available' => 10,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/producer/listings');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Native Mango')
            ->assertJsonPath('data.0.listing_id', $mine->listing_id)
            ->assertJsonPath('data.0.images.0.is_primary', true);

        $this->assertStringContainsString('/storage/product_images/', $response->json('data.0.images.0.url'));
        $this->assertStringNotContainsString('Not Mine', $response->getContent());
        $this->assertDatabaseHas('product_listings', ['producer_id' => $other->producer_id, 'product_name' => 'Not Mine']);
    }

    public function test_producer_listings_preserves_fractional_quantities(): void
    {
        $user = User::create([
            'email' => 'fractionfarmer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Elena',
            'last_name' => 'Reyes',
            'contact_number' => '09189996666',
            'user_type' => 'Farmer',
        ]);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-007',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Partial Harvest Mango',
            'product_category' => 'Fruits',
            'current_price_per_unit' => 72.25,
            'unit_of_measure' => 'kg',
            'quantity_available' => 40.5,
            'status' => 'Active',
        ]);

        $this->assertSame(40.5, (float) $listing->fresh()->quantity_available);

        $this->actingAs($user, 'sanctum')->getJson('/api/producer/listings')
            ->assertOk()
            ->assertJsonPath('data.0.quantity_available', 40.5);
    }

    public function test_producer_listings_requires_authentication(): void
    {
        $this->getJson('/api/producer/listings')->assertStatus(401);
    }

    public function test_producer_can_create_listing(): void
    {
        $user = User::create([
            'email' => 'newfarmer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Berto',
            'last_name' => 'Santos',
            'contact_number' => '09181234567',
            'user_type' => 'Farmer',
        ]);

        Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-002',
            'verification_status' => 'Verified',
            'producer_type' => 'Fish Farmer',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/producer/listings', [
            'product_name' => 'Fresh Tilapia',
            'category' => 'Fish',
            'quantity' => 150,
            'unit' => 'kg',
            'price_per_unit' => 180,
            'description' => 'Fresh tilapia from local farms.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_listings', [
            'product_name' => 'Fresh Tilapia',
            'product_category' => 'Fish',
            'quantity_available' => 150,
            'unit_of_measure' => 'kg',
        ]);
    }

    public function test_producer_can_update_listing_values(): void
    {
        $user = User::create([
            'email' => 'updatefarmer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Carla',
            'last_name' => 'Ramos',
            'contact_number' => '09184567890',
            'user_type' => 'Farmer',
        ]);

        $producer = Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-003',
            'verification_status' => 'Verified',
            'producer_type' => 'Rice Farmer',
        ]);

        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Old Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 100,
            'unit_of_measure' => 'kg',
            'quantity_available' => 120,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/producer/listings/' . $listing->listing_id, [
            'product_name' => 'Updated Rice',
            'category' => 'Grains',
            'quantity' => 200,
            'unit' => 'kg',
            'price_per_unit' => 150,
            'description' => 'Updated farm stock.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('listing.product_name', 'Updated Rice');
        $this->assertDatabaseHas('product_listings', [
            'listing_id' => $listing->listing_id,
            'product_name' => 'Updated Rice',
            'quantity_available' => 200,
            'current_price_per_unit' => '150.00',
        ]);
    }

    public function test_producer_cannot_delete_another_producers_listing(): void
    {
        Storage::fake('public');

        $ownerUser = User::create([
            'email' => 'delete-owner@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Owner',
            'user_type' => 'Farmer',
        ]);
        $owner = Producer::create([
            'user_id' => $ownerUser->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $otherUser = User::create([
            'email' => 'delete-other@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Other',
            'user_type' => 'Farmer',
        ]);
        $other = Producer::create([
            'user_id' => $otherUser->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $ownedListing = ProductListing::create([
            'producer_id' => $owner->producer_id,
            'product_name' => 'Owner Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 50,
            'unit_of_measure' => 'kg',
            'quantity_available' => 10,
            'status' => 'Active',
        ]);
        $ownedImagePath = 'product_images/owner-rice.png';
        Storage::disk('public')->put($ownedImagePath, 'image bytes');
        $ownedListing->images()->create([
            'path' => $ownedImagePath,
            'is_primary' => true,
        ]);
        $otherListing = ProductListing::create([
            'producer_id' => $other->producer_id,
            'product_name' => 'Other Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 60,
            'unit_of_measure' => 'kg',
            'quantity_available' => 10,
            'status' => 'Active',
        ]);

        $this->actingAs($ownerUser, 'sanctum')
            ->deleteJson("/api/producer/listings/{$otherListing->listing_id}")
            ->assertNotFound();

        $this->assertDatabaseHas('product_listings', ['listing_id' => $otherListing->listing_id]);

        $this->deleteJson("/api/producer/listings/{$ownedListing->listing_id}")
            ->assertOk();

        $this->assertDatabaseMissing('product_listings', ['listing_id' => $ownedListing->listing_id]);
        Storage::disk('public')->assertMissing($ownedImagePath);
    }

    public function test_listing_with_order_history_cannot_be_deleted_or_have_its_images_removed(): void
    {
        Storage::fake('public');

        $producerUser = User::create([
            'email' => 'ordered-listing-producer@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Producer',
            'user_type' => 'Farmer',
        ]);
        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);
        $buyerUser = User::create([
            'email' => 'ordered-listing-buyer@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Buyer',
            'user_type' => 'Buyer',
        ]);
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id]);
        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Ordered Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 50,
            'unit_of_measure' => 'kg',
            'quantity_available' => 10,
            'status' => 'Active',
        ]);
        $imagePath = 'product_images/ordered-rice.png';
        Storage::disk('public')->put($imagePath, 'image bytes');
        $image = $listing->images()->create([
            'path' => $imagePath,
            'is_primary' => true,
        ]);
        $order = Order::create([
            'buyer_id' => $buyer->buyer_id,
            'order_date' => now()->toDateString(),
            'total_amount' => 50,
            'fulfillment_status' => 'Order Submitted',
            'payment_status' => 'Pending',
        ]);
        $orderDetail = OrderDetail::create([
            'order_id' => $order->order_id,
            'listing_id' => $listing->listing_id,
            'quantity' => 1,
            'unit_price' => 50,
            'subtotal' => 50,
        ]);

        $this->actingAs($producerUser, 'sanctum')
            ->deleteJson("/api/producer/listings/{$listing->listing_id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This listing is linked to existing orders and cannot be deleted. Transaction history must be preserved.');

        $this->assertDatabaseHas('product_listings', ['listing_id' => $listing->listing_id]);
        $this->assertDatabaseHas('order_details', ['order_detail_id' => $orderDetail->order_detail_id]);
        $this->assertDatabaseHas('product_listing_images', ['id' => $image->id]);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_buyer_can_fetch_producers_with_products(): void
    {
        $buyer = User::create([
            'email' => 'buyer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Buyer',
            'last_name' => 'Account',
            'contact_number' => '09181234567',
            'user_type' => 'Buyer',
        ]);

        $producerUser = User::create([
            'email' => 'producer2@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Ariel',
            'last_name' => 'Farm',
            'contact_number' => '09181111111',
            'user_type' => 'Farmer',
        ]);

        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-004',
            'location' => 'Aparri, Cagayan',
            'primary_product_type' => 'Seafood',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 120,
            'unit_of_measure' => 'kg',
            'quantity_available' => 300,
            'status' => 'Active',
        ]);

        ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Tilapia',
            'product_category' => 'Fish',
            'current_price_per_unit' => 180,
            'unit_of_measure' => 'kg',
            'quantity_available' => 150,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/buyer/producers');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Ariel Farm');
        $response->assertJsonFragment(['product_name' => 'Rice']);
        $response->assertJsonFragment(['product_name' => 'Tilapia']);
    }
}
