<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit event-to-coordinator assignment. Authority is never inferred from an
 * administrator role; a coordinator only reaches an event that names them here.
 */
final class KadiwaEventCoordinator extends Model
{
    protected $table = 'kadiwa_event_coordinators';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'user_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(KadiwaEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}