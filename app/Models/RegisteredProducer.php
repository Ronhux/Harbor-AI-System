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
        'first_name',
        'middle_name',
        'last_name',
        'extension_name',
        'sex',
        'house_number',
        'zone',
        'street',
        'barangay',
        'municipality',
        'province',
        'region',
        'mobile_number',
        'farm_location',
        'area_ha',
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