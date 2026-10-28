<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class KadiwaEvent extends Model
{
    protected $guarded = ['*'];
    public function coordinators(): HasMany { return $this->hasMany(KadiwaEventCoordinator::class,'event_id'); }
    public function applications(): HasMany { return $this->hasMany(KadiwaApplication::class,'event_id'); }
    public function audits(): HasMany { return $this->hasMany(KadiwaEventAudit::class,'event_id'); }
    protected function casts(): array { return ['categories'=>'array','requirements'=>'array','evidence_required'=>'boolean','rsbsa_required'=>'boolean','starts_at'=>'immutable_datetime','ends_at'=>'immutable_datetime','opens_at'=>'immutable_datetime','closes_at'=>'immutable_datetime','row_version'=>'integer','capacity'=>'integer']; }
}
