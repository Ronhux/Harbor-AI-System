<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
