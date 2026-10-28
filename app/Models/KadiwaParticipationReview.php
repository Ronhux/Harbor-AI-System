<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only participation decision record. One row per reviewer decision, with the
 * documented reason supplied at decision time. Never updated or deleted.
 */
final class KadiwaParticipationReview extends Model
{
    protected $table = 'kadiwa_participation_reviews';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'reviewer_id' => 'integer',
            'version' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KadiwaApplication::class, 'application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id', 'user_id');
    }
}