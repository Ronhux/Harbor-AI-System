<?php
namespace App\Services\Mao\President;
use App\Models\{User,FcaRegistry,FcaDirectoryContact};
use App\Policies\MaoIdentityPolicy;
use App\Services\Mao\{MaoLockPlan,PreparedWorkflowTransaction};
/** Used only by a separately approved protected import/reconciliation. No route or command registered. */
final class DirectoryContactProvisioning {
 public function create(User $actor,int $fcaId,string $phone,string $source,\DateTimeInterface $validUntil):void {
  abort_unless(config('fca_president.workflow_enabled')===true&&app(MaoIdentityPolicy::class)->administrator($actor,'fca_reviewer_user_ids'),403);
  $canonical=PresidentIdentity::phone($phone);abort_unless($canonical&&trim($source)!==''&&$validUntil>now(),422);
  PreparedWorkflowTransaction::run(function()use($actor,$fcaId,$canonical,$source,$validUntil){
   MaoLockPlan::acquire(['actor'=>$actor->getKey(),'fca'=>$fcaId]);abort_unless(app(MaoIdentityPolicy::class)->administrator($actor->fresh(),'fca_reviewer_user_ids'),403);
   $fca=FcaRegistry::findOrFail($fcaId);abort_unless($fca->directory_verification_status==='verified',422);
   PhoneMutex::lock(PresidentIdentity::digest($canonical));
   abort_if(FcaDirectoryContact::where('fca_id',$fcaId)->exists(),409,'Existing confidential record requires reconciliation.');
   (new FcaDirectoryContact())->forceFill(['fca_id'=>$fcaId,'phone'=>$canonical,'phone_lookup'=>PresidentIdentity::digest($canonical),'source_reference'=>$source,'version'=>1,'status'=>'active','valid_until'=>$validUntil])->saveOrFail();
  });
 }
 public function masked(FcaDirectoryContact $contact):array {return ['fca_id'=>$contact->fca_id,'phone_display'=>'***********','status'=>$contact->status,'version'=>$contact->version];}
}
