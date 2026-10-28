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
        'preferred_delivery_date',
        'quality_requirements',
        'special_requirements',
        'partial_fulfillment',
    ];

    protected $casts = [
        'deadline' => 'date',
        'preferred_delivery_date' => 'date',
        'partial_fulfillment' => 'boolean',
    ];

    public static function publicationReviewAvailable(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('demand_publication_reviews');
    }

    public function currentPublicationReview()
    {
        return self::publicationReviewAvailable() ? $this->publicationReview : null;
    }

    public function publicationReviews()
    {
        return $this->hasMany(DemandPublicationReview::class, 'demand_request_id', 'request_id');
    }

    public function publicationReview()
    {
        return $this->hasOne(DemandPublicationReview::class, 'demand_request_id', 'request_id')->latestOfMany();
    }

    public function scopePublished($query)
    {
        if (!self::publicationReviewAvailable()) return $query->whereRaw('1 = 0');
        return $query->whereHas('publicationReview', fn ($review) => $review->where('decision', 'Approved'));
    }

    public function buyer()
    {
        return $this->belongsTo(Buyer::class, 'buyer_id', 'buyer_id');
    }

    public function demandDetails()
    {
        // Legacy demand specifications have no producer and are not submitted offers.
        return $this->hasMany(DemandDetail::class, 'demand_request_id', 'request_id')->whereNotNull('producer_id');
    }
}
