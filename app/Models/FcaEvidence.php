<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcaEvidence extends Model
{
    protected $table = 'fca_evidence';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['*'];
    protected $hidden = ['storage_key', 'sha256', 'uploaded_by', 'producer_id', 'uploader', 'producer', 'latest_attempt_id'];
    protected $casts = ['expires_at' => 'datetime', 'bytes' => 'integer', 'evidence_version' => 'integer'];

    public function application() { return $this->belongsTo(FcaRepresentative::class, 'application_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by', 'user_id'); }
    public function producer() { return $this->belongsTo(Producer::class, 'producer_id', 'producer_id'); }

    public function hasSuccessfulInspection(): bool
    {
        if (!$this->latest_attempt_id || $this->inspection_state !== 'clean') return false;
        $attempt = FcaInspectionAttempt::find($this->latest_attempt_id);
        if (!$attempt || $attempt->status !== 'successful' || !$attempt->completed_at
            || $attempt->inspection_type !== 'document' || $attempt->evidence_id !== $this->getKey()
            || $attempt->evidence_version !== $this->evidence_version
            || $attempt->adapter_id !== config('fca_evidence.inspection_adapter_id')
            || !hash_equals($this->sha256, $attempt->input_sha256)) return false;
        $checks = $attempt->check_results ?? [];
        $format = $this->mime === 'application/pdf' ? 'pdf_policy' : 'image_decode';
        return isset($checks[$format]) && (new \App\Services\Mao\Inspection\InspectionResult('clean', $checks, $this->sha256))->usable();
    }
}
