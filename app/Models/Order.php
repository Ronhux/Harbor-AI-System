<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'product_listing_id',
        'quantity',
        'total_price',
        'status',
        'order_date',
        'delivery_date',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
    ];

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class);
    }
}