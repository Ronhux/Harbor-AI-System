<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
final class KadiwaApplication extends Model
{
    protected $guarded = ['*'];
    protected $hidden = ['details','submission_key','submission_hash','producer_id'];
    public function event(): BelongsTo { return $this->belongsTo(KadiwaEvent::class,'event_id'); }
    public function producer(): BelongsTo { return $this->belongsTo(Producer::class,'producer_id','producer_id'); }
    public function reviews(): HasMany { return $this->hasMany(KadiwaParticipationReview::class,'application_id'); }
    public function history(): HasMany { return $this->hasMany(KadiwaApplicationStatusEvent::class,'application_id'); }
    protected function casts(): array { return ['details'=>'encrypted:array','row_version'=>'integer','event_id'=>'integer','producer_id'=>'integer']; }
}
