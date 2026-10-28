<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class PreparedSupplyOffer extends Model {
    protected $table='demand_details'; protected $guarded=['*'];
    protected $hidden=['mao_payload','mao_submission_key','mao_submission_hash','producer_id'];
    protected function casts(): array { return ['mao_payload'=>'encrypted:array','mao_version'=>'integer','producer_id'=>'integer','demand_request_id'=>'integer']; }
    protected static function booted(): void { static::addGlobalScope('prepared',fn($q)=>$q->where('demand_details.mao_kind','institutional')); }
}
