<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FcaPhoneEvent extends Model
{
    protected $table = 'fca_phone_events';

    protected $fillable = [
        'actor_user_id',
        'claim_id',
        'event',
    ];

    public $timestamps = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}