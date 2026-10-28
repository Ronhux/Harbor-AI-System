<?php
namespace App\Http\Controllers;
use App\Models\FcaPresidentClaim;
use App\Policies\MaoIdentityPolicy;
use App\Services\Mao\President\PresidentVerification;
use Illuminate\Http\Request;
final class FcaPresidentReviewController extends Controller {
 public function pending(Request $r,PresidentVerification $s){return response()->json($s->reviewQueue($r->user()))->header('Cache-Control','no-store, private');}
 public function show(Request $r,int $claim,PresidentVerification $s){
  abort_unless(config('fca_president.workflow_enabled')===true&&app(MaoIdentityPolicy::class)->administrator($r->user(),'fca_reviewer_user_ids'),403);
  $ref=FcaPresidentClaim::findOrFail($claim);
  return response()->json(\App\Services\Mao\PreparedWorkflowTransaction::run(function()use($r,$s,$ref){
   \App\Services\Mao\MaoLockPlan::acquire(['actor'=>$r->user()->getKey(),'users'=>[$ref->applicant_user_id],'fca'=>$ref->fca_id]);
   abort_unless(app(MaoIdentityPolicy::class)->administrator($r->user()->fresh(),'fca_reviewer_user_ids'),403);
   $c=FcaPresidentClaim::whereKey($ref->id)->lockForUpdate()->firstOrFail();$fca=\App\Models\FcaRegistry::findOrFail($c->fca_id);$u=\App\Models\User::findOrFail($c->applicant_user_id);
   $summary=$s->summary($c);$summary['manual_verification_current']=$s->currentProof($c);$summary['phone_display']='***********';
   $history=\Illuminate\Support\Facades\Schema::hasTable('fca_manual_decisions')
    ? \App\Models\FcaManualDecision::where('claim_id',$c->id)->orderByDesc('id')->limit(20)->get()->map(fn($d)=>['result'=>$d->result,'outcome'=>$d->outcome,'reviewer_id'=>$d->reviewer_id,'reviewed_at'=>$d->created_at->toISOString(),'contacted_at'=>$d->contacted_at?->toISOString()])->all():[];
   // Encrypted free-text evidence and confidential contacts never enter this projection.
   $corrections=\Illuminate\Support\Facades\Schema::hasTable('fca_claim_revisions')?\App\Models\FcaClaimRevision::where('claim_id',$c->id)->orWhere('target_claim_id',$c->id)->orderByDesc('id')->limit(20)->get()->map(fn($d)=>['from_claim_id'=>$d->claim_id,'target_claim_id'=>$d->target_claim_id,'previous_version'=>$d->previous_version,'resubmitted_at'=>$d->created_at->toISOString()])->all():[];
   return $summary+['claimed_name'=>$c->claimed_name,'directory_president'=>$fca->president_name,'applicant_identity'=>['first_name'=>$u->first_name,'last_name'=>$u->last_name],'manual_review_history'=>$history,'correction_history'=>$corrections];
  }))->header('Cache-Control','no-store, private')->header('Pragma','no-cache');
 }
 public function decide(Request $r,int $claim,PresidentVerification $s){$v=$r->validate(['decision'=>'required|in:reject,dispute,outdated,revoke','expected_version'=>'required|integer|min:1']);return response()->json($s->reviewClaim($r->user(),$claim,$v['decision'],$v['expected_version']));}
 public function contact(Request $r,int $claim,PresidentVerification $s){
  abort_if(array_diff(array_keys($r->all()),['expected_version','purpose']),422);
  $v=$r->validate(['expected_version'=>'required|integer|min:1','purpose'=>'required|in:manual_identity_verification']);
  return response()->json($s->trustedContact($r->user(),$claim,(int)$v['expected_version']))->header('Cache-Control','no-store, private')->header('Pragma','no-cache');
 }
 public function verifyManually(Request $r,int $claim,PresidentVerification $s){
  abort_if(array_diff(array_keys($r->all()),['result','outcome','decision_key','expected_version','contact_version','contacted_at','evidence']),422);
  $v=$r->validate(['result'=>'required|in:verified,rejected,requires_further_review','outcome'=>'required|in:identity_confirmed,identity_mismatch,unreachable,outdated_record,ambiguous_record','decision_key'=>'required|uuid','expected_version'=>'required|integer|min:1','contact_version'=>'nullable|integer|min:1','contacted_at'=>'nullable|date','evidence'=>'required|string|min:10|max:2000']);
  $v['expected_version']=(int)$v['expected_version'];if(isset($v['contact_version']))$v['contact_version']=(int)$v['contact_version'];
  return response()->json($s->manualReview($r->user(),$claim,$v))->header('Cache-Control','no-store, private');
 }
}
