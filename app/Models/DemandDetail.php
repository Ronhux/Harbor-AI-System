<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandDetail extends Model
{
    use HasFactory;

    protected $table = 'demand_details';

    protected $casts = ['revisions' => 'array', 'availability_date' => 'date'];

    protected $fillable = [
        'demand_request_id',
        'producer_id',
        'proposed_quantity',
        'proposed_price',
        'notes',
        'status',
        'listing_id',
        'quantity_offered',
        'availability_date',
        'delivery_terms',
        'quality_grade',
    ];

    public function demandRequest()
    {
        return $this->belongsTo(DemandRequest::class, 'demand_request_id', 'request_id');
    }

    public function producer()
    {
        return $this->belongsTo(Producer::class);
    }

    public function listing()
    {
        return $this->belongsTo(ProductListing::class, 'listing_id', 'listing_id');
    }
}
