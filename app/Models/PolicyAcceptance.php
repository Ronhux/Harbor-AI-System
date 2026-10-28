<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versioned record of a user's affirmative acceptance of a HarborAI policy.
 *
 * Rows are immutable acceptance evidence: repeated submissions for the same
 * account, policy type, and version are idempotent and keep the original
 * server-generated timestamp.
 */
class PolicyAcceptance extends Model
{
    use HasFactory;

    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY = 'privacy_policy';

    /**
     * Acceptance metadata is intentionally narrow: acceptance ownership,
     * policy type, and server-validated version only. Timestamps are always
     * generated on the server; client-supplied values are never accepted.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'policy_type',
        'policy_version',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            // Server-generated acceptance timestamp in UTC (app timezone).
            $model->accepted_at = $model->accepted_at ?? now('UTC');
        });

        // Acceptance rows are append-only evidence. An existing row's identity
        // and timestamp are never rewritten, so a later code path (or a mass
        // assignment that reaches a fillable attribute) cannot backdate or
        // re-attribute a recorded acceptance. Corrections are made by recording
        // acceptance of the current policy version, never by editing history.
        static::updating(function (self $model): bool {
            foreach (['user_id', 'policy_type', 'policy_version', 'accepted_at'] as $immutable) {
                if ($model->isDirty($immutable)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
