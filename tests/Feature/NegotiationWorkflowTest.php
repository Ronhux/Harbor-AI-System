<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegotiationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_order_requires_both_parties_to_confirm_the_same_version(): void
    {
        $buyerUser = User::create(['email'=>'buyer-neg@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'One','user_type'=>'Buyer']);
        $buyer = Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Aparri School District','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'producer-neg@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'One','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Fresh Corn','category'=>'Other Grain','product_category'=>'Other Grain','quantity'=>100,'quantity_available'=>100,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>50,'current_price_per_unit'=>50,'status'=>'Active']);

        $created = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>45,'quantity'=>20]);
        $created->assertCreated()->assertJsonPath('data.status','Awaiting Producer Review')->assertJsonPath('data.version.buyer_confirmed_at',fn ($value) => !empty($value));
        $id = $created->json('data.id');
        $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>45,'quantity'=>20])->assertOk()->assertJsonPath('data.id',$id);
        $this->assertDatabaseCount('negotiations',1);
        $this->actingAs($buyerUser,'sanctum')->getJson('/api/buyer/orders')->assertOk()->assertExactJson([]);

        $agreement = $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$id/respond",['action'=>'accept','version'=>1])->assertOk()->assertJsonPath('data.status','Agreement Confirmed')->assertJsonPath('order.negotiation_id',$id);
        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$id/respond",['action'=>'accept','version'=>1])->assertOk()->assertJsonPath('data.status','Agreement Confirmed')->assertJsonPath('order.order_id',$agreement->json('order.order_id'));
        $this->assertDatabaseHas('orders',['negotiation_id'=>$id,'fulfillment_status'=>'Order Submitted']);
        $this->assertDatabaseHas('orders',['negotiation_id'=>$id,'total_amount'=>900]);
        $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>80]);
        $firstOrder = \App\Models\Order::where('negotiation_id',$id)->firstOrFail();
        $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/negotiations/$id/order",[])->assertCreated()->assertJsonPath('order.order_id',$firstOrder->order_id);
        $this->assertDatabaseCount('orders',1);
        $this->assertDatabaseHas('transaction_notifications',['recipient_user_id'=>$buyerUser->user_id,'order_id'=>$firstOrder->order_id]);
        $this->assertDatabaseHas('transaction_notifications',['recipient_user_id'=>$producerUser->user_id,'order_id'=>$firstOrder->order_id]);
        $this->actingAs($buyerUser,'sanctum')->getJson('/api/buyer/orders')->assertOk()->assertJsonPath('0.order_id',$firstOrder->order_id)->assertJsonPath('0.fulfillment_status','Order Submitted')->assertJsonPath('0.negotiation.status','Agreement Confirmed');
        // The producer sees its own lines and its own subtotal. The order total
        // is no longer exposed on this endpoint because it can include other
        // producers' products.
        $producerOrders = $this->actingAs($producerUser,'sanctum')->getJson('/api/producer/orders')->assertOk()
            ->assertJsonPath('0.order_id',$firstOrder->order_id)
            ->assertJsonPath('0.negotiation.status','Agreement Confirmed')
            ->assertJsonPath('0.producer_subtotal',900)
            ->assertJsonPath('0.details.0.product_name','Fresh Corn')
            ->assertJsonCount(1,'0.details')
            ->json();
        $this->assertArrayNotHasKey('total_amount',$producerOrders[0]);
        foreach (['Order Confirmed','Preparing Order','Ready for Shipment','Shipped','Delivered'] as $status) {
            $this->actingAs($producerUser,'sanctum')->patchJson("/api/producer/orders/{$firstOrder->order_id}",['status'=>$status])->assertOk();
        }
        $this->actingAs($buyerUser,'sanctum')->patchJson("/api/buyer/orders/{$firstOrder->order_id}",['status'=>'Completed'])->assertOk();
        $this->assertDatabaseHas('orders',['order_id'=>$firstOrder->order_id,'fulfillment_status'=>'Completed']);
    }

    public function test_counteroffer_versions_invalidate_stale_confirmations_and_auto_create_order(): void
    {
        $buyerUser = User::create(['email'=>'buyer-version@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','user_type'=>'Buyer']);
        $buyer = Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Buyer Org']);
        $producerUser = User::create(['email'=>'producer-version@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Rice','category'=>'Grains','quantity'=>50,'quantity_available'=>50,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>30,'current_price_per_unit'=>30,'status'=>'Active']);
        $id = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>28,'quantity'=>10])->assertCreated()->json('data.id');

        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$id/respond",['action'=>'revise','version'=>1,'unit_price'=>29,'quantity'=>10])->assertOk()->assertJsonPath('data.current_version',2);
        $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/negotiations/$id/respond",['action'=>'accept','version'=>1])->assertStatus(409);
        $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/negotiations/$id/respond",['action'=>'accept','version'=>2])->assertOk()->assertJsonPath('data.status','Agreement Confirmed');
        $this->assertDatabaseHas('orders',['negotiation_id'=>$id,'total_amount'=>290]);
        $this->assertDatabaseHas('negotiation_versions',['negotiation_id'=>$id,'version'=>1,'unit_price'=>28]);
    }

    public function test_other_producers_cannot_read_or_respond_to_a_negotiation(): void
    {
        $buyerUser = User::create(['email'=>'buyer-auth@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','user_type'=>'Buyer']);
        $buyer = Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Buyer Org']);
        $producerUser = User::create(['email'=>'producer-auth@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified']);
        $otherUser = User::create(['email'=>'other-auth@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Other','user_type'=>'Farmer']);
        Producer::create(['user_id'=>$otherUser->user_id,'verification_status'=>'Verified']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Rice','category'=>'Grains','quantity'=>10,'quantity_available'=>10,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>20,'current_price_per_unit'=>20,'status'=>'Active']);
        $id = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>18,'quantity'=>5])->json('data.id');
        $this->actingAs($otherUser,'sanctum')->getJson("/api/producer/negotiations/$id")->assertNotFound();
        $this->actingAs($otherUser,'sanctum')->postJson("/api/producer/negotiations/$id/respond",['action'=>'accept'])->assertNotFound();
    }

    public function test_direct_order_endpoint_creates_an_order_from_a_confirmed_negotiation(): void
    {
        $buyerUser = User::create(['email'=>'buyer-direct-order@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'One','user_type'=>'Buyer']);
        Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Aparri Supply Hub','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'producer-direct-order@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'One','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Fresh Mango','category'=>'Fruit','product_category'=>'Fruit','quantity'=>75,'quantity_available'=>75,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>60,'current_price_per_unit'=>60,'status'=>'Active']);

        $negotiationId = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>58,'quantity'=>15])->assertCreated()->json('data.id');

        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$negotiationId/respond",['action'=>'accept','version'=>1])->assertOk()->assertJsonPath('data.status','Agreement Confirmed');

        $this->actingAs($buyerUser,'sanctum')->postJson('/api/orders', [
            'listing_id' => $listing->listing_id,
            'quantity' => 15,
            'shipping_address' => 'Warehouse 3, Aparri',
            'delivery_date' => now()->addDay()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('order.negotiation_id', $negotiationId)
            ->assertJsonPath('order.shipping_address', 'Warehouse 3, Aparri');

        $this->assertDatabaseHas('orders', ['negotiation_id' => $negotiationId, 'shipping_address' => 'Warehouse 3, Aparri', 'fulfillment_status' => 'Order Submitted']);
        $this->assertDatabaseHas('order_details', ['listing_id' => $listing->listing_id, 'quantity' => 15]);
    }

    public function test_retries_are_idempotent_and_do_not_double_reserve_stock_or_notifications(): void
    {
        $buyerUser = User::create(['email'=>'buyer-retry@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'Retry','user_type'=>'Buyer']);
        Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Retry Org','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'producer-retry@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'Retry','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Cabbage','category'=>'Vegetables','product_category'=>'Vegetables','quantity'=>50,'quantity_available'=>50,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>30,'current_price_per_unit'=>30,'status'=>'Active']);

        $negotiationId = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>28,'quantity'=>10])->assertCreated()->json('data.id');
        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$negotiationId/respond",['action'=>'accept','version'=>1])->assertOk()->assertJsonPath('data.status','Agreement Confirmed');

        $firstRetry = $this->actingAs($buyerUser,'sanctum')->postJson('/api/orders', ['negotiation_id' => $negotiationId, 'shipping_address' => 'Warehouse 1'])->assertCreated();
        $orderId = $firstRetry->json('order.order_id');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>40]);
        $this->assertDatabaseHas('transaction_notifications',['recipient_user_id'=>$buyerUser->user_id,'order_id'=>$orderId]);
        $this->assertDatabaseHas('transaction_notifications',['recipient_user_id'=>$producerUser->user_id,'order_id'=>$orderId]);

        $secondRetry = $this->actingAs($buyerUser,'sanctum')->postJson('/api/orders', ['negotiation_id' => $negotiationId, 'shipping_address' => 'Warehouse 2']);
        $secondRetry->assertStatus(201)->assertJsonPath('order.order_id',$orderId);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>40]);
        $this->assertSame(2, \App\Models\TransactionNotification::where('order_id', $orderId)->count());
    }

    public function test_buyer_cannot_order_from_another_buyers_negotiation_and_unconfirmed_negotiations_are_rejected(): void
    {
        $buyerUser = User::create(['email'=>'buyer-other@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'Other','user_type'=>'Buyer']);
        Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Buyer Org One','verification_status'=>'Verified']);
        $otherBuyerUser = User::create(['email'=>'other-buyer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Other','last_name'=>'Buyer','user_type'=>'Buyer']);
        Buyer::create(['user_id'=>$otherBuyerUser->user_id,'organization_name'=>'Buyer Org Two','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'producer-other@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'Other','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Ginger','category'=>'Vegetables','product_category'=>'Vegetables','quantity'=>30,'quantity_available'=>30,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>40,'current_price_per_unit'=>40,'status'=>'Active']);

        $negotiationId = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>38,'quantity'=>8])->assertCreated()->json('data.id');
        $this->actingAs($otherBuyerUser,'sanctum')->postJson('/api/orders',['negotiation_id'=>$negotiationId,'shipping_address'=>'Foreign Warehouse'])->assertForbidden();

        $this->actingAs($buyerUser,'sanctum')->postJson('/api/orders',['negotiation_id'=>$negotiationId,'shipping_address'=>'Warehouse 1'])->assertStatus(409);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_insufficient_stock_is_rejected_without_partial_order_updates(): void
    {
        $buyerUser = User::create(['email'=>'buyer-stock@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'Stock','user_type'=>'Buyer']);
        Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Stock Org','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'producer-stock@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'Stock','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Onion','category'=>'Vegetables','product_category'=>'Vegetables','quantity'=>5,'quantity_available'=>5,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>25,'current_price_per_unit'=>25,'status'=>'Active']);

        $negotiationId = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>24,'quantity'=>5])->assertCreated()->json('data.id');
        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$negotiationId/respond",['action'=>'accept','version'=>1])->assertOk();
        \App\Models\Order::where('negotiation_id', $negotiationId)->delete();

        \App\Models\ProductListing::whereKey($listing->listing_id)->update(['quantity_available' => 0, 'quantity' => 0]);

        $this->actingAs($buyerUser,'sanctum')->postJson('/api/orders', ['negotiation_id' => $negotiationId, 'shipping_address' => 'Warehouse 1'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The product no longer has enough available quantity.');

        $this->assertDatabaseHas('negotiations',['id'=>$negotiationId,'status'=>'Agreement Confirmed']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>0,'quantity'=>0]);
    }

    public function test_rejected_and_cancelled_negotiations_cannot_be_confirmed_or_ordered(): void
    {
        foreach ([['reject', 'Rejected'], ['cancel', 'Cancelled'], ['expire', 'Expired']] as [$closingAction, $expectedStatus]) {
            $suffix = strtolower($expectedStatus);
            $buyerUser = User::create(['email'=>"buyer-closed-$suffix@example.test",'password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','user_type'=>'Buyer']);
            Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Buyer Org']);
            $producerUser = User::create(['email'=>"producer-closed-$suffix@example.test",'password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','user_type'=>'Farmer']);
            $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified']);
            $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Rice','category'=>'Grains','quantity'=>30,'quantity_available'=>30,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>35,'current_price_per_unit'=>35,'status'=>'Active']);
            $id = $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/negotiations',['listing_id'=>$listing->listing_id,'unit_price'=>32,'quantity'=>10])->assertCreated()->json('data.id');

            if ($closingAction === 'reject') {
                $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/$id/respond",['action'=>'reject','version'=>1])
                    ->assertOk()->assertJsonPath('data.status','Rejected');
            } elseif ($closingAction === 'cancel') {
                $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/negotiations/$id/respond",['action'=>'cancel','version'=>1])
                    ->assertOk()->assertJsonPath('data.status','Cancelled');
            } else {
                \App\Models\Negotiation::whereKey($id)->update(['status' => 'Expired']);
            }

            // The buyer had confirmed version 1 when proposing it. That saved
            // timestamp must not make a later confirmation look successful.
            $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/negotiations/$id/respond",['action'=>'confirm','version'=>1])
                ->assertStatus(409);
            $this->assertDatabaseHas('negotiations',['id'=>$id,'status'=>$expectedStatus]);
            $this->assertDatabaseCount('orders',0);
            $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>30]);
        }
    }
}
