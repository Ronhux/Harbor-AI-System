<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FcaManualDecision extends Model {
 protected $guarded=['*'];
 protected $hidden=['evidence','decision_hash','contact_id','contact_version','reviewer_id'];
 protected $casts=['evidence'=>'encrypted','contact_id'=>'integer','contact_version'=>'integer','reviewer_id'=>'integer','claim_id'=>'integer','contacted_at'=>'datetime'];
}
