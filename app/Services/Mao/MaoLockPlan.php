<?php
namespace App\Services\Mao;
use App\Models\PreparedSupplyOffer;
use Illuminate\Support\Facades\{DB,Schema};
/** Existing-record locks only; snapshots are private and never returned/logged. */
final class MaoLockPlan {
    public const KEYS=['users'=>'user_id','producers'=>'producer_id','buyers'=>'buyer_id','fca_registry'=>'id','fca_representatives'=>'id','fca_evidence'=>'id','fca_inspection_attempts'=>'id','kadiwa_events'=>'id','kadiwa_event_coordinators'=>'id','kadiwa_applications'=>'id','kadiwa_participation_reviews'=>'id','demand_requests'=>'request_id','demand_details'=>'id','mao_demand_items'=>'id','mao_offer_items'=>'id','mao_allocations'=>'id'];
    private const FIELDS=[
        'users'=>[], 'producers'=>['user_id'], 'buyers'=>['user_id'], 'fca_registry'=>[],
        'fca_representatives'=>['producer_id','fca_id','authorization_evidence_reference'],
        'fca_evidence'=>['producer_id','uploaded_by','application_id','latest_attempt_id','evidence_version','sha256','storage_key'],
        'fca_inspection_attempts'=>['evidence_id','retry_of_id'],
        'kadiwa_events'=>[], 'kadiwa_event_coordinators'=>['event_id','user_id'], 'kadiwa_applications'=>['event_id','producer_id'], 'kadiwa_participation_reviews'=>['application_id','reviewer_id'],
        'demand_requests'=>['buyer_id','mao_payload'], 'demand_details'=>['demand_request_id','producer_id','mao_payload'],
        'mao_demand_items'=>['demand_id'], 'mao_offer_items'=>['offer_id','demand_id','demand_item_id'], 'mao_allocations'=>['offer_id','offer_item_id','demand_item_id'],
    ];
    private array $rows=[];
    private array $checked=[];
    private function row(string $table,int|string|null $id,bool $required=true): ?object {
        if($id===null)return null;
        if(in_array($table,['fca_evidence','fca_inspection_attempts'],true)&&is_string($id))$id=strtolower($id);
        abort_unless(isset(self::KEYS[$table]) && ((is_int($id)&&$id>0)||(is_string($id)&&strlen($id)<=100&&$id!=='')),422);
        if(isset($this->rows[$table][(string)$id]))return $this->rows[$table][(string)$id];
        $columns=array_merge([self::KEYS[$table]],self::FIELDS[$table]);
        if(!isset($this->checked[$table])){abort_unless(Schema::hasTable($table)&&Schema::hasColumns($table,$columns),503,'Workflow lock dependencies unavailable');$this->checked[$table]=true;}
        $r=DB::table($table)->where(self::KEYS[$table],$id)->first($columns);
        if(!$r){abort_if($required,404,'Workflow context not found');return null;}
        $this->rows[$table][(string)$id]=$r;return $r;
    }
    private function producer(?int $id): void { if($id===null)return;$r=$this->row('producers',$id);$this->row('users',(int)$r->user_id); }
    private function buyer(?int $id): void { if($id===null)return;$r=$this->row('buyers',$id);$this->row('users',(int)$r->user_id); }
    private function representative(?int $id): void {
        if($id===null)return;$already=isset($this->rows['fca_representatives'][(string)$id]);$r=$this->row('fca_representatives',$id);if($already)return;
        $this->producer($r->producer_id === null ? null : (int)$r->producer_id);$this->row('fca_registry',(int)$r->fca_id);
        if($r->authorization_evidence_reference)$this->evidence($r->authorization_evidence_reference);
    }
    private function evidence(?string $id,bool $required=true): void {
        if($id===null)return;$already=isset($this->rows['fca_evidence'][$id]);$r=$this->row('fca_evidence',$id,$required);if(!$r||$already)return;
        $this->producer((int)$r->producer_id);$this->row('users',(int)$r->uploaded_by);
        if($r->application_id)$this->representative((int)$r->application_id);
        if($r->latest_attempt_id)$this->attempt($r->latest_attempt_id);
    }
    private function attempt(?string $id): void {
        if($id===null)return;$already=isset($this->rows['fca_inspection_attempts'][$id]);$r=$this->row('fca_inspection_attempts',$id);if($already)return;
        abort_if(count($this->rows['fca_inspection_attempts']??[])>100,409,'Inspection context exceeds bounded history');
        $this->evidence($r->evidence_id);if($r->retry_of_id)$this->attempt($r->retry_of_id);
    }
    private function participant(?int $id): void {
        if($id===null)return;$r=$this->row('kadiwa_applications',$id);$this->producer((int)$r->producer_id);$this->row('kadiwa_events',(int)$r->event_id);
    }
    private function demand(?int $id): void { if($id===null)return;$r=$this->row('demand_requests',$id);$this->buyer((int)$r->buyer_id); }
    private function offer(?int $id): void {
        if($id===null)return;$r=$this->row('demand_details',$id);$this->producer((int)$r->producer_id);$this->demand((int)$r->demand_request_id);
        $model=PreparedSupplyOffer::findOrFail($id);$payload=$model->mao_payload;
        abort_unless($model->getRawOriginal('mao_payload')===$r->mao_payload,409,'Offer context changed');
        if(($payload['supplier_type']??null)==='fca' && !empty($payload['representation_id']))$this->representative((int)$payload['representation_id']);
    }
    private function children(string $table,string $foreign,array $parents): void {
        if(!$parents)return;
        foreach(DB::table($table)->whereIn($foreign,$parents)->orderBy(self::KEYS[$table])->pluck(self::KEYS[$table]) as $id)$this->row($table,$id);
    }
    public static function acquire(array $context): void {
        abort_unless(DB::connection()->transactionLevel()>0,503,'Lock plan requires an active transaction');
        $allowed=['actor','users','producer','buyer','fca','fcas','representative','evidence','evidence_optional','attempt','event','participant','demand','offer','kadiwa','event_children','demand_children','fca_pair'];
        abort_if(array_diff(array_keys($context),$allowed),422);
        $p=new self;
        // Anonymous preliminary intake has no authenticated actor; skip the user lock.
        if (isset($context['actor']) && is_int($context['actor']) && $context['actor'] > 0) {
            $p->row('users',$context['actor']);
            abort_unless(isset($context['actor']),422);
        }
        foreach($context['users']??[] as $id)$p->row('users',$id);
        if($context['kadiwa']??false){$ids=config('mao_workflows.kadiwa_manager_user_ids',[]);sort($ids,SORT_NUMERIC);abort_unless($ids&&is_int($ids[0])&&$ids[0]>0,503);$p->row('users',$ids[0]);}
        $p->producer($context['producer']??null);$p->buyer($context['buyer']??null);
        if(isset($context['fca']))$p->row('fca_registry',$context['fca'],false);
        foreach($context['fcas']??[] as $id)$p->row('fca_registry',$id,false); // Collected before the existing globally sorted acquisition.
        $p->representative($context['representative']??null);$p->evidence($context['evidence']??null,!($context['evidence_optional']??false));$p->attempt($context['attempt']??null);
        if($context['fca_pair']??false)foreach(DB::table('fca_representatives')->where(['producer_id'=>$context['producer'],'fca_id'=>$context['fca']])->orderBy('id')->pluck('id') as $id)$p->representative((int)$id);
        $p->participant($context['participant']??null);
        if(isset($context['event']))$p->row('kadiwa_events',$context['event']);
        $p->demand($context['demand']??null);$p->offer($context['offer']??null);
        // These actor profile reads are used by scoped listings and parent creation.
        if(!isset($context['producer'])&&!isset($context['participant'])&&!isset($context['offer'])){
            $id=DB::table('producers')->where('user_id',$context['actor'])->value('producer_id');if($id)$p->producer((int)$id);
        }
        if(!isset($context['buyer'])&&!isset($context['demand'])&&!isset($context['offer'])){
            $id=DB::table('buyers')->where('user_id',$context['actor'])->value('buyer_id');if($id)$p->buyer((int)$id);
        }
        $events=array_keys($p->rows['kadiwa_events']??[]);
        $p->children('kadiwa_event_coordinators','event_id',$events);
        if($context['event_children']??false)$p->children('kadiwa_applications','event_id',$events);
        $demands=array_keys($p->rows['demand_requests']??[]);
        if($context['demand_children']??false)$p->children('demand_details','demand_request_id',$demands);
        if(isset($context['offer'])||($context['demand_children']??false)) {
            foreach($demands as $id){$revision=DB::table('mao_demand_items')->where('demand_id',$id)->max('revision');foreach(DB::table('mao_demand_items')->where(['demand_id'=>$id,'revision'=>$revision])->orderBy('id')->pluck('id') as $item)$p->row('mao_demand_items',$item);}
            $p->children('mao_offer_items','demand_id',$demands);
            $p->children('mao_allocations','offer_id',array_keys($p->rows['demand_details']??[]));
            // Allocation rows of other offers affect aggregate budgets and quantities.
            $p->children('mao_allocations','demand_item_id',array_keys($p->rows['mao_demand_items']??[]));
        }
        $plan=[];
        foreach(self::KEYS as $table=>$key){$ids=array_keys($p->rows[$table]??[]);if(!$ids)continue;sort($ids,$table==='fca_evidence'||$table==='fca_inspection_attempts'?SORT_STRING:SORT_NUMERIC);$plan[$table]=$ids;}
        $observer=app(WorkflowLockObserver::class);$observer->planned($plan);
        foreach($plan as $table=>$ids){
            $key=self::KEYS[$table];$columns=array_merge([$key],self::FIELDS[$table]);
            // One primary-key point lock at a time makes physical acquisition ordering explicit.
            foreach($ids as $id){$current=DB::table($table)->where($key,$id)->lockForUpdate()->first($columns);abort_unless($current && (array)$current===(array)$p->rows[$table][(string)$id],409,'Workflow relationship changed; reload context');}
            $observer->acquired($table,$ids);
        }
    }
}
