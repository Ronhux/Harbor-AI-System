<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class PreparedDemand extends Model {
    protected $table='demand_requests'; protected $primaryKey='request_id'; protected $guarded=['*'];
    protected $hidden=['mao_payload','mao_submission_key','mao_submission_hash','buyer_id'];
    protected function casts(): array { return ['mao_payload'=>'encrypted:array','mao_version'=>'integer','buyer_id'=>'integer']; }
    protected static function booted(): void { static::addGlobalScope('prepared',fn($q)=>$q->where('demand_requests.mao_kind','institutional')); }
}
