<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $primaryKey = 'order_id';

    public $timestamps = false;

    protected $fillable = [
        'buyer_id',
        'order_date',
        'total_amount',
        'shipping_address',
        'fulfillment_status',
        'payment_status',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'total_price' => 'decimal:2',
        'order_date' => 'date',
        'delivery_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Buyer
    |--------------------------------------------------------------------------
    */

    public function buyer()
    {
        return $this->belongsTo(
            Buyer::class,
            'buyer_id',
            'buyer_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product Listing
    |--------------------------------------------------------------------------
    */

    public function productListing()
    {
        return $this->belongsTo(
            ProductListing::class,
            'product_listing_id',
            'listing_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Order Details
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(
            OrderDetail::class,
            'order_id',
            'order_id'
        );
    }
}