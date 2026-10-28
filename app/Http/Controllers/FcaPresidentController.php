<?php
namespace App\Http\Controllers;
use App\Models\{User,Producer};
use App\Services\Mao\President\PresidentVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
final class FcaPresidentController extends Controller {
 public function onboarding(Request $r){
  abort_unless(config('fca_president.workflow_enabled')===true,404);
  $v=$r->validate(['email'=>'required|email|max:255','password'=>'required|string|max:255']);
  $user=User::where('email',$v['email'])->first();
  abort_unless($user&&Hash::check($v['password'],$user->password_hash)&&$user->is_active===true&&in_array($user->user_type,['Farmer','Fisherfolk','Producer'],true)&&Producer::where('user_id',$user->getKey())->exists(),403,'Verification access unavailable.');
  abort_unless(app(\App\Services\Policies\PolicyAcceptanceService::class)->pendingFor($user)===[],403);
  // Restricted token, not ordinary login. Global guard is source-wired but hard-inactive.
  return response()->json(['token'=>$user->createToken('fca-onboarding',['fca:onboarding'],now()->addMinutes(15))->plainTextToken,'scope'=>'fca:onboarding','expires_in'=>900])->header('Cache-Control','no-store, private');
 }
 private function actor(Request $r):User {$u=$r->user();abort_unless($u&&(!$u->currentAccessToken()||$u->currentAccessToken()->can('fca:onboarding')||$u->currentAccessToken()->can('*')),403);return $u;}
 public function match(Request $r,int $fca,PresidentVerification $s){$v=$r->validate(['association'=>'required|string|max:255','name'=>'required|string|max:255','phone'=>'required|string|max:40','consent'=>'required|accepted']);abort_if(array_diff(array_keys($r->all()),['association','name','phone','consent']),422);return response()->json($s->match($this->actor($r),$fca,$v['association'],$v['name'],$v['phone'],true));}
 public function show(Request $r,int $claim,PresidentVerification $s){return response()->json($s->show($this->actor($r),$claim));}
 public function directory(Request $r,PresidentVerification $s){return response()->json($s->directory($this->actor($r)));}
 public function mine(Request $r,PresidentVerification $s){return response()->json($s->mine($this->actor($r)))->header('Cache-Control','no-store, private');}
 public function resubmit(Request $r,int $claim,PresidentVerification $s){return response()->json($s->resubmit($this->actor($r),$claim,$r->all()))->header('Cache-Control','no-store, private');}
 public function send(Request $r,int $claim,PresidentVerification $s){abort_if($r->all(),422);return response()->json($s->send($this->actor($r),$claim,$r->ip()));}
 public function verify(Request $r,string $challenge,PresidentVerification $s){$v=$r->validate(['code'=>['required','string','regex:/\A\d{6}\z/']]);abort_if(array_diff(array_keys($r->all()),['code']),422);return response()->json($s->verify($this->actor($r),$challenge,$v['code']));}
 public function submit(Request $r,int $claim,PresidentVerification $s){abort_if($r->user()?->currentAccessToken()?->can('fca:onboarding')&&!$r->user()?->currentAccessToken()?->can('*'),403);$v=$r->validate(['representative_position'=>'required|string|max:120','authorization_statement'=>'required|string|max:2000','evidence_receipt'=>'required|uuid','submission_key'=>'required|uuid']);return response()->json($s->submit($this->actor($r),$claim,$v));}
}
