<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producer extends Model
{
    use HasFactory;

    protected $primaryKey = 'producer_id';

    protected $fillable = [
        'user_id',
        'rsbsa_number',
        'location',
        'primary_product_type',
        'verification_status',
        'producer_type',
    ];

    protected $casts = [
        'products' => 'array',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productListings()
    {
        return $this->hasMany(ProductListing::class);
    }
}