<?php
namespace App\Http\Responses;

/** Explicit projections for UNREGISTERED MAO controllers. No queries, policy, files or privilege changes. */
final class PreparedMaoResponse
{
    public const EVENT_FIELDS = ['id','title','description','venue','location','starts_at','ends_at','opens_at','closes_at','capacity','categories','requirements','status','row_version','evidence_required','rsbsa_required'];
    public const APPLICATION_FIELDS = ['id','event_id','status','row_version','created_at'];
    public const DEMAND_FIELDS = ['request_id','mao_state','mao_version','created_at','updated_at'];
    public const OFFER_FIELDS = ['id','demand_request_id','mao_state','mao_version','created_at','updated_at'];
    public const FCA_FIELDS = ['id','fca_id','status','row_version','created_at','reviewed_at','expires_at','revoked_at'];
    public const INQUIRY_FIELDS = ['id','locality','message','response','status','row_version','created_at'];
    private static function fields(array $row, array $fields): array
    {
        $result=[];
        foreach ($fields as $key) {
            if (!array_key_exists($key,$row)) throw new \InvalidArgumentException('Prepared response field missing: '.$key);
            $result[$key]=$row[$key];
        }
        return $result;
    }
    private static function optional(array $row,array $fields): array { return array_intersect_key($row,array_flip($fields)); }
    private static function rows(iterable $rows,callable $project): array
    {
        $result=[];foreach($rows as $row)$result[]=$project(is_array($row)?$row:get_object_vars($row));return $result;
    }
    public static function event(array $row): array { return self::fields($row,self::EVENT_FIELDS); }
    public static function events(iterable $rows): array { return self::rows($rows,self::event(...)); }
    public static function application(array $row): array { return self::fields($row,self::APPLICATION_FIELDS); }
    public static function queue(array $raw): array
    {
        return ['event'=>self::event($raw['event']),'approved_count'=>$raw['approved_count'],'available_slots'=>$raw['available_slots'],
            'applications'=>self::rows($raw['applications'],self::application(...))];
    }
    public static function participation(array $raw): array
    {
        return ['application'=>self::application($raw['application']),
            'details'=>['commodities'=>self::rows($raw['details']['commodities'],fn($r)=>self::fields($r,['commodity','category','quantity','unit'])),
                'participation_notes'=>$raw['details']['participation_notes']??null],
            // Producer identity, submission key, payload hash and reviewer identity are never returned.
            'history'=>self::rows($raw['history'],fn($r)=>self::fields($r,['action','previous_status','new_status','created_at'])),
            'review_notes'=>self::rows($raw['review_notes'],fn($r)=>self::fields($r,['action','reason','created_at']))];
    }
    public static function inquiry(array $row): array
    {
        // participation_approved is always false: an inquiry response never approves participation.
        return self::fields($row,self::INQUIRY_FIELDS)+['participation_approved'=>false];
    }
    public static function inquiries(iterable $rows): array
    {
        return ['data'=>self::rows($rows,self::inquiry(...))];
    }
    public static function fca(array $row): array { return self::fields($row,self::FCA_FIELDS); }
    public static function fcaDetail(array $row,array $details,iterable $history): array
    {
        // No identity, receipt, private evidence summary or review reason inferred.
        return ['data'=>self::fca($row)+['submitted_details'=>self::fields($details,['representative_position','authorization_statement']),
            'review_history'=>self::rows($history,fn($r)=>self::fields($r,['event_type','previous_status','new_status','created_at']))]];
    }
    public static function demand(array $row): array { return self::fields($row,self::DEMAND_FIELDS); }
    public static function offer(array $row): array { return self::fields($row,self::OFFER_FIELDS); }
    public static function demandDetail(array $raw): array
    {
        $p=$raw['posting'];$posting=self::fields($p,['requesting_institution','title','description','purpose','posting_type','items','budget_kind','currency','delivery_location','delivery_start','delivery_end','offer_deadline','partial_supply','required_documents','eligible_supplier_type','eligibility_requirements','rsbsa_required'])
            +self::optional($p,['program','procurement_reference','budget_amount']);
        $posting['items']=self::rows($p['items'],fn($r)=>self::fields($r,['commodity','category','quantity','unit','specifications'])+self::optional($r,['variety','max_unit_price']));
        // Header history excludes offer actions/states. Offer history remains on its own detail response.
        $history=[];foreach($raw['history'] as $row){$r=is_array($row)?$row:get_object_vars($row);
            if(in_array($r['action'],['created','revised','state_changed'],true))$history[]=self::fields($r,['action','previous_state','new_state','revision','created_at']);
            elseif(!in_array($r['action'],['offer_submitted','review','reject','withdraw','provisional_allocation','cancelled','expired'],true))throw new \InvalidArgumentException('Unsupported prepared demand history event.');
        }
        return ['demand'=>self::demand($raw['demand']),'posting'=>$posting,
            'items'=>self::rows($raw['items'],fn($r)=>self::fields($r,['id','commodity','category','variety','unit','specifications','requested','provisional','remaining','delivered'])), 'history'=>$history];
    }
    public static function offerDetail(array $raw): array
    {
        $submission=self::fields($raw['submission'],['demand_version','supplier_type','items','availability_date','delivery_information','eligibility_declaration'])+self::optional($raw['submission'],['representation_id']);
        $submission['items']=self::rows($submission['items'],fn($r)=>self::fields($r,['demand_item_id','quantity'])+self::optional($r,['unit_price']));
        return ['offer'=>self::offer($raw['offer']),'submission'=>$submission,
            'items'=>self::rows($raw['items'],fn($r)=>self::fields($r,['id','demand_item_id','offered_milli','unit_price_cents'])),
            'history'=>self::rows($raw['history'],fn($r)=>self::fields($r,['action','previous_state','new_state','created_at','reason']))];
    }
}
