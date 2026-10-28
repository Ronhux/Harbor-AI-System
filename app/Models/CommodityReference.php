<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommodityReference extends Model
{
    public const CATEGORIES = ['Grains', 'Fish', 'Vegetables', 'Fruits', 'Livestock'];

    protected $fillable = [
        'canonical_name',
        'normalized_name',
        'category',
        'aliases',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $commodity): void {
            if ($commodity->isDirty('canonical_name') || blank($commodity->normalized_name)) {
                $commodity->normalized_name = Str::slug(trim((string) $commodity->canonical_name));
            }

            Validator::make($commodity->attributesToArray(), [
                'canonical_name' => ['required', 'string', 'max:255'],
                'normalized_name' => ['required', 'string', 'max:255'],
                'category' => ['required', Rule::in(self::CATEGORIES)],
                'aliases' => ['nullable', 'array'],
                'notes' => ['nullable', 'string'],
                'is_active' => ['boolean'],
            ])->validate();
        });
    }

    public function marketPrices()
    {
        return $this->hasMany(MarketPriceReference::class, 'commodity_reference_id');
    }

    public function seasonalityReferences()
    {
        return $this->hasMany(SeasonalityReference::class, 'commodity_reference_id');
    }

    public function unitConversions()
    {
        return $this->hasMany(UnitConversionReference::class, 'commodity_reference_id');
    }
}
