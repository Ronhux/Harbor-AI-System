<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandRequest extends Model
{
    use HasFactory;

    protected $table = 'demand_requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'buyer_id',
        'product_name',
        'category',
        'quantity_needed',
        'unit',
        'max_price_per_unit',
        'description',
        'location',
        'deadline',
        'status',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function demandDetails()
    {
        return $this->hasMany(DemandDetail::class);
    }
}