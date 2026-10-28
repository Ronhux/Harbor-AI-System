<?php
namespace Tests\Feature;
use App\Models\{FcaRegistry,FcaDirectoryContact,FcaPresidentClaim,FcaOtpChallenge,User,Producer,FcaPhoneEvent};
use App\Services\Mao\President\{PresidentVerification,PresidentIdentity,SemaphoreOtpDelivery};
use Illuminate\Support\Facades\{Http,DB,Hash};
use Illuminate\Support\Str;
use Tests\Traits\VerifiesTestDatabase;
class FcaPresidentBackendTest extends FcaApplicationPersistenceTest {
 use VerifiesTestDatabase;
 private string $phone;private ?string $code=null;private PresidentVerification $verification;
 protected function setUp():void {
   $this->verifyTestDatabaseTargetPreBoot();
   parent::setUp();
   $this->verifyTestDatabaseTargetPostBoot();
   Http::preventStrayRequests();$this->phone='09'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT);
   config(['app.key'=>'base64:'.base64_encode(random_bytes(32)),'fca_president.lookup_key'=>str_repeat('L',32),'fca_president.verifier_key'=>str_repeat('V',32),'fca_president.workflow_enabled'=>true]);
   $this->verification=new PresidentVerification();
 }
 private function enable():void {config(['fca_president.verification_method'=>'otp_future','fca_president.workflow_enabled'=>true,'fca_president.notice_version'=>'synthetic.notice','fca_president.sms_enabled'=>true,'fca_president.semaphore_key'=>'not-a-real-key','fca_president.sender'=>'Synthetic']);}
 private function owner():User {return User::where('user_type','Farmer')->firstOrFail();}
 private function directory():FcaRegistry {return FcaRegistry::firstOrFail();}
 private function contact():FcaDirectoryContact {$row=(new FcaDirectoryContact())->forceFill(['fca_id'=>$this->directory()->id,'phone'=>$this->phone,'phone_lookup'=>PresidentIdentity::digest($this->phone),'source_reference'=>'Synthetic approved source','status'=>'active','version'=>1,'valid_until'=>now()->addMonth()]);$row->saveOrFail();return $row;}
 private function claim():array {return $this->verification->match($this->owner(),$this->directory()->id,$this->directory()->official_name,'Synthetic president',$this->phone,true);}
 private function fakeSms(string $status='Pending'):void {
  Http::fake(['api.semaphore.co/*'=>function($request)use($status){$this->assertTrue($request['number']===$this->phone);$this->code=$request['code'];return Http::response([['message_id'=>1,'recipient'=>$request['number'],'code'=>$request['code'],'message'=>'private code body','status'=>$status]],200);}]);
 }
 public function test_president_valid_provider_and_otp_do_not_grant_authority():void {
  $this->enable();$c=$this->contact();$claim=$this->claim();$this->assertSame('phone_required',$claim['status']);$this->fakeSms();$sent=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');
  $this->assertSame('code_required',$sent['status']);$this->assertFalse($sent['phone_verified']);$raw=DB::table('fca_otp_challenges')->first();$this->assertTrue($raw->verifier!==$this->code);
  $done=$this->verification->verify($this->owner(),$sent['challenge_id'],$this->code);$this->assertTrue($done['phone_verified']);$this->assertSame('pending',$done['authority_status']);
  $this->assertFalse($this->verification->verify($this->owner(),$sent['challenge_id'],$this->code)['phone_verified']);$this->assertSame(1,DB::table('fca_phone_events')->where('event','phone_verified')->count());
  $this->assertTrue(DB::table('fca_directory_contacts')->first()->phone!==$this->phone);$this->assertArrayNotHasKey('phone',$c->toArray());$this->assertArrayNotHasKey('phone_lookup',$c->toArray());
  $this->assertTrue(!str_contains(json_encode($sent),$this->phone)&&!str_contains(json_encode($done),$this->code));
 }
 public function test_president_wrong_name_duplicate_association_and_claims_require_manual_review():void {
  $this->enable();$this->contact();$v=$this->verification->match($this->owner(),$this->directory()->id,$this->directory()->official_name,'Wrong identity',$this->phone,true);$this->assertSame('manual_review',$v['status']);
  $this->assertSame($v['id'],$this->claim()['id']);$this->assertSame(1,FcaPresidentClaim::count());
  }
 public function test_president_duplicate_directory_is_manual():void {$this->enable();$this->contact();(new FcaRegistry())->forceFill(['official_name'=>strtoupper($this->directory()->official_name),'address'=>'Synthetic','president_name'=>'Other','directory_verification_status'=>'verified'])->save();$this->assertSame('manual_review',$this->claim()['status']);
 }
 public function test_president_provider_failures_and_missing_credentials_never_verify():void {
  $this->enable();$this->contact();$claim=$this->claim();$this->fakeSms('Failed');$sent=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');$this->assertSame('unavailable',$sent['status']);$this->assertFalse($this->verification->verify($this->owner(),$sent['challenge_id'],$this->code)['phone_verified']);
  config(['fca_president.semaphore_key'=>null]);$this->assertNull((new SemaphoreOtpDelivery())->send($this->phone,'123456'));
 }
 public function test_president_expiry_wrong_attempts_and_resend_limits():void {
  $this->enable();$this->contact();$claim=$this->claim();$this->fakeSms();$sent=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');
  for($i=0;$i<5;$i++)$this->assertFalse($this->verification->verify($this->owner(),$sent['challenge_id'],'000000')['phone_verified']);
  $this->assertSame('locked',FcaOtpChallenge::first()->status);
  try{$this->verification->send($this->owner(),$claim['id'],'synthetic-address');$this->fail('Cooldown bypass');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(429,$e->getStatusCode());}
  $this->travel(61)->seconds();$second=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');$this->travel(301)->seconds();$this->assertFalse($this->verification->verify($this->owner(),$second['challenge_id'],$this->code)['phone_verified']);
 }
 public function test_president_changed_contact_during_provider_send_fails_closed():void {
  $this->enable();$contact=$this->contact();$claim=$this->claim();Http::fake(['api.semaphore.co/*'=>function($request)use($contact){$contact->version++;$contact->save();return Http::response([['message_id'=>1,'recipient'=>$request['number'],'code'=>$request['code'],'status'=>'Pending']],200);}]);
  $sent=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');$this->assertSame('unavailable',$sent['status']);$this->assertSame('manual_review',$this->verification->show($this->owner(),$claim['id'])['status']);
 }
 public function test_president_pending_onboarding_token_cannot_use_other_apis():void {
  $this->enable();Producer::where('user_id',$this->owner()->getKey())->update(['verification_status'=>'Pending']);
  app('router')->middleware('api')->prefix('api')->group(base_path('routes/prepared/mao_fca_president.php'));
  $user=$this->owner();app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($user);$token=$this->postJson('/api/fca-president/onboarding',['email'=>$user->email,'password'=>'test-only'])->assertOk()->json('token');
  $this->withToken($token)->getJson('/api/policies/acceptance/status')->assertForbidden();app('auth')->forgetGuards();$this->contact();
  $claim=$this->withToken($token)->postJson('/api/fca-president/directory/'.$this->directory()->id.'/claim',['association'=>$this->directory()->official_name,'name'=>'Synthetic president','phone'=>$this->phone,'consent'=>true])->assertOk()->json('id');
  $this->withToken($token)->postJson('/api/fca-president/claims/'.$claim.'/send',['number'=>'arbitrary destination'])->assertUnprocessable();
  $this->withToken($token)->postJson('/api/fca-president/claims/'.$claim.'/application',[])->assertForbidden();
  config(['fca_president.workflow_enabled'=>false]);
  $this->withToken($token)->getJson('/api/policies/acceptance/status')->assertForbidden();
 }
 public function test_president_foreign_actor_and_unassigned_admin_are_denied():void {
  $this->enable();$this->contact();$claim=$this->claim();$admin=User::where('user_type','Admin')->firstOrFail();
  try{$this->verification->show($admin,$claim['id']);$this->fail('Foreign read');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
  config(['mao_workflows.fca_reviewer_user_ids'=>[]]);try{$this->verification->reviewClaim($admin,$claim['id'],'reject',1);$this->fail('Admin bypass');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
 }
 public function test_president_normalization_is_exact_and_missing_configuration_is_closed():void {
  $this->assertSame(PresidentIdentity::name(' Synthetic   President '),PresidentIdentity::name('synthetic president'));$this->assertNotSame(PresidentIdentity::name('Synthetic president'),PresidentIdentity::name('S. president'));
  $this->assertNull(PresidentIdentity::phone("invalid\n"));$this->assertFalse((require base_path('config/fca_president.php'))['workflow_enabled']);Http::fake();$this->assertNull((new SemaphoreOtpDelivery())->send($this->phone,'123456'));Http::assertNothingSent();
 }
public function test_president_linked_application_requires_admin_and_current_phone_proof():void {
   $this->enable();$contact=$this->contact();$c=$this->claim();$this->fakeSms();$sent=$this->verification->send($this->owner(),$c['id'],'synthetic-address');
   $this->verification->verify($this->owner(),$sent['challenge_id'],$this->code);
   $before=$this->owner()->fresh()->getAttributes();
   $receipt=$this->actingAs($this->owner())->postJson('/api/fca/evidence',['evidence'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf',"%PDF-1.4\nSynthetic\n%%EOF")])->assertCreated()->json('receipt');$this->inspectReceipt($receipt);
   $input=['representative_position'=>'President','authorization_statement'=>'Synthetic authority evidence','evidence_receipt'=>$receipt,'submission_key'=>(string)Str::uuid()];
   $app=$this->verification->submit($this->owner(),$c['id'],$input);$this->assertSame('pending',$app['status']);
  $record=\App\Models\FcaRepresentative::findOrFail($app['id']);$this->assertSame($c['id'],$record->president_claim_id);$this->assertSame('president',$record->verification_kind);
  $admin=User::where('user_type','Admin')->firstOrFail();$decision=['decision'=>'authorize','expected_version'=>0,'decision_key'=>(string)Str::uuid(),'review_reason'=>'Synthetic reviewed identity','expires_at'=>now()->addMonth()->toISOString()];
  $contact->version++;$contact->save();
  try{app(\App\Services\Mao\FcaApplicationService::class)->review($admin,$record->id,$decision);$this->fail('Stale proof approved');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
  $this->assertSame('pending',$record->fresh()->status);$this->assertSame($before,$this->owner()->fresh()->getAttributes());
 }
 public function test_president_shared_phone_wrong_recipient_and_revocation_are_safe():void {
  $this->enable();$contact=$this->contact();$c=$this->claim();
  Http::fake(['api.semaphore.co/*'=>fn($r)=>Http::response([['message_id'=>1,'recipient'=>'invalid','code'=>$r['code'],'status'=>'Sent']],200)]);
  $this->assertSame('unavailable',$this->verification->send($this->owner(),$c['id'],'synthetic-address')['status']);
  $admin=User::where('user_type','Admin')->firstOrFail();$this->assertSame('revoked',$this->verification->reviewClaim($admin,$c['id'],'revoke',1)['status']);
  $this->assertSame('failed',FcaOtpChallenge::first()->status); // Failed remains unusable; never revived.
  $this->assertFalse($this->verification->currentProof(FcaPresidentClaim::find($c['id'])));
 }
 public function test_president_shared_contact_and_duplicate_president_claim_are_manual():void {
  $this->enable();$this->contact();$first=$this->claim();
  $other=User::create(['first_name'=>'Synthetic','last_name'=>'Other','email'=>Str::uuid().'@example.test','password_hash'=>bcrypt('test-only'),'user_type'=>'Farmer','is_active'=>true]);
  Producer::create(['user_id'=>$other->getKey(),'verification_status'=>'Verified']);
  $this->assertSame('manual_review',$this->verification->match($other,$this->directory()->id,$this->directory()->official_name,'Synthetic president',$this->phone,true)['status']);
  $second=(new FcaRegistry())->forceFill(['official_name'=>'Different synthetic association','address'=>'Synthetic','president_name'=>'Synthetic president','directory_verification_status'=>'verified']);$second->save();
  (new FcaDirectoryContact())->forceFill(['fca_id'=>$second->id,'phone'=>$this->phone,'phone_lookup'=>PresidentIdentity::digest($this->phone),'source_reference'=>'Synthetic','status'=>'active','version'=>1,'valid_until'=>now()->addMonth()])->save();
  $this->assertSame('manual_review',$this->verification->show($this->owner(),$first['id'])['status']);
 }
public function test_president_audit_failure_rolls_back_phone_consumption():void {
   $this->enable();$this->contact();$claim=$this->claim();$this->fakeSms();$sent=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');
   FcaPhoneEvent::creating(function ($model) { throw new \Illuminate\Database\QueryException('SQLSTATE[HY000]', 'INSERT INTO fca_phone_events', [], new \Exception('Synthetic audit failure')); });
   try{$this->verification->verify($this->owner(),$sent['challenge_id'],$this->code);$this->fail('Unaudited verification');}catch(\Illuminate\Database\QueryException){$this->assertSame('pending',FcaOtpChallenge::first()->status);$this->assertSame('phone_required',FcaPresidentClaim::find($claim['id'])->status);$this->assertNull(FcaOtpChallenge::first()->consumed_at);}
   FcaPhoneEvent::flushEventListeners();
 }
public function test_president_admin_approval_is_explicit_and_revocable():void {
   $this->enable();$this->contact();$claim=$this->claim();$this->fakeSms();$sent=$this->verification->send($this->owner(),$claim['id'],'synthetic-address');$this->verification->verify($this->owner(),$sent['challenge_id'],$this->code);
   $receipt=$this->actingAs($this->owner())->postJson('/api/fca/evidence',['evidence'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf',"%PDF-1.4\nSynthetic\n%%EOF")])->assertCreated()->json('receipt');$this->inspectReceipt($receipt);
   $app=$this->verification->submit($this->owner(),$claim['id'],['representative_position'=>'President','authorization_statement'=>'Synthetic evidence','evidence_receipt'=>$receipt,'submission_key'=>(string)Str::uuid()]);
  $before=$this->owner()->fresh()->getAttributes();$admin=User::where('user_type','Admin')->firstOrFail();$service=app(\App\Services\Mao\FcaApplicationService::class);
  $approved=$service->review($admin,$app['id'],['decision'=>'authorize','expected_version'=>0,'decision_key'=>(string)Str::uuid(),'review_reason'=>'Synthetic reviewed identity','expires_at'=>now()->addMonth()->toISOString()]);
  $this->assertSame('authorized',$approved->status);$this->assertSame($before,$this->owner()->fresh()->getAttributes());$this->assertSame('not_assessed',$this->directory()->procurement_eligibility_status);
  $revoked=$service->review($admin,$app['id'],['decision'=>'revoke','expected_version'=>1,'decision_key'=>(string)Str::uuid(),'review_reason'=>'Synthetic revocation']);$this->assertSame('revoked',$revoked->status);
 }
}
