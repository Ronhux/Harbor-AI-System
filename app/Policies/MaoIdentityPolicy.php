<?php
namespace App\Policies;
use App\Models\{Buyer,Producer,User};
/** Shared dormant workflow identity checks; never grants or writes privileges. */
final class MaoIdentityPolicy {
    public function producer(User $actor,Producer $producer): bool {
        $review=$producer->account_review_status;
        $approved=$review!==null?$review==='active':$producer->verification_status==='Verified';
        return $actor->is_active===true && (int)$actor->getKey()>0 && (int)$producer->getKey()>0
            && in_array($actor->user_type,['Farmer','Fisherfolk','Producer'],true)
            && (int)$producer->user_id===(int)$actor->getKey() && $approved
            && strtolower((string)$producer->verification_status)!=='rejected';
    }
    public function administrator(User $actor,string $assignment): bool {
        if(!in_array($assignment,['fca_reviewer_user_ids','kadiwa_manager_user_ids','kadiwa_inquiry_reviewer_user_ids','demand_reviewer_user_ids'],true))return false;
        return $actor->is_active===true && (int)$actor->getKey()>0 && in_array($actor->user_type,['Admin','Super Admin'],true)
            && in_array((int)$actor->getKey(),config('mao_workflows.'.$assignment,[]),true);
    }
    public function institutionalBuyer(User $actor,Buyer $buyer): bool {
        return $actor->is_active===true && $actor->user_type==='Buyer' && (int)$actor->getKey()>0 && (int)$buyer->getKey()>0
            && (int)$buyer->user_id===(int)$actor->getKey() && $buyer->verification_status==='Verified' && trim((string)$buyer->organization_name)!==''
            && in_array((int)$actor->getKey(),config('mao_workflows.institutional_buyer_user_ids',[]),true);
    }
    public function rsbsaVerified(Producer $producer): bool { return $producer->rsbsa_verification_status==='verified'; }
}
