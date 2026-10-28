<?php
namespace Tests\Feature;
use App\Models\{User,FcaRegistry,FcaDirectoryContact,FcaPresidentClaim,FcaManualDecision,FcaPhoneEvent};
use App\Services\Mao\President\{PresidentVerification,PresidentIdentity};
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Tests\Traits\VerifiesTestDatabase;
class FcaManualVerificationTest extends FcaApplicationPersistenceTest {
 use VerifiesTestDatabase;
 private PresidentVerification $service;private string $phone;
 protected function setUp():void {
   $this->verifyTestDatabaseTargetPreBoot();
   parent::setUp();
   $this->verifyTestDatabaseTargetPostBoot();
   config(['app.key'=>'base64:'.base64_encode(random_bytes(32)),'fca_president.lookup_key'=>str_repeat('L',32)]);
   Http::preventStrayRequests();$this->phone='09'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT);$this->service=new PresidentVerification();
 }
 private function owner():User{return User::where('user_type','Farmer')->firstOrFail();}
 private function admin():User{return User::where('user_type','Admin')->firstOrFail();}
 private function claim(string $name='Synthetic president'):array {
  config(['fca_president.workflow_enabled'=>true,'fca_president.notice_version'=>'synthetic.manual.notice']);
  $fca=FcaRegistry::firstOrFail();
  (new FcaDirectoryContact())->forceFill(['fca_id'=>$fca->id,'phone'=>$this->phone,'phone_lookup'=>PresidentIdentity::digest($this->phone),'source_reference'=>'Synthetic source','status'=>'active','version'=>1,'valid_until'=>now()->addMonth()])->saveOrFail();
  return $this->service->match($this->owner(),$fca->id,$fca->official_name,$name,$this->phone,true);
 }
 private function decision(array $c,string $result='verified'):array{return ['result'=>$result,'outcome'=>$result==='verified'?'identity_confirmed':'identity_mismatch','decision_key'=>(string)Str::uuid(),'expected_version'=>$c['row_version'],'contact_version'=>1,'contacted_at'=>now()->toISOString(),'evidence'=>'Synthetic reviewer contact confirmation'];}
 private function assertDenied(callable $fn,int $status):void {try{$fn();$this->fail('Unsafe operation accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame($status,$e->getStatusCode());}}
 public function test_model_update_explicitly_preserves_original_consent():void {
  $c=$this->claim();$claim=FcaPresidentClaim::findOrFail($c['id']);$original=$claim->getRawOriginal('consented_at');
  $this->travel(2)->hours();$claim->status='requires_further_review';$claim->consented_at=now();
  $this->assertSame($original,$claim->getDirty()['consented_at']);$claim->saveOrFail();
  $this->assertSame($original,$claim->fresh()->getRawOriginal('consented_at'));
 }
 public function test_missing_consent_cannot_authorize_verification():void {
  $c=$this->claim();$this->service->trustedContact($this->admin(),$c['id'],1);
  $claim=FcaPresidentClaim::findOrFail($c['id']);$claim->consented_at=null;
  $this->assertFalse($this->service->usable($claim));$this->assertFalse($this->service->currentProof($claim));
  FcaPresidentClaim::findOrFail($c['id'])->forceFill(['notice_version'=>''])->saveOrFail();
  $this->assertDenied(fn()=>$this->service->manualReview($this->admin(),$c['id'],$this->decision($c)),422);
 }
 public function test_manual_verification_then_separate_authority_approval_persists_without_sms():void {
  $c=$this->claim();$this->assertSame('pending',$c['status']);$this->assertFalse($c['phone_verified']);$before=$this->owner()->getAttributes();
  $producerBefore=\App\Models\Producer::where('user_id',$this->owner()->getKey())->firstOrFail()->getAttributes();
  $this->assertSame($this->phone,$this->service->trustedContact($this->admin(),$c['id'],1)['trusted_phone']);
  $input=$this->decision($c);$v=$this->service->manualReview($this->admin(),$c['id'],$input);$this->assertSame('verified',$v['status']);$this->assertFalse($v['phone_verified']);$this->assertSame('pending',$v['authority_status']);
  $this->assertSame($v,$this->service->manualReview($this->admin(),$c['id'],$input));$this->assertSame(1,FcaManualDecision::count());
  $this->assertNotSame($input['evidence'],DB::table('fca_manual_decisions')->value('evidence'));$this->assertNull(FcaPresidentClaim::find($c['id'])->phone_verified_at);
  $receipt=$this->actingAs($this->owner())->postJson('/api/fca/evidence',['evidence'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf',"%PDF-1.4\nSynthetic\n%%EOF")])->assertCreated()->json('receipt');$this->inspectReceipt($receipt);
  $application=$this->service->submit($this->owner(),$c['id'],['representative_position'=>'President','authorization_statement'=>'Synthetic authority evidence','evidence_receipt'=>$receipt,'submission_key'=>(string)Str::uuid()]);
  $this->assertSame('pending',$application['status']);$this->assertFalse($application['phone_verified']);
  $approved=app(\App\Services\Mao\FcaApplicationService::class)->review($this->admin(),$application['id'],['decision'=>'authorize','expected_version'=>0,'decision_key'=>(string)Str::uuid(),'review_reason'=>'Synthetic separate authority decision','expires_at'=>now()->addMonth()->toISOString()]);
  $this->assertSame('authorized',$approved->status);$this->assertSame($before,$this->owner()->fresh()->getAttributes());$this->assertSame('not_assessed',FcaRegistry::first()->procurement_eligibility_status);Http::assertNothingSent();
  $this->assertSame($producerBefore,\App\Models\Producer::where('user_id',$this->owner()->getKey())->firstOrFail()->getAttributes());
 }
 public function test_manual_mismatch_cannot_be_verified_and_rejection_is_recorded():void {
  $c=$this->claim('Wrong identity');$this->assertSame('requires_further_review',$c['status']);$this->service->trustedContact($this->admin(),$c['id'],1);
  $this->assertDenied(fn()=>$this->service->manualReview($this->admin(),$c['id'],$this->decision($c)),422);
  $this->assertSame('rejected',$this->service->manualReview($this->admin(),$c['id'],$this->decision($c,'rejected'))['status']);$this->assertSame(1,FcaManualDecision::count());
 }
 public function test_manual_unauthorized_contact_access_and_sms_are_denied():void {
  $c=$this->claim();$this->assertDenied(fn()=>$this->service->trustedContact($this->owner(),$c['id'],1),403);
  config(['mao_workflows.fca_reviewer_user_ids'=>[]]);$this->assertDenied(fn()=>$this->service->trustedContact($this->admin(),$c['id'],1),403);
  $this->assertDenied(fn()=>$this->service->send($this->owner(),$c['id'],'synthetic'),404);Http::assertNothingSent();
 }
 public function test_manual_requires_actual_contact_and_rejects_stale_versions():void {
  $c=$this->claim();$this->assertDenied(fn()=>$this->service->manualReview($this->admin(),$c['id'],$this->decision($c)),422);
  $this->service->trustedContact($this->admin(),$c['id'],1);$bad=$this->decision($c);$bad['contacted_at']=now()->addDay()->toISOString();$this->assertDenied(fn()=>$this->service->manualReview($this->admin(),$c['id'],$bad),422);
  $this->service->manualReview($this->admin(),$c['id'],$this->decision($c));$this->assertDenied(fn()=>$this->service->manualReview($this->admin(),$c['id'],$this->decision($c)),409);
  FcaDirectoryContact::first()->forceFill(['version'=>2])->saveOrFail();
  DB::transaction(fn()=>$this->assertFalse($this->service->usable(FcaPresidentClaim::find($c['id']))));
 }
public function test_manual_audit_failure_rolls_back_decision_and_status():void {
   $c=$this->claim();$this->service->trustedContact($this->admin(),$c['id'],1);
   FcaPhoneEvent::creating(function ($model) { throw new \Illuminate\Database\QueryException('SQLSTATE[HY000]', 'INSERT INTO fca_phone_events', [], new \Exception('Synthetic audit failure')); });
   try{$this->service->manualReview($this->admin(),$c['id'],$this->decision($c));$this->fail('Unaudited verification');}catch(\Illuminate\Database\QueryException){$this->assertSame(0,FcaManualDecision::count());$this->assertSame('pending',FcaPresidentClaim::find($c['id'])->status);}
   FcaPhoneEvent::flushEventListeners();
 }
 public function test_manual_http_contract_masks_contact_and_requires_authorized_reviewer():void {
  $c=$this->claim();app('router')->middleware('api')->prefix('api')->group(base_path('routes/prepared/mao_fca_president.php'));
  // Match API-before-SPA ordering at a future approved rollout; retain all existing routes.
  $all=app('router')->getRoutes();$routes=new \Illuminate\Routing\RouteCollection();
  foreach($all as $route)if(str_contains($route->getActionName(),'FcaPresident'))$routes->add($route);
  foreach($all as $route)if(!str_contains($route->getActionName(),'FcaPresident'))$routes->add($route);
  app('router')->setRoutes($routes);
  app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($this->admin());
  app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($this->owner());
  $this->actingAs($this->owner())->postJson('/api/fca-president-review/claims/'.$c['id'].'/trusted-contact',['expected_version'=>1,'purpose'=>'manual_identity_verification'])->assertForbidden();
  $contact=$this->actingAs($this->admin())->postJson('/api/fca-president-review/claims/'.$c['id'].'/trusted-contact',['expected_version'=>1,'purpose'=>'manual_identity_verification'])->assertOk()->assertHeader('Cache-Control','no-store, private');
  $this->assertSame($this->phone,$contact->json('trusted_phone'));
  $result=$this->postJson('/api/fca-president-review/claims/'.$c['id'].'/manual-verification',$this->decision($c))->assertOk()->assertJsonPath('status','verified')->assertJsonPath('phone_verified',false);
  $this->assertStringNotContainsString($this->phone,$result->getContent());
  $show=$this->getJson('/api/fca-president-review/claims/'.$c['id'])->assertOk()->assertJsonPath('manual_verification_current',true)->assertJsonPath('phone_verified',false);
  $this->assertStringNotContainsString($this->phone,$show->getContent());
  $show->assertHeader('Cache-Control','no-store, private')->assertJsonPath('manual_review_history.0.result','verified')->assertJsonPath('manual_review_history.0.reviewer_id',(int)$this->admin()->getKey());
  $this->assertArrayNotHasKey('evidence',FcaManualDecision::first()->toArray());
 }
 public function test_manual_duplicate_claim_requires_resolution_before_verification():void {
  $c=$this->claim();$other=User::create(['first_name'=>'Synthetic','last_name'=>'Other','email'=>Str::uuid().'@example.test','password_hash'=>bcrypt('test-only'),'user_type'=>'Farmer','is_active'=>true]);
  \App\Models\Producer::create(['user_id'=>$other->getKey(),'verification_status'=>'Verified']);$fca=FcaRegistry::first();
  $duplicate=$this->service->match($other,$fca->id,$fca->official_name,'Synthetic president',$this->phone,true);$this->assertSame('requires_further_review',$duplicate['status']);
  $this->service->trustedContact($this->admin(),$c['id'],1);$this->assertDenied(fn()=>$this->service->manualReview($this->admin(),$c['id'],$this->decision($c)),409);
 }
public function test_manual_missing_ledger_stops_registration_before_writes():void {
   if (config('database.default') === 'mysql') {
       $this->markTestSkipped('SQLite-only test: dropping tables not supported with foreign keys');
   }
   config(['fca_president.workflow_enabled'=>true,'fca_president.notice_version'=>'synthetic.manual.notice']);
   \Illuminate\Support\Facades\Schema::drop('fca_manual_decisions'); // Guarded SQLite memory only.
   $fca=FcaRegistry::first();$this->assertDenied(fn()=>$this->service->match($this->owner(),$fca->id,$fca->official_name,'Synthetic president',$this->phone,true),503);
   $this->assertSame(0,FcaPresidentClaim::count());
 }
 public function test_deferred_otp_cannot_be_selected_outside_testing_environment():void {
  $c=$this->claim();config(['fca_president.verification_method'=>'otp_future','fca_president.sms_enabled'=>true]);
  $environment=$this->app['env'];$this->app['env']='production';
  try{
   $this->assertDenied(fn()=>$this->service->send($this->owner(),$c['id'],'synthetic'),503);
   $this->assertNull((new \App\Services\Mao\President\SemaphoreOtpDelivery())->send($this->phone,'123456'));
   Http::assertNothingSent();
  }finally{$this->app['env']=$environment;}
 }
 public function test_admin_requested_correction_resubmits_pending_preserves_history_and_requires_new_verification():void {
  $c=$this->claim('Wrong identity');$fca=FcaRegistry::first();
  $payload=['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>'Synthetic president','phone'=>$this->phone,'consent'=>true,'expected_version'=>1,'submission_key'=>(string)Str::uuid()];
  $this->assertDenied(fn()=>$this->service->resubmit($this->owner(),$c['id'],$payload),409);
  $request=$this->decision($c,'requires_further_review');$request['outcome']='identity_mismatch';$review=$this->service->manualReview($this->admin(),$c['id'],$request);$this->assertTrue($review['can_resubmit']);$payload['expected_version']=$review['row_version'];
  $originalConsent=FcaPresidentClaim::findOrFail($c['id'])->consented_at->toISOString();$this->travel(2)->hours();
  $result=$this->service->resubmit($this->owner(),$c['id'],$payload);$this->assertSame($originalConsent,FcaPresidentClaim::findOrFail($c['id'])->consented_at->toISOString());$this->assertSame('pending',$result['status']);$this->assertFalse($result['phone_verified']);$this->assertFalse($result['can_resubmit']);$this->assertSame('pending',$result['authority_status']);
  $this->assertSame($result,$this->service->resubmit($this->owner(),$c['id'],$payload));$this->assertSame(1,\App\Models\FcaClaimRevision::count());
  $history=\App\Models\FcaClaimRevision::first();$this->assertSame('Wrong identity',$history->previous_details['name']);$this->assertSame('Synthetic president',$history->submitted_details['name']);$this->assertArrayNotHasKey('previous_details',$history->toArray());
  $this->assertStringNotContainsString($this->phone,DB::table('fca_claim_revisions')->value('submitted_details'));
  $this->service->trustedContact($this->admin(),$c['id'],$result['row_version']);$verified=$this->service->manualReview($this->admin(),$c['id'],$this->decision($result));$this->assertSame('verified',$verified['status']);$this->assertSame('pending',$verified['authority_status']);
  $this->assertSame(2,FcaManualDecision::count());$this->assertSame(1,DB::table('fca_phone_events')->where('event','correction_resubmitted')->count());
 }
 public function test_correction_cannot_change_directory_or_bypass_ownership_and_review():void {
  $c=$this->claim('Wrong identity');$fca=FcaRegistry::first();$this->service->manualReview($this->admin(),$c['id'],$this->decision($c,'requires_further_review'));
  $payload=['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>'Still wrong','phone'=>$this->phone,'consent'=>true,'expected_version'=>2,'submission_key'=>(string)Str::uuid()];
  $before=$fca->getAttributes();$this->assertDenied(fn()=>$this->service->resubmit($this->admin(),$c['id'],$payload),403);
  $result=$this->service->resubmit($this->owner(),$c['id'],$payload);$this->assertSame('requires_further_review',$result['status']);$this->assertFalse($result['can_resubmit']);$this->assertSame($before,$fca->fresh()->getAttributes());
  $payload['expected_version']=3;$payload['submission_key']=(string)Str::uuid();$this->assertDenied(fn()=>$this->service->resubmit($this->owner(),$c['id'],$payload),409);
 }
 public function test_fca_selection_correction_creates_linked_claim_without_rewriting_original():void {
  $c=$this->claim('Wrong identity');$this->service->manualReview($this->admin(),$c['id'],$this->decision($c,'requires_further_review'));
  $fca=(new FcaRegistry())->forceFill(['official_name'=>'Different synthetic FCA','address'=>'Synthetic','president_name'=>'Different synthetic president','directory_verification_status'=>'verified','procurement_eligibility_status'=>'not_assessed']);$fca->saveOrFail();
  $phone='09'.str_pad((string)random_int(1,999999999),9,'0',STR_PAD_LEFT);
  (new FcaDirectoryContact())->forceFill(['fca_id'=>$fca->id,'phone'=>$phone,'phone_lookup'=>PresidentIdentity::digest($phone),'source_reference'=>'Synthetic source','status'=>'active','version'=>1,'valid_until'=>now()->addMonth()])->saveOrFail();
  $payload=['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>$fca->president_name,'phone'=>$phone,'consent'=>true,'expected_version'=>2,'submission_key'=>(string)Str::uuid()];
  $result=$this->service->resubmit($this->owner(),$c['id'],$payload);$this->assertNotSame($c['id'],$result['id']);$this->assertSame('pending',$result['status']);$this->assertSame($c['fca_id'],FcaPresidentClaim::find($c['id'])->fca_id);$this->assertSame('Wrong identity',FcaPresidentClaim::find($c['id'])->claimed_name);$this->assertSame($result['id'],\App\Models\FcaClaimRevision::first()->target_claim_id);
 }
public function test_correction_audit_failure_rolls_back_revision_and_original_details():void {
   $c=$this->claim('Wrong identity');$fca=FcaRegistry::first();$this->service->manualReview($this->admin(),$c['id'],$this->decision($c,'requires_further_review'));
   FcaPhoneEvent::creating(function ($model) { throw new \Illuminate\Database\QueryException('SQLSTATE[HY000]', 'INSERT INTO fca_phone_events', [], new \Exception('Synthetic audit failure')); });
   try{$this->service->resubmit($this->owner(),$c['id'],['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>'Synthetic president','phone'=>$this->phone,'consent'=>true,'expected_version'=>2,'submission_key'=>(string)Str::uuid()]);$this->fail('Unaudited correction');}catch(\Illuminate\Database\QueryException){$this->assertSame(0,\App\Models\FcaClaimRevision::count());$this->assertSame('Wrong identity',FcaPresidentClaim::find($c['id'])->claimed_name);$this->assertSame(2,FcaPresidentClaim::find($c['id'])->row_version);}
   FcaPhoneEvent::flushEventListeners();
 }
 public function test_pending_authority_application_allows_requested_same_fca_correction_without_rewriting_application():void {
  $c=$this->claim();$this->service->trustedContact($this->admin(),$c['id'],1);$verified=$this->service->manualReview($this->admin(),$c['id'],$this->decision($c));
  $receipt=$this->actingAs($this->owner())->postJson('/api/fca/evidence',['evidence'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf',"%PDF-1.4\nSynthetic\n%%EOF")])->assertCreated()->json('receipt');$this->inspectReceipt($receipt);
  $application=$this->service->submit($this->owner(),$c['id'],['representative_position'=>'President','authorization_statement'=>'Synthetic authority evidence','evidence_receipt'=>$receipt,'submission_key'=>(string)Str::uuid()]);
  $before=\App\Models\FcaRepresentative::find($application['id'])->getAttributes();$review=$this->service->manualReview($this->admin(),$c['id'],$this->decision($verified,'requires_further_review'));$this->assertTrue($review['can_resubmit']);$this->assertTrue($review['correction_fca_locked']);
  $fca=FcaRegistry::first();$input=['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>'Synthetic president','phone'=>$this->phone,'consent'=>true,'expected_version'=>$review['row_version'],'submission_key'=>(string)Str::uuid()];
  $different=$input;$different['fca_id']=999;$this->assertDenied(fn()=>$this->service->resubmit($this->owner(),$c['id'],$different),409);
  $pending=$this->service->resubmit($this->owner(),$c['id'],$input);$this->assertSame('pending',$pending['status']);$this->assertSame($before,\App\Models\FcaRepresentative::find($application['id'])->getAttributes());
  $this->assertDenied(fn()=>app(\App\Services\Mao\FcaApplicationService::class)->review($this->admin(),$application['id'],['decision'=>'authorize','expected_version'=>0,'decision_key'=>(string)Str::uuid(),'review_reason'=>'Synthetic premature approval','expires_at'=>now()->addMonth()->toISOString()]),422);
 }
 public function test_manual_frontend_http_contract_through_correction_and_separate_authority_approval():void {
  $c=$this->claim('Wrong identity');$fca=FcaRegistry::first();
  app('router')->middleware('api')->prefix('api')->group(base_path('routes/prepared/mao_fca_president.php'));
  $all=app('router')->getRoutes();$routes=new \Illuminate\Routing\RouteCollection();foreach($all as $route)if(str_contains($route->getActionName(),'FcaPresident'))$routes->add($route);foreach($all as $route)if(!str_contains($route->getActionName(),'FcaPresident'))$routes->add($route);app('router')->setRoutes($routes);
  foreach([$this->owner(),$this->admin()] as $actor)app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($actor);
  $directory=$this->actingAs($this->owner())->getJson('/api/fca-president/directory')->assertOk()->json();
  $this->assertArrayNotHasKey('phone',$directory['data'][0]);$this->assertArrayNotHasKey('president_name',$directory['data'][0]);
  $request=$this->decision($c,'requires_further_review');
  $correctionRequested=$this->actingAs($this->admin())->postJson('/api/fca-president-review/claims/'.$c['id'].'/manual-verification',$request)->assertOk()->assertJsonPath('can_resubmit',true)->json();
  $pending=$this->actingAs($this->owner())->postJson('/api/fca-president/claims/'.$c['id'].'/resubmit',['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>'Synthetic president','phone'=>$this->phone,'consent'=>true,'expected_version'=>2,'submission_key'=>(string)Str::uuid()])->assertOk()->assertJsonPath('status','pending')->json();
  $this->postJson('/api/fca-president/claims/'.$c['id'].'/resubmit',['fca_id'=>$fca->id,'association'=>$fca->official_name,'name'=>'Synthetic president','phone'=>$this->phone,'consent'=>true,'expected_version'=>3,'submission_key'=>(string)Str::uuid(),'status'=>'verified'])->assertUnprocessable();
  $pendingReview=$this->actingAs($this->admin())->getJson('/api/fca-president-review/claims/'.$c['id'])->assertOk()->json();
  $this->postJson('/api/fca-president-review/claims/'.$c['id'].'/trusted-contact',['expected_version'=>3,'purpose'=>'manual_identity_verification'])->assertOk()->assertJsonPath('trusted_phone',$this->phone);
  $verified=$this->postJson('/api/fca-president-review/claims/'.$c['id'].'/manual-verification',$this->decision($pending))->assertOk()->assertJsonPath('status','verified')->assertJsonPath('authority_status','pending')->json();
  $review=$this->getJson('/api/fca-president-review/claims/'.$c['id'])->assertOk()->json();
  $this->getJson('/api/fca-president-review/claims')->assertOk()->assertJsonCount(1,'data');
  $this->actingAs($this->owner())->getJson('/api/fca-president/claims')->assertOk()->assertJsonCount(1,'data');
  $upload=$this->postJson('/api/fca/evidence',['evidence'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.pdf',"%PDF-1.4\nSynthetic\n%%EOF")])->assertCreated()->json();$receipt=$upload['receipt'];
  $this->getJson('/api/fca/evidence/'.$receipt.'/status')->assertOk()->assertJsonPath('inspection_ready',false);
  $this->actingAs($this->admin())->getJson('/api/fca/evidence/'.$receipt.'/status')->assertForbidden();
  $this->actingAs($this->owner());$this->inspectReceipt($receipt);
  $inspection=$this->getJson('/api/fca/evidence/'.$receipt.'/status')->assertOk()->assertJsonPath('inspection_ready',true)->json();
  $application=$this->postJson('/api/fca-president/claims/'.$c['id'].'/application',['representative_position'=>'President','authorization_statement'=>'Synthetic authority evidence','evidence_receipt'=>$receipt,'submission_key'=>(string)Str::uuid()])->assertOk()->assertJsonPath('status','pending')->json('id');
  $pendingApplication=$this->getJson('/api/fca/applications/'.$application)->assertOk()->json('data');
  $approved=$this->actingAs($this->admin())->postJson('/api/fca/review/'.$application,['decision'=>'authorize','expected_version'=>0,'decision_key'=>(string)Str::uuid(),'review_reason'=>'Synthetic explicit authority approval','expires_at'=>now()->addMonth()->toISOString()])->assertOk()->assertJsonPath('data.status','authorized')->json('data');
  $approvedClaim=$this->actingAs($this->owner())->getJson('/api/fca-president/claims/'.$c['id'])->assertOk()->json();
  $this->assertStringNotContainsString($this->phone,json_encode([$directory,$pending,$verified,$review,$inspection,$pendingApplication,$approved]));Http::assertNothingSent();
  // Optional local-only contract handoff to TypeScript checks, never used by the real UI.
  if($output=getenv('FCA_MANUAL_CONTRACT_OUTPUT')){
   $parent=realpath(dirname($output));$root=realpath(storage_path('framework/testing'));if(!$parent||!$root||!str_starts_with(strtolower($parent.'\\'),strtolower($root.'\\'))||file_exists($output))throw new \LogicException('Unsafe contract artifact path.');
   $handle=fopen($output,'x');try{fwrite($handle,json_encode(compact('directory','correctionRequested','pending','pendingReview','verified','review','upload','inspection','pendingApplication','approved','approvedClaim'),JSON_THROW_ON_ERROR));}finally{fclose($handle);}
  }
 }
}
