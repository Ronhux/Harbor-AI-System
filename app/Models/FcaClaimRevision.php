<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FcaClaimRevision extends Model {
 protected $guarded=['*'];protected $hidden=['previous_details','submitted_details','payload_hash'];
 protected $casts=['previous_details'=>'encrypted:array','submitted_details'=>'encrypted:array','actor_id'=>'integer','claim_id'=>'integer','target_claim_id'=>'integer','previous_version'=>'integer','review_decision_id'=>'integer'];
}
