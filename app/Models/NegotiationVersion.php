<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NegotiationVersion extends Model
{
    protected $fillable = ['negotiation_id','version','proposed_by_user_id','listed_price','unit_price','quantity','unit','delivery_date','delivery_arrangement','additional_charges','notes','remarks','proposed_by_role','buyer_confirmed_at','producer_confirmed_at'];
    protected $casts = ['delivery_date' => 'date','buyer_confirmed_at' => 'datetime','producer_confirmed_at' => 'datetime'];
}
