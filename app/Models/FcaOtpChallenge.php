<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FcaOtpChallenge extends Model {
 public $incrementing=false;protected $keyType='string';protected $guarded=['*'];
 protected $hidden=['verifier','claim_id','provider_reference'];
 protected $casts=['expires_at'=>'datetime','consumed_at'=>'datetime','attempts'=>'integer'];
}
