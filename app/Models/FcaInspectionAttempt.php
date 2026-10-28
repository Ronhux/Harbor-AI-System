<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcaInspectionAttempt extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['*'];
    protected $hidden = ['input_sha256', 'operation_key', 'adapter_id', 'initiated_by'];
    protected $casts = ['evidence_version' => 'integer', 'check_results' => 'array', 'completed_at' => 'datetime'];
}
