<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcaRepresentative extends Model
{
    protected $guarded = ['*'];
    protected $hidden = ['authorization_evidence_reference', 'submitted_details', 'submission_key', 'president_claim_id'];
    protected $casts = ['reviewed_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime',
        'submitted_details' => 'encrypted:array', 'row_version' => 'integer', 'president_claim_id' => 'integer'];

    public function fca() { return $this->belongsTo(FcaRegistry::class, 'fca_id'); }
    public function producer() { return $this->belongsTo(Producer::class, 'producer_id', 'producer_id'); }
    public function evidence() { return $this->hasMany(FcaEvidence::class, 'application_id'); }
    public function reviewEvents() { return $this->hasMany(FcaReviewEvent::class, 'application_id'); }
}
