<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_buyer_can_post_and_fetch_demand_notices(): void
    {
        $user = User::create([
            'email' => 'institution@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Institutional',
            'last_name' => 'Buyer',
            'contact_number' => '09181234567',
            'user_type' => 'Buyer',
        ]);

        $buyer = Buyer::create([
            'user_id' => $user->user_id,
            'organization_name' => 'Institution',
            'buyer_type' => 'Institution',
            'verification_status' => 'Verified',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/buyer/demands', [
            'product_name' => 'Rice',
            'category' => 'Grains',
            'quantity_needed' => 2000,
            'unit' => 'kg',
            'max_price_per_unit' => 120,
            'description' => 'Good quality rice required.',
            'deadline' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('demand_requests', [
            'buyer_id' => $buyer->buyer_id,
            'product_name' => 'Rice',
            'quantity_needed' => 2000,
            'status' => 'Active',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/buyer/demands')
            ->assertOk()
            ->assertJsonPath('data.0.product_name', 'Rice');
    }

    public function test_unverified_buyer_cannot_post_demand_notices(): void
    {
        $user = User::create([
            'email' => 'pending@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Pending',
            'last_name' => 'Buyer',
            'contact_number' => '09181234567',
            'user_type' => 'Buyer',
        ]);

        Buyer::create([
            'user_id' => $user->user_id,
            'organization_name' => 'Pending Institution',
            'buyer_type' => 'Institution',
            'verification_status' => 'Pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/buyer/demands', [
                'product_name' => 'Rice',
                'quantity_needed' => 100,
                'unit' => 'kg',
                'deadline' => now()->addDays(10)->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_posted_demand_is_returned_to_its_buyer_and_hidden_pending_review(): void
    {
        $buyerUser = User::create([
            'email' => 'verified-demand@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Verified',
            'last_name' => 'Buyer',
            'user_type' => 'Buyer',
        ]);
        $buyer = Buyer::create([
            'user_id' => $buyerUser->user_id,
            'organization_name' => 'North Harbor Clinic',
            'buyer_type' => 'Institution',
            'verification_status' => 'Verified',
        ]);
        $producerUser = User::create([
            'email' => 'producer-demand@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Local',
            'last_name' => 'Producer',
            'user_type' => 'Farmer',
        ]);
        Producer::create([
            'user_id' => $producerUser->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $created = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/demands', [
            'product_name' => 'Eggplant',
            'category' => 'Vegetables',
            'quantity_needed' => 50,
            'unit' => 'kg',
            'deadline' => now()->addDays(7)->toDateString(),
            'preferred_delivery_date' => now()->addDays(14)->toDateString(),
            'partial_fulfillment' => true,
        ])->assertCreated()
            ->assertJsonPath('data.product_name', 'Eggplant')
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.buyer_id', $buyer->buyer_id);

        $this->actingAs($buyerUser, 'sanctum')->getJson('/api/buyer/demands')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $created->json('data.id'));

        $this->actingAs($producerUser, 'sanctum')->getJson('/api/producer/demand-opportunities')
            ->assertOk()->assertJsonCount(0, 'data');

        $otherUser = User::create([
            'email' => 'other-buyer@example.com',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Other',
            'last_name' => 'Buyer',
            'user_type' => 'Buyer',
        ]);
        Buyer::create([
            'user_id' => $otherUser->user_id,
            'organization_name' => 'Other Institution',
            'buyer_type' => 'Institution',
            'verification_status' => 'Verified',
        ]);
        $this->actingAs($otherUser, 'sanctum')->getJson('/api/buyer/demands')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_posted_demand_returns_the_saved_record_with_fractional_quantities(): void
    {
        $buyerUser = User::create(['email' => 'fractional-demand@example.com', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Fractional', 'last_name' => 'Buyer', 'user_type' => 'Buyer']);
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Fractional Institution', 'verification_status' => 'Verified']);

        $created = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/demands', [
            'product_name' => 'Eggplant',
            'category' => 'Vegetables',
            'quantity_needed' => 12.5,
            'unit' => 'kg',
            'deadline' => now()->addDays(7)->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.product_name', 'Eggplant')
            ->assertJsonPath('data.buyer_id', $buyer->buyer_id)
            ->assertJsonPath('data.status', 'Active');

        $id = $created->json('data.id');
        $this->assertNotEmpty($id);

        // The response must describe the row that was actually written.
        $listed = $this->actingAs($buyerUser, 'sanctum')->getJson('/api/buyer/demands')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.quantity_needed', 12.5);

        $this->assertSame(12.5, (float) $listed->json('data.0.quantity_needed'));
        $this->assertDatabaseHas('demand_requests', ['request_id' => $id, 'buyer_id' => $buyer->buyer_id, 'status' => 'Active']);
        $this->assertSame(12.5, (float) \App\Models\DemandRequest::findOrFail($id)->quantity_needed);
    }

    public function test_invalid_demand_submission_returns_validation_errors_without_saving(): void
    {
        $buyerUser = User::create(['email' => 'invalid-demand@example.com', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Invalid', 'last_name' => 'Buyer', 'user_type' => 'Buyer']);
        Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Invalid Institution', 'verification_status' => 'Verified']);

        $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/demands', [
            'product_name' => '',
            'quantity_needed' => 0,
            'unit' => '',
            'deadline' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['product_name', 'quantity_needed', 'unit', 'deadline']);

        $this->assertDatabaseCount('demand_requests', 0);
    }

    public function test_repeated_submissions_create_one_record_per_request(): void
    {
        $buyerUser = User::create(['email' => 'repeat-demand@example.com', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Repeat', 'last_name' => 'Buyer', 'user_type' => 'Buyer']);
        Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Repeat Institution', 'verification_status' => 'Verified']);

        $payload = ['product_name' => 'Pineapple', 'quantity_needed' => 30, 'unit' => 'pcs', 'deadline' => now()->addDays(5)->toDateString()];

        // The interface blocks the second click, so only one request is sent.
        $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/demands', $payload)->assertCreated();
        $this->actingAs($buyerUser, 'sanctum')->getJson('/api/buyer/demands')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('demand_requests', 1);
    }
}
