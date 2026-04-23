<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandDetail extends Model
{
    use HasFactory;

    protected $table = 'demand_details';

    protected $fillable = [
        'demand_request_id',
        'producer_id',
        'proposed_quantity',
        'proposed_price',
        'notes',
        'status',
    ];

    public function demandRequest()
    {
        return $this->belongsTo(DemandRequest::class);
    }

    public function producer()
    {
        return $this->belongsTo(Producer::class);
    }
}