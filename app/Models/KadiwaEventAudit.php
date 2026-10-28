<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Management audit trail for significant event actions: draft created, draft updated,
 * published, registration closed, marked ongoing/completed, rescheduled (republication
 * required) and cancelled. Append-only; the operation_key makes each action idempotent.
 */
final class KadiwaEventAudit extends Model
{
    protected $table = 'kadiwa_event_audits';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'actor_user_id' => 'integer',
            'version' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(KadiwaEvent::class, 'event_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'user_id');
    }
}