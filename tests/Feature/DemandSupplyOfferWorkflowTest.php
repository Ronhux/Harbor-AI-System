<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\DemandDetail;
use App\Models\DemandRequest;
use App\Models\Order;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandSupplyOfferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_demand_offer_negotiation_creates_shared_order_only_after_both_confirm(): void
    {
        $buyerUser = User::create(['email'=>'demand-buyer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'Institution','user_type'=>'Buyer']);
        $buyer = Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Aparri Hospital','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'demand-producer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'One','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Eggplant','category'=>'Vegetables','product_category'=>'Vegetables','quantity'=>25,'quantity_available'=>25,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>60,'current_price_per_unit'=>60,'status'=>'Active']);
        $demand = DemandRequest::create(['buyer_id'=>$buyer->buyer_id,'product_name'=>'Eggplant','category'=>'Vegetables','quantity_needed'=>10,'unit'=>'kg','max_price_per_unit'=>55,'location'=>'Aparri Hospital','deadline'=>now()->addDays(7)->toDateString(),'status'=>'Active']);
        $reviewer = User::create(['email'=>'reviewer-'.uniqid().'@example.test','first_name'=>'Synthetic','last_name'=>'Reviewer','user_type'=>'Admin','password_hash'=>'test-only']);
        $demand->publicationReviews()->create(['actor_user_id'=>$reviewer->user_id,'decision'=>'Approved']);

        $offer = $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/demands/{$demand->request_id}/offers",[
            'listing_id'=>$listing->listing_id,'quantity_offered'=>8,'proposed_price'=>52,'availability_date'=>now()->addDays(2)->toDateString(),'delivery_terms'=>'Deliver to receiving dock','quality_grade'=>'Grade A','notes'=>'Fresh harvest',
        ])->assertCreated()->assertJsonPath('data.status','Submitted')->json('data.id');

        $this->actingAs($buyerUser,'sanctum')->getJson("/api/buyer/demands/{$demand->request_id}/offers")
            ->assertOk()->assertJsonPath('data.0.producer_name','Producer One')->assertJsonPath('data.0.proposed_price',52);
        $negotiationId = $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/demand-offers/{$offer}/negotiate")
            ->assertOk()->assertJsonPath('data.demand_detail_id',$offer)->assertJsonPath('data.status','Awaiting Producer Confirmation')->json('data.id');

        $this->actingAs($buyerUser,'sanctum')->getJson('/api/buyer/orders')->assertOk()->assertExactJson([]);
        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/{$negotiationId}/respond",['action'=>'accept','version'=>1])
            ->assertOk()->assertJsonPath('data.status','Agreement Confirmed')->assertJsonPath('order.fulfillment_status','Order Submitted');

        $order = Order::where('negotiation_id',$negotiationId)->firstOrFail();
        $this->assertDatabaseHas('demand_details',['id'=>$offer,'status'=>'Order Created']);
        $this->assertDatabaseHas('demand_requests',['request_id'=>$demand->request_id,'status'=>'Order Created']);
        $this->actingAs($buyerUser,'sanctum')->getJson('/api/buyer/orders')->assertOk()->assertJsonPath('0.order_id',$order->order_id);
        $this->actingAs($producerUser,'sanctum')->getJson('/api/producer/orders')->assertOk()->assertJsonPath('0.order_id',$order->order_id);
    }

    public function test_cancelling_future_supply_order_does_not_add_unreserved_stock_and_reopens_demand(): void
    {
        $buyerUser = User::create(['email'=>'future-demand-buyer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Buyer','last_name'=>'Institution','user_type'=>'Buyer']);
        $buyer = Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Aparri Hospital','verification_status'=>'Verified']);
        $producerUser = User::create(['email'=>'future-demand-producer@example.test','password_hash'=>bcrypt('secret123'),'first_name'=>'Producer','last_name'=>'One','user_type'=>'Farmer']);
        $producer = Producer::create(['user_id'=>$producerUser->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing = ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Eggplant','category'=>'Vegetables','product_category'=>'Vegetables','quantity'=>25,'quantity_available'=>25,'unit'=>'kg','unit_of_measure'=>'kg','price_per_unit'=>60,'current_price_per_unit'=>60,'status'=>'Active']);
        $demand = DemandRequest::create(['buyer_id'=>$buyer->buyer_id,'product_name'=>'Eggplant','category'=>'Vegetables','quantity_needed'=>10,'unit'=>'kg','max_price_per_unit'=>55,'location'=>'Aparri Hospital','deadline'=>now()->addDays(7)->toDateString(),'status'=>'Active']);
        $reviewer = User::create(['email'=>'reviewer-'.uniqid().'@example.test','first_name'=>'Synthetic','last_name'=>'Reviewer','user_type'=>'Admin','password_hash'=>'test-only']);
        $demand->publicationReviews()->create(['actor_user_id'=>$reviewer->user_id,'decision'=>'Approved']);
        $availabilityDate = now()->addDays(2)->toDateString();

        $offerId = $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/demands/{$demand->request_id}/offers",[
            'listing_id'=>$listing->listing_id,'quantity_offered'=>8,'proposed_price'=>52,'availability_date'=>$availabilityDate,'delivery_terms'=>'Deliver to receiving dock','quality_grade'=>'Grade A','notes'=>'Future harvest',
        ])->assertCreated()->json('data.id');
        $negotiationId = $this->actingAs($buyerUser,'sanctum')->postJson("/api/buyer/demand-offers/{$offerId}/negotiate")
            ->assertOk()->json('data.id');
        $this->actingAs($producerUser,'sanctum')->postJson("/api/producer/negotiations/{$negotiationId}/respond",['action'=>'accept','version'=>1])
            ->assertOk()->assertJsonPath('order.fulfillment_status','Order Submitted');

        $order = Order::where('negotiation_id',$negotiationId)->firstOrFail();
        $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>25,'quantity'=>25]);
        $this->assertDatabaseHas('demand_requests',['request_id'=>$demand->request_id,'status'=>'Order Created']);

        $this->actingAs($buyerUser,'sanctum')->patchJson("/api/buyer/orders/{$order->order_id}",['status'=>'Cancelled'])
            ->assertOk()->assertJsonPath('order.fulfillment_status','Cancelled');

        $this->assertDatabaseHas('product_listings',['listing_id'=>$listing->listing_id,'quantity_available'=>25,'quantity'=>25]);
        $this->assertDatabaseHas('demand_details',['id'=>$offerId,'status'=>'Withdrawn']);
        $this->assertDatabaseHas('demand_requests',['request_id'=>$demand->request_id,'status'=>'Offers Received']);
        $this->actingAs($buyerUser,'sanctum')->getJson("/api/buyer/negotiations/{$negotiationId}")
            ->assertOk()->assertJsonPath('data.order.fulfillment_status','Cancelled');
    }
}
