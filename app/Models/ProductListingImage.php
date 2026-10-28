<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductListingImage extends Model
{
    protected $fillable = ['listing_id', 'path', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function listing()
    {
        return $this->belongsTo(ProductListing::class, 'listing_id', 'listing_id');
    }
}
