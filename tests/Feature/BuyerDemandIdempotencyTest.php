<?php
namespace Tests\Feature;
use App\Models\{Buyer,User,DemandRequest,TransactionNotification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;
class BuyerDemandIdempotencyTest extends TestCase {
 use RefreshDatabase;
 private int $seq=0;
 private function actor(string $role):User {return User::create(['email'=>'idempotency'.(++$this->seq).'@example.test','first_name'=>'Synthetic','last_name'=>$role,'password_hash'=>bcrypt('test-only'),'user_type'=>$role,'is_active'=>true]);}
 private function buyer():User {$u=$this->actor('Buyer');Buyer::create(['user_id'=>$u->user_id,'verification_status'=>'Verified','organization_name'=>'Synthetic QA']);return $u;}
 private function payload():array {return ['product_name'=>'Synthetic Rice','quantity_needed'=>5,'unit'=>'kg','deadline'=>now()->addDays(10)->toDateString()];}
 private string $key='20790124-580d-4ad7-9fd4-a117f58070ec';
 public function test_same_key_replays_one_demand_review_and_admin_notice_and_current_state():void {
  $admin=$this->actor('Admin');$buyer=$this->buyer();$this->actingAs($buyer,'sanctum');
  $this->getJson('/api/buyer/demands')->assertOk()->assertJsonPath('submission_idempotency_available',true);
  $created=$this->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertCreated()->assertJsonPath('replayed',false)->json('data');
  $this->postJson('/api/buyer/demands',array_reverse($this->payload(),true),['Idempotency-Key'=>strtoupper($this->key)])->assertOk()->assertJsonPath('data.id',$created['id'])->assertJsonPath('replayed',true);
  $this->assertDatabaseCount('demand_requests',1);$this->assertDatabaseCount('demand_publication_reviews',1);$this->assertDatabaseCount('buyer_demand_submissions',1);
  $this->assertSame(1,TransactionNotification::where('recipient_user_id',$admin->user_id)->count());
  $this->actingAs($admin,'sanctum')->postJson('/api/admin/demands/'.$created['id'].'/review',['decision'=>'Approved','expected_review_id'=>$created['review_id']])->assertOk();
  $this->actingAs($buyer,'sanctum')->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertOk()->assertJsonPath('data.review_status','Approved');
  $this->assertDatabaseCount('demand_publication_reviews',2);$this->assertSame(1,TransactionNotification::where('recipient_user_id',$admin->user_id)->count());
 }
 public function test_changed_payload_conflicts_and_same_uuid_does_not_leak_between_buyers():void {
  $one=$this->buyer();$two=$this->buyer();$this->actingAs($one,'sanctum')->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertCreated();
  $this->postJson('/api/buyer/demands',array_replace($this->payload(),['quantity_needed'=>6]),['Idempotency-Key'=>$this->key])->assertStatus(409);
  $this->actingAs($two,'sanctum')->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertCreated();
  $this->assertDatabaseCount('demand_requests',2);$this->assertDatabaseCount('buyer_demand_submissions',2);
  $this->getJson('/api/buyer/demands')->assertOk()->assertJsonCount(1,'data');
 }
 public function test_missing_optional_schema_keeps_legacy_posts_and_refuses_keyed_posts():void {
  Schema::drop('buyer_demand_submissions');$this->actingAs($this->buyer(),'sanctum');
  $this->getJson('/api/buyer/demands')->assertOk()->assertJsonPath('submission_idempotency_available',false);
  $this->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertStatus(503);
  $this->assertDatabaseCount('demand_requests',0);$this->postJson('/api/buyer/demands',$this->payload())->assertCreated();
 }
 public function test_invalid_header_cannot_create_a_demand():void {
  $this->actingAs($this->buyer(),'sanctum')->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>'invalid'])->assertUnprocessable();$this->assertDatabaseCount('demand_requests',0);
 }
 public function test_failed_database_inbox_rolls_back_submission_key_and_retry_is_safe():void {
  $admin=$this->actor('Admin');$this->actingAs($this->buyer(),'sanctum');
  DB::statement("CREATE TRIGGER qa_fail_demand_notice BEFORE INSERT ON transaction_notifications WHEN NEW.title='New demand request awaiting review' BEGIN SELECT RAISE(ABORT,'synthetic inbox failure'); END");
  $this->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertStatus(500);
  $this->assertDatabaseCount('demand_requests',0);$this->assertDatabaseCount('buyer_demand_submissions',0);$this->assertDatabaseCount('demand_publication_reviews',0);
  DB::statement('DROP TRIGGER qa_fail_demand_notice');
  $this->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertCreated();
  $this->postJson('/api/buyer/demands',$this->payload(),['Idempotency-Key'=>$this->key])->assertOk();
  $this->assertDatabaseCount('demand_requests',1);$this->assertSame(1,TransactionNotification::where('recipient_user_id',$admin->user_id)->count());
 }
 public function test_keyless_submissions_remain_distinct():void {
  $this->actingAs($this->buyer(),'sanctum');for($i=0;$i<2;$i++)$this->postJson('/api/buyer/demands',$this->payload())->assertCreated();$this->assertDatabaseCount('demand_requests',2);
 }
}
