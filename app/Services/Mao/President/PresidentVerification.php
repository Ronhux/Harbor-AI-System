<?php
namespace App\Services\Mao\President;
use App\Models\{User,Producer,FcaRegistry,FcaDirectoryContact,FcaPresidentClaim,FcaOtpChallenge,FcaRepresentative,FcaPhoneEvent};
use App\Support\Deadline;
use App\Policies\MaoIdentityPolicy;
use App\Services\Mao\{MaoLockPlan,PreparedWorkflowTransaction,FcaApplicationService};
use Illuminate\Support\Facades\{DB,RateLimiter};
use Illuminate\Support\Str;
final class PresidentVerification {
 private OtpDelivery $delivery;
 public function __construct(?OtpDelivery $delivery=null){if($delivery!==null&&!app()->environment('testing'))throw new \LogicException('Test adapter prohibited.');$this->delivery=$delivery??new SemaphoreOtpDelivery();}
 private function futureOtpAllowed():bool {return app()->environment('testing')&&config('fca_president.verification_method')==='otp_future';}
 private function ready(User $user):void {
  abort_unless(config('fca_president.workflow_enabled')===true,404);
  abort_unless(config('fca_president.verification_method')==='manual'||$this->futureOtpAllowed(),503);
  foreach(['fca_directory_contacts','fca_president_claims','fca_otp_challenges','fca_phone_events','fca_phone_locks'] as $table)abort_unless(\Illuminate\Support\Facades\Schema::hasTable($table),503,'Verification dependencies unavailable.');
  if(config('fca_president.verification_method')==='manual')abort_unless(\Illuminate\Support\Facades\Schema::hasTable('fca_manual_decisions'),503,'Manual verification history unavailable.');
  abort_unless($user->is_active===true&&in_array($user->user_type,['Farmer','Fisherfolk','Producer'],true)&&Producer::where('user_id',$user->getKey())->exists(),403);
 }
 private function secret():string {$key=config('fca_president.verifier_key');abort_unless(is_string($key)&&strlen($key)>=32,503,'Verification unavailable.');return $key;}
 private function audit(User $user,FcaPresidentClaim $claim,string $event):void {FcaPhoneEvent::create(['actor_user_id'=>$user->getKey(),'claim_id'=>$claim->id,'event'=>$event]);}
 private function locks(User $user,int $fca):void {MaoLockPlan::acquire(['actor'=>$user->getKey(),'fca'=>$fca]);abort_unless($user->fresh()?->is_active===true&&in_array($user->fresh()->user_type,['Farmer','Fisherfolk','Producer'],true),403);}
 public function match(User $user,int $fcaId,string $association,string $name,string $phone,bool $consent):array {
  $this->ready($user);abort_unless($consent,422);$notice=config('fca_president.notice_version');abort_unless(is_string($notice)&&$notice!==''&&strlen($notice)<=100,503,'Approved notice unavailable.');$canonical=PresidentIdentity::phone($phone);$identity=PresidentIdentity::name($name);$associationKey=PresidentIdentity::name($association);
  return PreparedWorkflowTransaction::run(function()use($user,$fcaId,$associationKey,$identity,$canonical,$name,$phone,$notice){
   $this->locks($user,$fcaId);$fca=FcaRegistry::findOrFail($fcaId);
   $existing=FcaPresidentClaim::where(['applicant_user_id'=>$user->getKey(),'fca_id'=>$fcaId])->lockForUpdate()->first();if($existing)return $this->summary($existing);
   [$matches,$contact]=$this->directoryMatch($fca,$associationKey,$identity,$canonical);
   $claim=(new FcaPresidentClaim())->forceFill(['applicant_user_id'=>$user->getKey(),'fca_id'=>$fcaId,'consented_at'=>now(),'notice_version'=>$notice,'claimed_name'=>$name,'claimed_phone'=>$phone,'status'=>$matches?(config('fca_president.verification_method')==='manual'?'pending':'phone_required'):(config('fca_president.verification_method')==='manual'?'requires_further_review':'manual_review'),'row_version'=>1,'directory_contact_id'=>$matches?$contact->id:null,'directory_version'=>$matches?$contact->version:null]);$claim->saveOrFail();$this->audit($user,$claim,'claim_pending');return $this->summary($claim);
  });
 }
 private function directoryMatch(FcaRegistry $fca,?string $association,?string $name,?string $phone,?int $except=null):array {
  $snapshot=FcaDirectoryContact::where('fca_id',$fca->id)->first();if($snapshot)PhoneMutex::lock($snapshot->phone_lookup);
  $contact=FcaDirectoryContact::where('fca_id',$fca->id)->lockForUpdate()->first();
  $matches=$phone&&$name&&$association&&$fca->directory_verification_status==='verified'&&$contact&&$contact->status==='active'&&Deadline::active($contact->valid_until)
   &&PresidentIdentity::name($fca->official_name)===$association&&PresidentIdentity::name($fca->president_name)===$name
   &&hash_equals($contact->phone_lookup,PresidentIdentity::digest($phone))&&PresidentIdentity::phone($contact->phone)===$phone;
  if($matches){$names=FcaRegistry::get(['official_name'])->filter(fn($row)=>PresidentIdentity::name($row->official_name)===$association)->count();$phones=FcaDirectoryContact::where('phone_lookup',$contact->phone_lookup)->where('status','active')->count();$other=FcaPresidentClaim::where('fca_id',$fca->id)->whereIn('status',['pending','verified','requires_further_review','phone_required','phone_verified']);if($except)$other->where('id','!=',$except);$matches=$names===1&&$phones===1&&!$other->exists();}
  return [(bool)$matches,$contact];
 }
 public function directory(User $actor) {$this->ready($actor);return FcaRegistry::where('directory_verification_status','verified')->select(['id','official_name','address'])->orderBy('official_name')->paginate(20);}
 public function mine(User $actor) {$this->ready($actor);return FcaPresidentClaim::where('applicant_user_id',$actor->getKey())->orderByDesc('id')->paginate(20)->through(fn($c)=>$this->summary($c));}
 public function reviewQueue(User $actor) {
  abort_unless(config('fca_president.workflow_enabled')===true&&app(MaoIdentityPolicy::class)->administrator($actor,'fca_reviewer_user_ids'),403);
  return FcaPresidentClaim::whereIn('status',['pending','requires_further_review','verified'])->orderBy('id')->paginate(20)->through(fn($c)=>$this->summary($c));
 }
 public function resubmit(User $actor,int $id,array $input):array {
  $this->ready($actor);abort_unless(config('fca_president.verification_method')==='manual'&&\Illuminate\Support\Facades\Schema::hasTable('fca_claim_revisions'),503);
  \Illuminate\Support\Facades\Validator::make($input,['fca_id'=>'required|integer|min:1','association'=>'required|string|max:255','name'=>'required|string|max:255','phone'=>'required|string|max:40','consent'=>'required|accepted','expected_version'=>'required|integer|min:1','submission_key'=>'required|uuid'])->validate();
  abort_if(array_diff(array_keys($input),['fca_id','association','name','phone','consent','expected_version','submission_key']),422);
  $ref=$this->own($actor,$id);$repSnapshot=FcaRepresentative::where('president_claim_id',$ref->id)->first();$targetId=(int)$input['fca_id'];$canonical=['fca_id'=>$targetId,'association'=>$input['association'],'name'=>$input['name'],'phone'=>$input['phone'],'consent'=>true,'expected_version'=>(int)$input['expected_version'],'submission_key'=>$input['submission_key']];$hash=PresidentIdentity::digest(json_encode($canonical,JSON_THROW_ON_ERROR),'claim_revision');
  return PreparedWorkflowTransaction::run(function()use($actor,$ref,$repSnapshot,$input,$targetId,$hash){
   MaoLockPlan::acquire(['actor'=>$actor->getKey(),'fcas'=>[$ref->fca_id,$targetId],'representative'=>$repSnapshot?->id]);$this->ready($actor->fresh());$c=$this->checked($actor,$ref);
   $old=\App\Models\FcaClaimRevision::where('submission_key',$input['submission_key'])->first();
   if($old){abort_unless($old->claim_id===$c->id&&$old->actor_id===(int)$actor->getKey()&&hash_equals($old->payload_hash,$hash),409);return $this->summary(FcaPresidentClaim::findOrFail($old->target_claim_id));}
   abort_unless($c->row_version===(int)$input['expected_version']&&$c->status==='requires_further_review',409);
   $review=\App\Models\FcaManualDecision::where('claim_id',$c->id)->latest('id')->first();
   abort_unless($review?->result==='requires_further_review',409,'Administrator must request correction first.');
   abort_if(\App\Models\FcaClaimRevision::where('review_decision_id',$review->id)->exists(),409,'A new correction request is required.');
   $rep=FcaRepresentative::where('president_claim_id',$c->id)->first();abort_unless($rep?->id===$repSnapshot?->id,409,'Authority context changed.');
   abort_if($rep&&($rep->status!=='pending'||$targetId!==$c->fca_id),409,'An existing authority decision or association change requires separate review.');
   $before=['fca_id'=>$c->fca_id,'name'=>$c->claimed_name,'phone'=>$c->claimed_phone,'status'=>$c->status,'directory_contact_id'=>$c->directory_contact_id,'directory_version'=>$c->directory_version,'consented_at'=>$c->consented_at,'notice_version'=>$c->notice_version];$version=$c->row_version;
   if($targetId!==$c->fca_id){
    abort_if(FcaPresidentClaim::where(['applicant_user_id'=>$actor->getKey(),'fca_id'=>$targetId])->exists(),409,'Target association already has a claim.');
    $result=$this->match($actor,$targetId,$input['association'],$input['name'],$input['phone'],true);$target=FcaPresidentClaim::findOrFail($result['id']);
    // Preserve the old association claim and decision. Admin can resolve it independently.
    $c->row_version++;$c->saveOrFail();
   }else{
    $fca=FcaRegistry::findOrFail($targetId);[$matched,$contact]=$this->directoryMatch($fca,PresidentIdentity::name($input['association']),PresidentIdentity::name($input['name']),PresidentIdentity::phone($input['phone']),$c->id);
    $notice=config('fca_president.notice_version');abort_unless(is_string($notice)&&$notice!==''&&strlen($notice)<=100,503);
    $c->forceFill(['claimed_name'=>$input['name'],'claimed_phone'=>$input['phone'],'consented_at'=>$c->consented_at,'notice_version'=>$c->notice_version,'status'=>$matched?'pending':'requires_further_review','directory_contact_id'=>$matched?$contact->id:null,'directory_version'=>$matched?$contact->version:null,'phone_verified_at'=>null,'proof_expires_at'=>null,'row_version'=>$version+1])->saveOrFail();$target=$c;
   }
   (new \App\Models\FcaClaimRevision())->forceFill(['claim_id'=>$c->id,'target_claim_id'=>$target->id,'actor_id'=>$actor->getKey(),'review_decision_id'=>$review->id,'submission_key'=>$input['submission_key'],'payload_hash'=>$hash,'previous_version'=>$version,'previous_details'=>$before,'submitted_details'=>['fca_id'=>$targetId,'association'=>$input['association'],'name'=>$input['name'],'phone'=>$input['phone']]])->saveOrFail();
   $this->audit($actor,$c,'correction_resubmitted');return $this->summary($target);
  });
 }
 private function contact(FcaPresidentClaim $claim):?FcaDirectoryContact {
  $snapshot=FcaDirectoryContact::find($claim->directory_contact_id);if($snapshot)PhoneMutex::lock($snapshot->phone_lookup);
  $row=FcaDirectoryContact::whereKey($claim->directory_contact_id)->lockForUpdate()->first();$fca=FcaRegistry::find($claim->fca_id);
  return $row&&$snapshot&&hash_equals($row->phone_lookup,$snapshot->phone_lookup)&&$row->status==='active'&&Deadline::active($row->valid_until)&&$row->version===$claim->directory_version&&$row->fca_id===$claim->fca_id
   &&$fca?->directory_verification_status==='verified'&&PresidentIdentity::name($fca->president_name)===PresidentIdentity::name($claim->claimed_name)
   &&PresidentIdentity::phone($row->phone)===PresidentIdentity::phone($claim->claimed_phone)
   &&hash_equals($row->phone_lookup,PresidentIdentity::digest($row->phone))
   &&FcaDirectoryContact::where('phone_lookup',$row->phone_lookup)->where('status','active')->count()===1?$row:null;
 }
 private function own(User $user,int $id):FcaPresidentClaim {$claim=FcaPresidentClaim::findOrFail($id);abort_unless((int)$claim->applicant_user_id===(int)$user->getKey(),403);return $claim;}
 private function checked(User $user,FcaPresidentClaim $snapshot):FcaPresidentClaim {
  $current=FcaPresidentClaim::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
  abort_unless((int)$current->applicant_user_id===(int)$user->getKey()&&(int)$current->fca_id===(int)$snapshot->fca_id,409,'Verification context changed.');return $current;
 }
 public function show(User $user,int $id):array {$this->ready($user);$c=$this->own($user,$id);return PreparedWorkflowTransaction::run(function()use($user,$c){$this->locks($user,$c->fca_id);$c=$this->checked($user,$c);if(config('fca_president.verification_method')==='manual'&&$c->status==='verified'&&!$this->manualProof($c)){$c->status='requires_further_review';$c->row_version++;$c->saveOrFail();$this->audit($user,$c,'manual_proof_stale');}if(in_array($c->status,['phone_required','phone_verified'],true)&&(!$this->contact($c)||($c->status==='phone_verified'&&(!$c->proof_expires_at||$c->proof_expires_at->isPast())))){$c->status='manual_review';$c->row_version++;$c->saveOrFail();$this->audit($user,$c,'proof_stale');}return $this->summary($c);});}
 public function send(User $user,int $id,string $ip):array {
  $this->ready($user);abort_unless($this->futureOtpAllowed(),404);abort_unless(config('fca_president.sms_enabled')===true,503,'SMS unavailable.');$secret=$this->secret();$c=$this->own($user,$id);
  $limit=config('fca_president.hourly_send_limit');$cooldown=config('fca_president.cooldown_seconds');$expiry=config('fca_president.expiry_seconds');
  abort_unless(is_int($limit)&&$limit>0&&$limit<=10&&is_int($cooldown)&&$cooldown>=30&&is_int($expiry)&&$expiry>=60&&$expiry<=600,503);
  $rate='fca-otp-ip:'.hash_hmac('sha256',$ip,$secret);abort_if(RateLimiter::tooManyAttempts($rate,$limit*5),429);RateLimiter::hit($rate,3600);
  [$challenge,$target,$code]=PreparedWorkflowTransaction::run(function()use($user,$c,$limit,$cooldown,$expiry,$secret){
   $this->locks($user,$c->fca_id);$claim=$this->checked($user,$c);$contact=$this->contact($claim);
   abort_unless($claim->status==='phone_required'&&$claim->notice_version===config('fca_president.notice_version')&&$contact,409,'Manual review required.');
   // Parent user/FCA locks serialize recipient/account quotas and duplicate claims.
   $claimIds=FcaPresidentClaim::where('applicant_user_id',$user->getKey())->orWhere('fca_id',$claim->fca_id)->pluck('id');
   $history=FcaOtpChallenge::whereIn('claim_id',$claimIds)->where('created_at','>=',now()->subHour());abort_if((clone $history)->count()>=$limit,429);abort_if((clone $history)->where('created_at','>',now()->subSeconds($cooldown))->exists(),429);
   FcaOtpChallenge::where('claim_id',$claim->id)->whereIn('status',['sending','pending'])->update(['status'=>'superseded']);
   $id=(string)Str::uuid();$code=(string)random_int(100000,999999);$verifier=hash_hmac('sha256',$id.'|'.$claim->id.'|'.$claim->directory_version.'|'.$code,$secret);
   $challenge=(new FcaOtpChallenge())->forceFill(['id'=>$id,'claim_id'=>$claim->id,'verifier'=>$verifier,'status'=>'sending','attempts'=>0,'expires_at'=>now()->addSeconds($expiry)]);$challenge->saveOrFail();$this->audit($user,$claim,'otp_requested');return [$challenge,$contact->phone,$code];
  });
  // Network outside locks; no automatic retries. Raw code lives only in transient memory/HTTPS body.
  $reference=$this->delivery->send($target,$code);unset($target,$code);
  return PreparedWorkflowTransaction::run(function()use($user,$c,$challenge,$reference){$this->locks($user,$c->fca_id);$claim=$this->checked($user,$c);$valid=$this->contact($claim);$row=FcaOtpChallenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();$ok=$row->status==='sending'&&Deadline::active($row->expires_at)&&$valid&&$reference!==null;$row->status=$ok?'pending':'failed';$row->provider_reference=$ok?$reference:null;$row->saveOrFail();$this->audit($user,$claim,$ok?'otp_accepted':'otp_unavailable');return ['challenge_id'=>$row->id,'status'=>$ok?'code_required':'unavailable','phone_verified'=>false];});
 }
 public function verify(User $user,string $challengeId,string $code):array {
  $this->ready($user);abort_unless($this->futureOtpAllowed(),404);abort_unless(preg_match('/\A\d{6}\z/',$code),422);$secret=$this->secret();$ref=FcaOtpChallenge::findOrFail($challengeId);$claim=$this->own($user,$ref->claim_id);
  $max=config('fca_president.max_attempts');abort_unless(is_int($max)&&$max>0&&$max<=10,503);
  return PreparedWorkflowTransaction::run(function()use($user,$claim,$ref,$code,$secret,$max){
   $this->locks($user,$claim->fca_id);$c=$this->checked($user,$claim);$contact=$this->contact($c);$otp=FcaOtpChallenge::whereKey($ref->id)->lockForUpdate()->firstOrFail();
   if($c->notice_version!==config('fca_president.notice_version')||!$contact||$c->status!=='phone_required'||$otp->status!=='pending'||!Deadline::active($otp->expires_at)||$otp->attempts>=$max)return ['status'=>'verification_unavailable','phone_verified'=>false];
   $otp->attempts++;$expected=hash_hmac('sha256',$otp->id.'|'.$c->id.'|'.$c->directory_version.'|'.$code,$secret);$matched=hash_equals($otp->verifier,$expected);
   if($matched){$otp->status='consumed';$otp->consumed_at=now();$c->status='phone_verified';$c->row_version++;$c->phone_verified_at=now();abort_unless($contact&&Deadline::active($contact->valid_until),409,'Directory contact validity unavailable.');$c->proof_expires_at=$contact->valid_until;$c->saveOrFail();$this->audit($user,$c,'phone_verified');}
   elseif($otp->attempts>=$max){$otp->status='locked';$this->audit($user,$c,'otp_locked');}
   $otp->saveOrFail();return ['status'=>$matched?'phone_verified':'code_rejected','phone_verified'=>$matched,'authority_status'=>'pending'];
  });
 }
 public function submit(User $user,int $id,array $input):array {
  $this->ready($user);$c=$this->own($user,$id);
  // Existing approved-account/evidence policy remains mandatory; identity verification alone is insufficient.
  return PreparedWorkflowTransaction::run(function()use($user,$c,$input){
   $producer=app(FcaApplicationService::class)->applicant($user);
   MaoLockPlan::acquire(['actor'=>$user->getKey(),'producer'=>$producer->getKey(),'fca'=>$c->fca_id,'fca_pair'=>true,'evidence'=>$input['evidence_receipt']??null,'evidence_optional'=>true]);
   $claim=$this->checked($user,$c);abort_unless($this->usable($claim),409);
   $app=app(FcaApplicationService::class)->submit($user,$c->fca_id,$input+['representative_kind'=>'president'],$claim);abort_unless($app->status==='pending'&&(!$app->president_claim_id||$app->president_claim_id===$c->id),409);
   $app->forceFill(['president_claim_id'=>$c->id])->saveOrFail();$this->audit($user,$claim,'authority_pending');return ['id'=>$app->id,'status'=>'pending','phone_verified'=>config('fca_president.verification_method')!=='manual','verification_method'=>config('fca_president.verification_method')];
  });
 }
 public function currentProof(FcaPresidentClaim $claim):bool {if(!$claim->consented_at||!$claim->notice_version)return false;if(config('fca_president.verification_method')==='manual')return $this->manualProof($claim);if(!$this->futureOtpAllowed())return false;return $claim->status==='phone_verified'&&$claim->phone_verified_at&&$this->contact($claim)!==null;}
 public function usable(FcaPresidentClaim $claim):bool {if(!$claim->consented_at||!$claim->notice_version)return false;if(config('fca_president.verification_method')==='manual')return $this->manualProof($claim);if(!$this->futureOtpAllowed())return false;return $claim->status==='phone_verified'&&$claim->phone_verified_at&&$claim->proof_expires_at?->isFuture()&&$this->contact($claim)!==null;}
 public function reviewClaim(User $actor,int $id,string $decision,int $expectedVersion):array {
  abort_unless($this->futureOtpAllowed(),404); // Manual results require the audited decision contract.
  abort_unless(config('fca_president.workflow_enabled')===true&&app(MaoIdentityPolicy::class)->administrator($actor,'fca_reviewer_user_ids'),403);
  abort_unless(in_array($decision,['reject','dispute','outdated','revoke'],true),422);$ref=FcaPresidentClaim::findOrFail($id);
  return PreparedWorkflowTransaction::run(function()use($actor,$ref,$decision,$expectedVersion){MaoLockPlan::acquire(['actor'=>$actor->getKey(),'users'=>[$ref->applicant_user_id],'fca'=>$ref->fca_id]);abort_unless(app(MaoIdentityPolicy::class)->administrator($actor->fresh(),'fca_reviewer_user_ids')&&(int)$actor->getKey()!==(int)$ref->applicant_user_id,403);$c=FcaPresidentClaim::whereKey($ref->id)->lockForUpdate()->firstOrFail();abort_unless($c->row_version===$expectedVersion,409);$c->row_version++;$c->status=match($decision){'reject'=>'rejected','revoke'=>'revoked',default=>'manual_review'};$c->saveOrFail();FcaOtpChallenge::where('claim_id',$c->id)->whereIn('status',['pending','sending'])->update(['status'=>'superseded']);$this->audit($actor,$c,'claim_'.$decision);return $this->summary($c);});
 }
 private function manualProof(FcaPresidentClaim $claim):bool {
  if(!\Illuminate\Support\Facades\Schema::hasTable('fca_manual_decisions')||$claim->status!=='verified')return false;
  $decision=\App\Models\FcaManualDecision::where('claim_id',$claim->id)->latest('id')->first();
  return $decision?->result==='verified'&&$decision->contacted_at!==null&&$decision->contact_id===$claim->directory_contact_id&&$decision->contact_version===$claim->directory_version&&$this->contact($claim)!==null;
 }
 private function reviewer(User $actor,FcaPresidentClaim $ref):FcaPresidentClaim {
  abort_unless(config('fca_president.workflow_enabled')===true&&config('fca_president.verification_method')==='manual',404);
  abort_unless(\Illuminate\Support\Facades\Schema::hasTable('fca_manual_decisions'),503);
  MaoLockPlan::acquire(['actor'=>$actor->getKey(),'users'=>[$ref->applicant_user_id],'fca'=>$ref->fca_id]);
  abort_unless(app(MaoIdentityPolicy::class)->administrator($actor->fresh(),'fca_reviewer_user_ids')&&(int)$actor->getKey()!==(int)$ref->applicant_user_id,403);
  $c=FcaPresidentClaim::whereKey($ref->id)->lockForUpdate()->firstOrFail();
  abort_unless($c->fca_id===$ref->fca_id&&$c->applicant_user_id===$ref->applicant_user_id,409);return $c;
 }
 /** Purpose-bound confidential access; never part of general claim serialization. */
 public function trustedContact(User $actor,int $id,int $version):array {
  $ref=FcaPresidentClaim::findOrFail($id);
  return PreparedWorkflowTransaction::run(function()use($actor,$ref,$version){
   $c=$this->reviewer($actor,$ref);abort_unless($c->row_version===$version,409);
   $contact=FcaDirectoryContact::where('fca_id',$c->fca_id)->first();
   if($contact)PhoneMutex::lock($contact->phone_lookup);
   $contact=FcaDirectoryContact::where('fca_id',$c->fca_id)->lockForUpdate()->first();
   abort_unless($contact&&$contact->status==='active'&&Deadline::active($contact->valid_until)&&FcaRegistry::find($c->fca_id)?->directory_verification_status==='verified',409,'Current directory contact unavailable.');
   $phone=PresidentIdentity::phone($contact->phone);abort_unless($phone&&hash_equals($contact->phone_lookup,PresidentIdentity::digest($phone)),409);
   $this->audit($actor,$c,'manual_contact_access');return ['trusted_phone'=>$phone,'contact_version'=>$contact->version];
  });
 }
 public function manualReview(User $actor,int $id,array $input):array {
  abort_unless(in_array($input['result']??null,['verified','rejected','requires_further_review'],true)&&Str::isUuid($input['decision_key']??'')&&is_int($input['expected_version']??null),422);
  abort_unless(in_array($input['outcome']??null,['identity_confirmed','identity_mismatch','unreachable','outdated_record','ambiguous_record'],true)&&is_string($input['evidence']??null)&&strlen(trim($input['evidence']))>=10&&strlen($input['evidence'])<=2000,422);
  $ref=FcaPresidentClaim::findOrFail($id);$hash=hash('sha256',json_encode([$id,$input['result'],$input['outcome'],$input['expected_version'],$input['contact_version']??null,$input['contacted_at']??null,$input['evidence']],JSON_THROW_ON_ERROR));
  return PreparedWorkflowTransaction::run(function()use($actor,$ref,$input,$hash){
   $c=$this->reviewer($actor,$ref);
   $old=\App\Models\FcaManualDecision::where('decision_key',$input['decision_key'])->first();
   if($old){abort_unless($old->claim_id===$c->id&&$old->reviewer_id===(int)$actor->getKey()&&hash_equals($old->decision_hash,$hash),409);return $this->summary($c);}
   abort_unless($c->row_version===$input['expected_version']&&in_array($c->status,['pending','requires_further_review','verified'],true),409);
   $contact=$this->contact($c);$at=null;
   if(isset($input['contacted_at'])){try{$at=\Illuminate\Support\Carbon::parse($input['contacted_at']);}catch(\Throwable){abort(422);}abort_unless($at->lessThanOrEqualTo(now())&&$at->greaterThanOrEqualTo($c->created_at),422);}
   if($input['result']==='verified'){
    abort_unless($c->consented_at && $c->notice_version,422,'Explicit original consent is required.');
    abort_unless($contact&&$input['outcome']==='identity_confirmed'&&$at&&($input['contact_version']??null)===$contact->version,422,'Current trusted contact and actual contact evidence required.');
    abort_if(FcaPresidentClaim::where('fca_id',$c->fca_id)->where('id','!=',$c->id)->whereIn('status',['pending','verified','requires_further_review'])->exists(),409,'Competing claim requires resolution.');
    abort_unless(DB::table('fca_phone_events')->where(['claim_id'=>$c->id,'actor_user_id'=>$actor->getKey(),'event'=>'manual_contact_access'])->exists(),422,'Trusted contact access required.');
   }else{abort_if($input['outcome']==='identity_confirmed',422);}
   (new \App\Models\FcaManualDecision())->forceFill(['claim_id'=>$c->id,'reviewer_id'=>$actor->getKey(),'decision_key'=>$input['decision_key'],'decision_hash'=>$hash,'result'=>$input['result'],'outcome'=>$input['outcome'],'contact_id'=>$contact?->id,'contact_version'=>$contact?->version,'contacted_at'=>$at,'evidence'=>$input['evidence']])->saveOrFail();
   $c->status=$input['result'];$c->row_version++;$c->saveOrFail();$this->audit($actor,$c,'manual_'.$input['result']);return $this->summary($c);
  });
 }
 public function summary(FcaPresidentClaim $c):array {
  $revisionReady=\Illuminate\Support\Facades\Schema::hasTable('fca_claim_revisions');$review=\Illuminate\Support\Facades\Schema::hasTable('fca_manual_decisions')?\App\Models\FcaManualDecision::where('claim_id',$c->id)->latest('id')->first():null;
  $rep=FcaRepresentative::where('president_claim_id',$c->id)->first();
  $can=$revisionReady&&$c->status==='requires_further_review'&&$review?->result==='requires_further_review'&&(!$rep||$rep->status==='pending')&&!\App\Models\FcaClaimRevision::where('review_decision_id',$review->id)->exists();
  return ['id'=>$c->id,'row_version'=>$c->row_version,'fca_id'=>$c->fca_id,'status'=>$c->status,'can_resubmit'=>(bool)$can,'correction_fca_locked'=>$rep!==null,'authority_application_id'=>$rep?->id,'verification_method'=>config('fca_president.verification_method'),'phone_verified'=>config('fca_president.verification_method')!=='manual'&&$c->status==='phone_verified'&&$c->proof_expires_at?->isFuture(),'phone_display'=>'Confidential MAO number','authority_status'=>FcaRepresentative::where('president_claim_id',$c->id)->value('status')??'pending'];}
}
