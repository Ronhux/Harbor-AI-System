<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createConfirmedOrder(string $suffix): array
    {
        $buyerUser = User::create([
            'email' => "buyer-{$suffix}@example.test",
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Buyer',
            'user_type' => 'Buyer',
        ]);
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Buyer Org']);
        $producerUser = User::create([
            'email' => "producer-{$suffix}@example.test",
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Producer',
            'user_type' => 'Farmer',
        ]);
        $producer = Producer::create(['user_id' => $producerUser->user_id, 'verification_status' => 'Verified']);
        $listing = ProductListing::create([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Workflow Rice',
            'product_category' => 'Grains',
            'current_price_per_unit' => 50,
            'unit_of_measure' => 'kg',
            'quantity_available' => 100,
            'quantity' => 100,
            'status' => 'Active',
        ]);

        $negotiationId = $this->actingAs($buyerUser, 'sanctum')
            ->postJson('/api/buyer/negotiations', [
                'listing_id' => $listing->listing_id,
                'unit_price' => 45,
                'quantity' => 20,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($producerUser, 'sanctum')
            ->postJson("/api/producer/negotiations/{$negotiationId}/respond", [
                'action' => 'accept',
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Agreement Confirmed');

        return [
            $buyerUser,
            $producerUser,
            $listing->fresh(),
            Order::where('negotiation_id', $negotiationId)->firstOrFail(),
        ];
    }

    public function test_order_status_updates_enforce_ownership_and_allowed_transitions(): void
    {
        [$buyerUser, $producerUser, $listing, $order] = $this->createConfirmedOrder('permissions');
        $otherBuyer = User::create([
            'email' => 'other-order-buyer@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Other Buyer',
            'user_type' => 'Buyer',
        ]);
        Buyer::create(['user_id' => $otherBuyer->user_id]);
        $otherProducer = User::create([
            'email' => 'other-order-producer@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Other Producer',
            'user_type' => 'Farmer',
        ]);
        Producer::create(['user_id' => $otherProducer->user_id, 'verification_status' => 'Verified']);

        $this->actingAs($otherBuyer, 'sanctum')
            ->patchJson("/api/buyer/orders/{$order->order_id}", ['status' => 'Cancelled'])
            ->assertForbidden();
        $this->actingAs($otherProducer, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Order Confirmed'])
            ->assertForbidden();
        $this->actingAs($producerUser, 'sanctum')
            ->patchJson("/api/producer/orders/{$order->order_id}", ['status' => 'Shipped'])
            ->assertStatus(409);
        $this->actingAs($buyerUser, 'sanctum')
            ->patchJson("/api/buyer/orders/{$order->order_id}", ['status' => 'Completed'])
            ->assertStatus(409);

        $this->assertDatabaseHas('orders', [
            'order_id' => $order->order_id,
            'fulfillment_status' => 'Order Submitted',
        ]);
        $this->assertDatabaseHas('product_listings', [
            'listing_id' => $listing->listing_id,
            'quantity_available' => 80,
        ]);
    }

    public function test_cancellation_releases_reserved_stock_once_and_keeps_status_history_in_sync(): void
    {
        [$buyerUser, $producerUser, $listing, $order] = $this->createConfirmedOrder('cancel-stock');

        $this->actingAs($buyerUser, 'sanctum')
            ->patchJson("/api/buyer/orders/{$order->order_id}", ['status' => 'Cancelled'])
            ->assertOk()
            ->assertJsonPath('order.fulfillment_status', 'Cancelled');

        $this->assertDatabaseHas('product_listings', [
            'listing_id' => $listing->listing_id,
            'quantity_available' => 100,
            'quantity' => 100,
        ]);
        $this->assertDatabaseHas('negotiation_events', [
            'negotiation_id' => $order->negotiation_id,
            'event' => 'order_status_changed',
            'remarks' => 'Cancelled',
        ]);
        $this->assertDatabaseHas('transaction_notifications', [
            'recipient_user_id' => $producerUser->user_id,
            'order_id' => $order->order_id,
            'title' => 'Order updated',
        ]);

        $this->actingAs($buyerUser, 'sanctum')
            ->patchJson("/api/buyer/orders/{$order->order_id}", ['status' => 'Cancelled'])
            ->assertStatus(409);

        $this->assertDatabaseHas('product_listings', [
            'listing_id' => $listing->listing_id,
            'quantity_available' => 100,
        ]);
        $this->assertDatabaseCount('orders', 1);
    }
}
