<?php

namespace Tests\Feature;

use App\Models\Buyer;
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
            'deadline' => '2026-09-30',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('demand_requests', [
            'buyer_id' => $buyer->buyer_id,
            'product_name' => 'Rice',
            'quantity_needed' => 2000,
            'status' => 'open',
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
                'deadline' => '2026-09-30',
            ])
            ->assertForbidden();
    }
}
