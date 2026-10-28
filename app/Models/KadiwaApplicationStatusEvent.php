<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participation status timeline entry. Records how a record reached its current status
 * (submitted, approve, reject, request_information, withdraw, event_cancelled) without
 * exposing the reviewer identity or any internal note to the producer.
 */
final class KadiwaApplicationStatusEvent extends Model
{
    protected $table = 'kadiwa_application_status_events';

    protected $guarded = ['*'];

    protected $hidden = ['actor_user_id'];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'actor_user_id' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KadiwaApplication::class, 'application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'user_id');
    }
}