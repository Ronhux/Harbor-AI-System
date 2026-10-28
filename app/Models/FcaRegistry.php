<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcaRegistry extends Model
{
    protected $table = 'fca_registry';
    // Status and evidence fields cannot be mass-assigned by account applicants.
    protected $guarded = ['*'];
    protected $hidden = ['contact_number', 'procurement_evidence_reference', 'remarks'];
    protected $casts = ['last_verified_at' => 'datetime'];

    public function representatives()
    {
        return $this->hasMany(FcaRepresentative::class, 'fca_id');
    }
}
