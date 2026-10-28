<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductListing extends Model
{
    use HasFactory;

    protected $table = 'product_listings';

    protected $primaryKey = 'listing_id';

    public $timestamps = true;

    protected $fillable = [
        'producer_id',
        'product_name',
        'product_category',
        'category',
        'current_price_per_unit',
        'price_per_unit',
        'unit_of_measure',
        'unit',
        'quantity_available',
        'quantity',
        'description',
        'location',
        'harvest_date',
        'expiry_date',
        'status',
        'image_url',
        'image_path',
    ];

    /*
    | `views` is deliberately absent from $fillable. It is a server owned
    | metric that must never be accepted from a client payload, so the only
    | way it changes is the atomic increment in ProducerController.
    */

    protected $casts = [
        'harvest_date' => 'date',
        'expiry_date' => 'date',
        'current_price_per_unit' => 'float',
        'price_per_unit' => 'float',
        'quantity_available' => 'float',
        'quantity' => 'float',
        'views' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Producer
    |--------------------------------------------------------------------------
    */

    public function producer()
    {
        return $this->belongsTo(
            Producer::class,
            'producer_id',
            'producer_id'
        );
    }

    public function images()
    {
        return $this->hasMany(ProductListingImage::class, 'listing_id', 'listing_id')->orderByDesc('is_primary')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Order Details
    |--------------------------------------------------------------------------
    */

    public function orderDetails()
    {
        return $this->hasMany(
            OrderDetail::class,
            'listing_id',
            'listing_id'
        );
    }
}
