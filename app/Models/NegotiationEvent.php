<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NegotiationEvent extends Model
{
    protected $fillable = ['negotiation_id','version_id','actor_user_id','event','status','remarks','terms'];
    protected $casts = ['terms' => 'array'];
}
