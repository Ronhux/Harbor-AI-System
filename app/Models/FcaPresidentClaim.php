<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FcaPresidentClaim extends Model {
 protected $guarded=['*'];protected $hidden=['claimed_name','claimed_phone','directory_contact_id','directory_version','applicant_user_id'];
 protected $casts=['fca_id'=>'integer','applicant_user_id'=>'integer','directory_contact_id'=>'integer','row_version'=>'integer','claimed_name'=>'encrypted','claimed_phone'=>'encrypted','directory_version'=>'integer','phone_verified_at'=>'datetime','proof_expires_at'=>'datetime','consented_at'=>'datetime'];
 /** Preserve the original explicit event even on legacy MariaDB ON UPDATE schemas.
  * Include the unchanged timestamp in UPDATE SQL so MariaDB cannot advance it.
  * The separate deadline corrective migration remains pending; this grants no authority.
  */
 public function getDirty(): array {
  if ($this->exists) $this->attributes['consented_at'] = $this->getRawOriginal('consented_at');
  $dirty = parent::getDirty();
  if ($this->exists && $dirty !== []) $dirty['consented_at'] = $this->getRawOriginal('consented_at');
  return $dirty;
 }
}
