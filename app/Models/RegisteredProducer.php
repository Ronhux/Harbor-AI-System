<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegisteredProducer extends Model
{
    use HasFactory;

    protected $table = 'registered_producers';
    protected $primaryKey = 'registry_id';
    public $timestamps = false;

    protected $fillable = [
        'rsbsa_number',
        'full_name',
        'municipality',
        'barangay',
        'primary_livelihood',
        'producer_type',
        'status',
    ];
}