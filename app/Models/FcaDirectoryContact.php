<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class FcaDirectoryContact extends Model {
 protected $guarded=['*'];protected $hidden=['phone','phone_lookup','source_reference'];
 protected $casts=['phone'=>'encrypted','source_reference'=>'encrypted','fca_id'=>'integer','version'=>'integer','valid_until'=>'datetime'];
}
