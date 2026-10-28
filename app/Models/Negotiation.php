<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Negotiation extends Model
{
    protected $fillable = ['reference','buyer_id','producer_id','listing_id','demand_request_id','demand_detail_id','status','current_version','agreement_snapshot'];
    protected $casts = ['agreement_snapshot' => 'array'];
    public function versions() { return $this->hasMany(NegotiationVersion::class)->orderBy('version'); }
    public function events() { return $this->hasMany(NegotiationEvent::class)->orderBy('created_at'); }
    public function listing() { return $this->belongsTo(ProductListing::class, 'listing_id', 'listing_id'); }
    public function demandRequest() { return $this->belongsTo(DemandRequest::class, 'demand_request_id', 'request_id'); }
    public function supplyOffer() { return $this->belongsTo(DemandDetail::class, 'demand_detail_id'); }
    public function order() { return $this->hasOne(Order::class, 'negotiation_id', 'id'); }
}
