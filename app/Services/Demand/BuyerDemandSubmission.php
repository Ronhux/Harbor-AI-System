<?php
namespace App\Services\Demand;
use App\Models\{Buyer,DemandRequest};
use App\Services\Notifications\AdminNotificationService;
use Illuminate\Support\Facades\{DB,Schema};
/** Optional persisted replay keys; no content-based deduplication of distinct requests. */
class BuyerDemandSubmission
{
    public static function available():bool {return Schema::hasTable('buyer_demand_submissions');}
    public function submit(Buyer $buyer,array $data,?string $key,int $actor):array
    {
        abort_if($key!==null&&!self::available(),503,'Safe submission retry is unavailable until the reviewed schema is installed.');
        ksort($data);
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return DB::transaction(function()use($buyer,$data,$key,$hash,$actor){
            $owner=Buyer::whereKey($buyer->buyer_id)->lockForUpdate()->firstOrFail();
            abort_unless((int)$owner->user_id===$actor&&$owner->verification_status==='Verified',403);
            if($key!==null){
                $prior=DB::table('buyer_demand_submissions')->where('buyer_id',$owner->buyer_id)->where('submission_key',$key)->lockForUpdate()->first();
                if($prior){
                    abort_unless(hash_equals($prior->payload_hash,$hash),409,'This submission key was already used with different details.');
                    return ['demand'=>$owner->demandRequests()->findOrFail($prior->demand_request_id),'replayed'=>true];
                }
            }
            $demand=$owner->demandRequests()->create($data+['status'=>'Active']);
            $demand->publicationReviews()->create(['actor_user_id'=>$actor,'decision'=>'Pending Review']);
            if($key!==null)DB::table('buyer_demand_submissions')->insert(['buyer_id'=>$owner->buyer_id,'submission_key'=>$key,'payload_hash'=>$hash,'demand_request_id'=>$demand->request_id,'created_at'=>now()]);
            // Existing delivery is a database inbox insert, in this same transaction.
            // A failed insert rolls back the demand, replay key and pending-review audit.
            app(AdminNotificationService::class)->demandRequestSubmitted($demand);
            return ['demand'=>$demand,'replayed'=>false];
        });
    }
}
