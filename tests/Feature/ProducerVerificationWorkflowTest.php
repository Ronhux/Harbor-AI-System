<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProducerVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $syntheticSequence = 0;

    /**
     * Synthetic user factory.
     *
     * `User::booted()` mirrors `contact_number` into `login_number`, and the
     * `users_login_number_unique` index rejects a second user sharing the same
     * number. Every default below is therefore unique per call, so a test that
     * creates more than one user no longer collides. Tests that care about a
     * specific number still override `contact_number` through $values.
     */
    private function user(string $email, string $type, array $values = []): User
    {
        return User::create(array_merge([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'contact_number' => '0917999' . str_pad((string) ++$this->syntheticSequence, 4, '0', STR_PAD_LEFT),
            'user_type' => $type,
        ], $values));
    }

    public function test_admin_can_list_and_update_pending_producer_verification(): void
    {
        $admin = $this->user('admin@producer-review.test', 'Admin', ['first_name' => 'Admin', 'last_name' => 'User']);
        $pendingUser = $this->user('pending@producer-review.test', 'Farmer', ['first_name' => 'Pending', 'last_name' => 'Farmer']);
        $producer = Producer::create([
            'user_id' => $pendingUser->user_id,
            'rsbsa_number' => 'RSBSA-1001',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);
        $lowercasePendingUser = $this->user('lowercase-pending@producer-review.test', 'Farmer');
        $lowercasePending = Producer::create([
            'user_id' => $lowercasePendingUser->user_id,
            'rsbsa_number' => null,
            'verification_status' => 'pending',
            'producer_type' => 'Farmer',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/producers/pending')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.producer_id', $producer->producer_id)
            ->assertJsonPath('data.0.verification_status', 'Pending')
            ->assertJsonPath('data.1.producer_id', $lowercasePending->producer_id);

        if (in_array($producer->rsbsa_number, ['RSBSA-1001','RSBSA-2004'], true)) {
            \App\Models\RegisteredProducer::firstOrCreate(['rsbsa_number'=>$producer->rsbsa_number],['first_name'=>$producer->user->first_name,'last_name'=>$producer->user->last_name]);
        }
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producer->producer_id . '/verification', [
                'verification_status' => 'Verified',
            ])
            ->assertOk()
            ->assertJsonPath('producer.verification_status', 'Verified');

        $this->assertSame('Verified', Producer::find($producer->producer_id)->verification_status);
    }

    public function test_pending_producer_cannot_create_listings_until_verified(): void
    {
        $producerUser = $this->user('provisional@producer-review.test', 'Farmer', ['first_name' => 'Pending', 'last_name' => 'Producer']);
        Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-2002',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/listings', [
                'product_name' => 'Rice',
                'category' => 'Grains',
                'quantity' => 50,
                'unit' => 'kg',
                'price_per_unit' => 40,
                'description' => 'Review pending listing',
            ])
            ->assertForbidden();
    }

    public function test_pending_producer_cannot_access_dashboard_or_listing_views_before_approval(): void
    {
        $producerUser = $this->user('pending-dashboard@producer-review.test', 'Farmer', ['first_name' => 'Pending', 'last_name' => 'Dashboard']);
        Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-2002A',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);

        $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/dashboard')
            ->assertForbidden()
            ->assertJsonPath('verification_required', true);

        $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/listings')
            ->assertForbidden()
            ->assertJsonPath('verification_required', true);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/product-assistant/analyze', ['product_name' => 'Rice'])
            ->assertForbidden()
            ->assertJsonPath('verification_required', true);

        $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/demand-opportunities')
            ->assertForbidden()
            ->assertJsonPath('verification_required', true);
    }

    public function test_rejected_producer_cannot_create_listings_until_reapproved(): void
    {
        $producerUser = $this->user('rejected@producer-review.test', 'Farmer', ['first_name' => 'Rejected', 'last_name' => 'Producer']);
        Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-2003',
            'location' => 'Aparri',
            'producer_type' => 'Fisherfolk',
            'verification_status' => 'Rejected',
        ]);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/listings', [
                'product_name' => 'Tilapia',
                'category' => 'Fish',
                'quantity' => 25,
                'unit' => 'kg',
                'price_per_unit' => 55,
                'description' => 'Rejected listing attempt',
            ])
            ->assertForbidden();
    }

    public function test_approved_producer_can_create_a_listing_and_appear_in_buyer_browse_pages(): void
    {
        $producerUser = $this->user('verified@producer-review.test', 'Farmer', ['first_name' => 'Verified', 'last_name' => 'Producer']);
        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-2004',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);

        $admin = $this->user('approve-before-browse@producer-review.test', 'Admin');
        if (in_array($producer->rsbsa_number, ['RSBSA-1001','RSBSA-2004'], true)) {
            \App\Models\RegisteredProducer::firstOrCreate(['rsbsa_number'=>$producer->rsbsa_number],['first_name'=>$producer->user->first_name,'last_name'=>$producer->user->last_name]);
        }
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producer->producer_id . '/verification', [
                'verification_status' => 'Verified',
            ])
            ->assertOk();

        $this->actingAs($producerUser, 'sanctum')
            ->postJson('/api/producer/listings', [
                'product_name' => 'Mango',
                'category' => 'Fruit',
                'quantity' => 80,
                'unit' => 'kg',
                'price_per_unit' => 35,
                'description' => 'Approved listing',
            ])
            ->assertCreated()
            ->assertJsonPath('listing.product_name', 'Mango');

        $this->assertDatabaseHas('product_listings', [
            'producer_id' => $producer->producer_id,
            'product_name' => 'Mango',
        ]);

        $buyerUser = $this->user('browse-approved@producer-review.test', 'Buyer');
        Buyer::create([
            'user_id' => $buyerUser->user_id,
            'organization_name' => 'Test Buyer',
            'buyer_type' => 'Institution',
        ]);
        $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/buyer/producers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Verified Producer']);
        $this->actingAs($buyerUser, 'sanctum')
            ->getJson('/api/buyer/products')
            ->assertOk()
            ->assertJsonPath('data.0.producer.name', 'Verified Producer');
    }

    public function test_non_admin_users_cannot_access_or_change_pending_review_queue(): void
    {
        $admin = $this->user('admin@producer-review.test', 'Admin', ['first_name' => 'Admin', 'last_name' => 'User']);
        $user = $this->user('regular@producer-review.test', 'Farmer', ['first_name' => 'Regular', 'last_name' => 'User']);
        $producer = Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => 'RSBSA-2005',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/producers/pending')->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson('/api/admin/producers/' . $producer->producer_id . '/verification', ['verification_status' => 'Verified'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/producers/pending')->assertOk();
    }

    public function test_invalid_verification_status_is_rejected_without_changing_existing_status(): void
    {
        $admin = $this->user('admin-invalid@producer-review.test', 'Admin', ['first_name' => 'Admin', 'last_name' => 'Invalid']);
        $producerUser = $this->user('invalid-status@producer-review.test', 'Farmer', ['first_name' => 'Invalid', 'last_name' => 'Producer']);
        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-2006',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);

        if (in_array($producer->rsbsa_number, ['RSBSA-1001','RSBSA-2004'], true)) {
            \App\Models\RegisteredProducer::firstOrCreate(['rsbsa_number'=>$producer->rsbsa_number],['first_name'=>$producer->user->first_name,'last_name'=>$producer->user->last_name]);
        }
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producer->producer_id . '/verification', [
                'verification_status' => 'Suspended',
            ])
            ->assertStatus(422);

        $this->assertSame('Pending', Producer::find($producer->producer_id)->verification_status);
    }

    public function test_admin_verification_changes_create_audit_event(): void
    {
        $admin = $this->user('audit-admin@producer-review.test', 'Admin', ['first_name' => 'Audit', 'last_name' => 'Admin']);
        $producerUser = $this->user('audit-producer@producer-review.test', 'Farmer', ['first_name' => 'Audit', 'last_name' => 'Producer']);
        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => 'RSBSA-2007',
            'location' => 'Aparri',
            'producer_type' => 'Farmer',
            'verification_status' => 'Pending',
        ]);

        if (in_array($producer->rsbsa_number, ['RSBSA-1001','RSBSA-2004'], true)) {
            \App\Models\RegisteredProducer::firstOrCreate(['rsbsa_number'=>$producer->rsbsa_number],['first_name'=>$producer->user->first_name,'last_name'=>$producer->user->last_name]);
        }
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producer->producer_id . '/verification', [
                'verification_status' => 'Rejected',
                'reason' => 'Synthetic identity information requires clarification.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->user_id,
            'action' => 'Rejected producer verification',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'subject' => 'producer_id=' . $producer->producer_id,
        ]);
    }
}
