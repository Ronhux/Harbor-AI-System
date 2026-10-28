<?php
namespace Tests\Feature;
use App\Models\{User,Buyer,Producer,DemandRequest,ProductListing};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class DemandPublicationReviewTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role): User {
        static $seq=0; $seq++;
        return User::create(['email'=>"review$seq@example.test",'first_name'=>'Synthetic','last_name'=>$role,'password_hash'=>bcrypt('test-only'),'contact_number'=>sprintf('0917%07d',$seq),'user_type'=>$role]);
    }
    public function test_review_visibility_permissions_edits_and_history(): void {
        $buyerUser=$this->user('Buyer');
        Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Synthetic review institution','buyer_type'=>'Institution','verification_status'=>'Verified']);
        $farmer=$this->user('Farmer');
        $producer=Producer::create(['user_id'=>$farmer->user_id,'verification_status'=>'Verified','producer_type'=>'Farmer']);
        $listing=ProductListing::create(['producer_id'=>$producer->producer_id,'product_name'=>'Rice','status'=>'Active','quantity_available'=>20,'current_price_per_unit'=>30,'unit_of_measure'=>'kg']);
        $admin=$this->user('Admin');
        $payload=['product_name'=>'Rice','quantity_needed'=>10,'unit'=>'kg','deadline'=>now()->addDays(10)->toDateString()];
        $created=$this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/demands',$payload)->assertCreated()->assertJsonPath('data.review_status','Pending Review')->json('data');
        $id=$created['id'];
        $decision=['decision'=>'Approved','expected_review_id'=>$created['review_id']];
        $this->postJson("/api/admin/demands/$id/review",$decision)->assertForbidden();
        $this->actingAs($farmer,'sanctum')->getJson('/api/producer/demand-opportunities')->assertOk()->assertJsonCount(0,'data');
        $this->postJson("/api/producer/demands/$id/offers",['listing_id'=>$listing->listing_id,'quantity_offered'=>5,'proposed_price'=>30])->assertNotFound();
        $this->postJson("/api/admin/demands/$id/review",$decision)->assertForbidden();
        $this->actingAs($admin,'sanctum')->getJson('/api/admin/demands')->assertOk()->assertJsonPath('data.0.buyer','Synthetic review institution');
        $this->postJson("/api/admin/demands/$id/review",$decision)->assertOk()->assertJsonPath('data.review_status','Approved');
        $this->postJson("/api/admin/demands/$id/review",$decision)->assertOk();
        $this->assertDatabaseCount('demand_publication_reviews',2);
        $notice = \App\Models\TransactionNotification::where('recipient_user_id', $buyerUser->user_id)->sole();
        $this->assertSame(['type'=>'buyer_demand','record_id'=>$id], $notice->navigation);
        $this->actingAs($buyerUser,'sanctum')->getJson("/api/buyer/demands/$id")->assertOk()->assertJsonPath('data.review_status','Approved');
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count',1);
        $this->patchJson('/api/notifications/'.$notice->id.'/read')->assertOk();
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count',0);
        $otherBuyer=$this->user('Buyer');
        Buyer::create(['user_id'=>$otherBuyer->user_id,'organization_name'=>'Other synthetic','verification_status'=>'Verified']);
        $this->actingAs($otherBuyer,'sanctum')->getJson("/api/buyer/demands/$id")->assertNotFound();
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0,'data');
        $this->patchJson('/api/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->actingAs($farmer,'sanctum')->getJson('/api/producer/demand-opportunities')->assertOk()->assertJsonCount(1,'data');
        $this->postJson("/api/producer/demands/$id/offers",['listing_id'=>$listing->listing_id,'quantity_offered'=>5,'proposed_price'=>30])->assertCreated();
        $edited=$this->actingAs($buyerUser,'sanctum')->putJson("/api/buyer/demands/$id",['quantity_needed'=>12])->assertOk()->assertJsonPath('data.review_status','Pending Review')->json('data');
        $this->assertDatabaseCount('demand_details',1);
        $this->actingAs($farmer,'sanctum')->getJson('/api/producer/demand-opportunities')->assertOk()->assertJsonCount(0,'data');
        $this->actingAs($admin,'sanctum')->postJson("/api/admin/demands/$id/review",$decision)->assertStatus(409);
        $this->postJson("/api/admin/demands/$id/review",['decision'=>'Rejected','expected_review_id'=>$edited['review_id']])->assertUnprocessable();
        $this->postJson("/api/admin/demands/$id/review",['decision'=>'Rejected','reason'=>'Clarify the quantity.','expected_review_id'=>$edited['review_id']])->assertOk();
        $this->actingAs($farmer,'sanctum')->getJson('/api/producer/demand-opportunities')->assertOk()->assertJsonCount(0,'data');
        $this->postJson("/api/producer/demands/$id/offers",['listing_id'=>$listing->listing_id,'quantity_offered'=>5,'proposed_price'=>30])->assertNotFound();
        $this->actingAs($buyerUser,'sanctum')->getJson("/api/buyer/demands/$id")->assertOk()->assertJsonPath('data.review_reason','Clarify the quantity.');
        $this->assertSame(2, \App\Models\TransactionNotification::where('recipient_user_id',$buyerUser->user_id)->where('title','like','Demand request %')->count());
    }
    public function test_missing_review_schema_fails_closed_without_breaking_reads(): void {
        $buyerUser=$this->user('Buyer');
        Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Synthetic','verification_status'=>'Verified']);
        \Illuminate\Support\Facades\Schema::partialMock()->shouldReceive('hasTable')->with('demand_publication_reviews')->andReturn(false);
        $this->assertSame(0,DemandRequest::published()->count());
        $this->actingAs($buyerUser,'sanctum')->postJson('/api/buyer/demands',['product_name'=>'Rice','quantity_needed'=>1,'unit'=>'kg','deadline'=>now()->addDay()->toDateString()])->assertStatus(503);
        $this->getJson('/api/buyer/demands')->assertOk();
    }

    public function test_legacy_records_are_not_rewritten_or_silently_approved(): void {
        $admin=$this->user('Admin');
        $buyerUser=$this->user('Buyer');
        $buyer=Buyer::create(['user_id'=>$buyerUser->user_id,'organization_name'=>'Synthetic legacy','buyer_type'=>'Institution']);
        $demand=DemandRequest::create(['buyer_id'=>$buyer->buyer_id,'product_name'=>'Corn','quantity_needed'=>2,'status'=>'Active']);
        $this->assertSame(0,DemandRequest::published()->count());
        $this->assertSame('Active',$demand->fresh()->status);
        $this->actingAs($admin,'sanctum')->getJson('/api/admin/demands')->assertOk()->assertJsonPath('data.0.review_status','Legacy Unreviewed');
        $this->postJson('/api/admin/demands/'.$demand->request_id.'/review',['decision'=>'Approved','expected_review_id'=>0])->assertOk();
        $this->assertSame(1,DemandRequest::published()->count());
    }

    public function test_expired_demands_cannot_be_approved_and_notification_failure_rolls_back(): void {
        $owner=$this->user('Buyer');
        $buyer=Buyer::create(['user_id'=>$owner->user_id,'organization_name'=>'Synthetic rollback','verification_status'=>'Verified']);
        $demand=DemandRequest::create(['buyer_id'=>$buyer->buyer_id,'product_name'=>'Rice','quantity_needed'=>2,'status'=>'Active','deadline'=>now()->subDay()]);
        $pending=$demand->publicationReviews()->create(['actor_user_id'=>$owner->user_id,'decision'=>'Pending Review']);
        $admin=$this->user('Admin');
        $url='/api/admin/demands/'.$demand->request_id.'/review';
        $payload=['decision'=>'Approved','expected_review_id'=>$pending->id];
        $this->actingAs($admin,'sanctum')->postJson($url,$payload)->assertStatus(409);
        $demand->update(['deadline'=>now()->addDay()]);
        \App\Models\TransactionNotification::creating(function () { throw new \RuntimeException('Synthetic inbox failure'); });
        try {
            $this->postJson($url,$payload)->assertStatus(500);
            $this->assertDatabaseCount('demand_publication_reviews',1);
            $this->assertDatabaseCount('transaction_notifications',0);
        } finally {
            \App\Models\TransactionNotification::flushEventListeners();
        }
        $this->postJson($url,$payload)->assertOk();
        $this->postJson($url,$payload)->assertOk();
        $this->assertDatabaseCount('demand_publication_reviews',2);
        $this->assertDatabaseCount('transaction_notifications',1);
    }
}
