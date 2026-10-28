<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class KadiwaInquiry extends Model {
 protected $guarded=['*'];protected $hidden=['message','response','payload_hash','owner_id','reviewer_id'];
 protected $casts=['message'=>'encrypted','response'=>'encrypted','owner_id'=>'integer','reviewer_id'=>'integer','row_version'=>'integer'];
}
