<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only at the service boundary; database-level immutability remains a deployment concern. */
class FcaReviewEvent extends Model
{
    protected $guarded = ['*'];
    protected $hidden = ['review_reason', 'decision_key', 'decision_hash', 'evidence_id', 'correlation_id'];
    protected $casts = ['review_reason' => 'encrypted'];
}
