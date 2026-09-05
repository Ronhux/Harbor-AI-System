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
        'name',
        'full_name',
        'municipality',
        'barangay',
        'province',
        'farm_type',
        'farm_size',
        'contact_number',
        'email',
        'registration_date',
        'primary_livelihood',
        'producer_type',
        'status',
    ];
}