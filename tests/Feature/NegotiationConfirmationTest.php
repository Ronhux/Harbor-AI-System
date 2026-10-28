<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Negotiation;
use App\Models\Order;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the confirmation feedback the interface relies on: a saved confirmation
 * must be reported back to the requesting party, must not leave the negotiation
 * labelled "Revision Pending", and must never create a second order.
 */
class NegotiationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function actors(string $suffix): array
    {
        $buyerUser = User::create(['email' => "buyer-$suffix@example.test", 'password_hash' => bcrypt('secret123'), 'first_name' => 'Buyer', 'last_name' => 'Confirm', 'user_type' => 'Buyer']);
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Buyer Org', 'verification_status' => 'Verified']);
        $producerUser = User::create(['email' => "producer-$suffix@example.test", 'password_hash' => bcrypt('secret123'), 'first_name' => 'Producer', 'last_name' => 'Confirm', 'user_type' => 'Farmer']);
        $producer = Producer::create(['user_id' => $producerUser->user_id, 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        $listing = ProductListing::create(['producer_id' => $producer->producer_id, 'product_name' => 'Fresh Corn', 'category' => 'Grains', 'product_category' => 'Grains', 'quantity' => 100, 'quantity_available' => 100, 'unit' => 'kg', 'unit_of_measure' => 'kg', 'price_per_unit' => 50, 'current_price_per_unit' => 50, 'status' => 'Active']);

        return [$buyerUser, $buyer, $producerUser, $producer, $listing];
    }

    public function test_producer_confirmation_saves_the_terms_and_creates_one_order(): void
    {
        [$buyerUser, , $producerUser, , $listing] = $this->actors('producer-confirm');
        $id = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])->assertCreated()->json('data.id');

        $response = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed')
            ->assertJsonPath('data.status_label', 'Approved')
            ->assertJsonPath('data.confirmation.mutually_approved', true)
            ->assertJsonPath('data.confirmation.you_confirmed', true)
            ->assertJsonPath('data.confirmation.your_action_required', false)
            ->assertJsonPath('data.awaiting', null);

        $this->assertNotNull($response->json('order.order_id'));
        $this->assertNotNull(\App\Models\NegotiationVersion::where('negotiation_id', $id)->whereNotNull('producer_confirmed_at')->first());
        $this->assertDatabaseHas('orders', ['negotiation_id' => $id, 'fulfillment_status' => 'Order Submitted']);
        $this->assertDatabaseHas('orders', ['negotiation_id' => $id, 'total_amount' => 900]);
    }

    public function test_buyer_confirmation_of_a_revision_reports_the_saved_confirmation(): void
    {
        [$buyerUser, , $producerUser, , $listing] = $this->actors('buyer-confirm');
        $id = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])->assertCreated()->json('data.id');

        // The producer counters, so version 2 waits on the buyer.
        $revision = $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'revise', 'version' => 1, 'unit_price' => 47, 'quantity' => 20])
            ->assertOk()
            ->assertJsonPath('data.current_version', 2)
            ->assertJsonPath('data.status_label', 'Awaiting Buyer Confirmation')
            ->assertJsonPath('data.awaiting', 'buyer')
            ->assertJsonPath('data.confirmation.you_confirmed', true)
            ->assertJsonPath('data.confirmation.your_action_required', false);

        $this->assertNotEmpty($revision->json('data.confirmation.producer_confirmed_at'));

        $this->actingAs($buyerUser, 'sanctum')
            ->getJson("/api/buyer/negotiations/$id")
            ->assertOk()
            ->assertJsonPath('data.status_label', 'Awaiting Buyer Confirmation')
            ->assertJsonPath('data.confirmation.you_confirmed', false)
            ->assertJsonPath('data.confirmation.other_party_confirmed', true)
            ->assertJsonPath('data.confirmation.your_action_required', true);

        $confirmed = $this->actingAs($buyerUser, 'sanctum')
            ->postJson("/api/buyer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed')
            ->assertJsonPath('data.status_label', 'Approved')
            ->assertJsonPath('data.confirmation.you_confirmed', true)
            ->assertJsonPath('data.confirmation.your_action_required', false);

        $this->assertNotEmpty($confirmed->json('data.confirmation.buyer_confirmed_at'));
        $this->assertDatabaseHas('orders', ['negotiation_id' => $id, 'total_amount' => 940]);
        $this->assertDatabaseCount('orders', 1);

        // A repeat confirmation of the same version is a no-op, not a second order.
        $this->actingAs($buyerUser, 'sanctum')
            ->postJson("/api/buyer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_repeated_confirmation_repairs_a_stale_revision_pending_status(): void
    {
        [$buyerUser, , $producerUser, , $listing] = $this->actors('stale-status');
        $id = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])->assertCreated()->json('data.id');

        // Simulate a run that saved both confirmations but never reached the status
        // update because the order creation failed.
        Negotiation::whereKey($id)->update(['status' => 'Revised Terms Submitted', 'agreement_snapshot' => null]);
        \App\Models\NegotiationVersion::where('negotiation_id', $id)->update([
            'buyer_confirmed_at' => now(),
            'producer_confirmed_at' => now(),
        ]);

        $this->assertDatabaseHas('negotiations', ['id' => $id, 'status' => 'Revised Terms Submitted']);
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed')
            ->assertJsonPath('data.status_label', 'Approved');

        $this->assertDatabaseHas('negotiations', ['id' => $id, 'status' => 'Agreement Confirmed']);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_a_dual_role_account_confirms_as_a_buyer_not_a_producer(): void
    {
        [$buyerUser, , $producerUser, , $listing] = $this->actors('dual-role');

        // The same login also owns a producer profile, which must not change how
        // it acts on a negotiation it holds as the buyer.
        $ownProducer = Producer::create([
            'user_id' => $buyerUser->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $id = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])->assertCreated()->json('data.id');
        $this->actingAs($producerUser, 'sanctum')->postJson("/api/producer/negotiations/$id/respond", ['action' => 'revise', 'version' => 1, 'unit_price' => 47, 'quantity' => 20])->assertOk();

        $response = $this->actingAs($buyerUser, 'sanctum')
            ->postJson("/api/buyer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 2]);

        // It must not be answered as "already confirmed" while still unconfirmed.
        $response->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed')
            ->assertJsonPath('data.status_label', 'Approved')
            ->assertJsonPath('data.confirmation.you_confirmed', true)
            ->assertJsonPath('data.confirmation.mutually_approved', true);

        $this->assertNotSame('This proposal version was already confirmed. Current status has been loaded.', $response->json('message'));
        $this->assertNotNull($response->json('order.order_id'));
        $this->assertNotNull(\App\Models\NegotiationVersion::where('negotiation_id', $id)->where('version', 2)->value('buyer_confirmed_at'));
        $this->assertDatabaseCount('orders', 1);

        // The buyer-side confirmation must not be attributed to the producer side.
        $this->assertDatabaseHas('negotiations', ['id' => $id, 'status' => 'Agreement Confirmed']);
        $this->assertNotSame($ownProducer->producer_id, $listing->producer_id);
    }

    public function test_only_the_matching_party_can_confirm_a_negotiation(): void
    {
        [$buyerUser, , , $producer, $listing] = $this->actors('ownership');
        $otherUser = User::create(['email' => 'other-owner@example.test', 'password_hash' => bcrypt('secret123'), 'first_name' => 'Other', 'user_type' => 'Farmer']);
        $other = Producer::create(['user_id' => $otherUser->user_id, 'verification_status' => 'Verified']);

        $id = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])->assertCreated()->json('data.id');

        $this->actingAs($otherUser, 'sanctum')->postJson("/api/producer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 1])->assertNotFound();
        $this->assertDatabaseMissing('negotiation_versions', ['negotiation_id' => $id, 'producer_confirmed_at' => now()->format('Y-m-d H:i:s')]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertNotSame($producer->producer_id, $other->producer_id);
    }

    public function test_buyer_rejection_is_not_reported_as_a_confirmation(): void
    {
        [$buyerUser, , , , $listing] = $this->actors('buyer-rejects');
        $id = $this->actingAs($buyerUser, 'sanctum')
            ->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])
            ->assertCreated()
            ->json('data.id');

        $rejected = $this->actingAs($buyerUser, 'sanctum')
            ->postJson("/api/buyer/negotiations/$id/respond", ['action' => 'reject', 'version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'Rejected')
            ->assertJsonPath('data.status_label', 'Rejected')
            ->assertJsonPath('data.rejected_by', 'buyer')
            ->assertJsonPath('data.confirmation.you_confirmed', false)
            ->assertJsonPath('data.confirmation.buyer_confirmed', false)
            ->assertJsonPath('data.confirmation.mutually_approved', false)
            ->assertJsonPath('data.confirmation.buyer_confirmed_at', null);

        $this->assertSame($rejected->json('data.version.id'), $rejected->json('data.rejected_version_id'));
        // Keep the version's original confirmation timestamp as history while
        // reporting the effective negotiation state as rejected.
        $this->assertNotEmpty($rejected->json('data.version.buyer_confirmed_at'));

        $this->actingAs($buyerUser, 'sanctum')
            ->postJson("/api/buyer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 1])
            ->assertStatus(409);
        $this->assertDatabaseHas('negotiations', ['id' => $id, 'status' => 'Rejected']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirmation_of_a_stale_version_is_rejected_with_a_conflict(): void
    {
        [$buyerUser, , $producerUser, , $listing] = $this->actors('stale-version');
        $id = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/buyer/negotiations', ['listing_id' => $listing->listing_id, 'unit_price' => 45, 'quantity' => 20])->assertCreated()->json('data.id');
        $this->actingAs($producerUser, 'sanctum')->postJson("/api/producer/negotiations/$id/respond", ['action' => 'revise', 'version' => 1, 'unit_price' => 47, 'quantity' => 20])->assertOk();

        $this->actingAs($producerUser, 'sanctum')->postJson("/api/producer/negotiations/$id/respond", ['action' => 'confirm', 'version' => 1])->assertStatus(409);
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull(Order::where('negotiation_id', $id)->first());
    }
}
