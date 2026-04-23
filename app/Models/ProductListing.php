<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductListing extends Model
{
    use HasFactory;

    protected $table = 'product_listings';

    protected $fillable = [
        'producer_id',
        'product_name',
        'category',
        'quantity',
        'unit',
        'price_per_unit',
        'description',
        'location',
        'harvest_date',
        'expiry_date',
        'status',
    ];

    protected $casts = [
        'harvest_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function producer()
    {
        return $this->belongsTo(Producer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}