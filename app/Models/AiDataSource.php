<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AiDataSource extends Model
{
    public const TYPE_HARBORAI_ACTUAL = 'harborai_actual';
    public const TYPE_OFFICIAL_REFERENCE = 'official_reference';
    public const TYPE_SYNTHETIC = 'synthetic_simulated';
    public const SOURCE_PSA_MAJOR_CROPS = 'PSA OpenSTAT - Major Crops Farmgate Prices by Region, Monthly';
    public const PSA_MAJOR_CROPS_TABLE_ID = '0142M4EFGP0';

    public const TYPES = [
        self::TYPE_HARBORAI_ACTUAL,
        self::TYPE_OFFICIAL_REFERENCE,
        self::TYPE_SYNTHETIC,
    ];

    protected $fillable = [
        'source_name',
        'source_type',
        'source_url',
        'organization',
        'geographic_scope',
        'description',
        'is_official',
        'is_active',
    ];

    protected $casts = [
        'is_official' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $source): void {
            Validator::make($source->attributesToArray(), [
                'source_name' => ['required', 'string', 'max:255'],
                'source_type' => ['required', Rule::in(self::TYPES)],
                'source_url' => ['nullable', 'url', 'max:255'],
                'organization' => ['nullable', 'string', 'max:255'],
                'geographic_scope' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'is_official' => ['boolean'],
                'is_active' => ['boolean'],
            ])->validate();
        });
    }

    public function marketPrices()
    {
        return $this->hasMany(MarketPriceReference::class, 'ai_data_source_id');
    }

    public function seasonalityReferences()
    {
        return $this->hasMany(SeasonalityReference::class, 'ai_data_source_id');
    }

    public function unitConversions()
    {
        return $this->hasMany(UnitConversionReference::class, 'ai_data_source_id');
    }
}
