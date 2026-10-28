<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UnitConversionReference extends Model
{
    public const VERIFICATION_STATUSES = ['unverified', 'pending', 'verified', 'rejected'];

    protected $attributes = ['verification_status' => 'unverified'];

    protected $fillable = [
        'commodity_reference_id',
        'from_unit',
        'to_unit',
        'conversion_factor',
        'ai_data_source_id',
        'source_url',
        'source_reference',
        'verification_status',
        'effective_date',
        'notes',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:9',
        'effective_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $reference): void {
            Validator::make($reference->attributesToArray(), [
                'commodity_reference_id' => ['nullable', 'integer', 'exists:commodity_references,id'],
                'from_unit' => ['required', 'string', 'max:50', 'different:to_unit'],
                'to_unit' => ['required', 'string', 'max:50'],
                'conversion_factor' => ['required', 'numeric', 'gt:0'],
                'ai_data_source_id' => ['nullable', 'integer', 'exists:ai_data_sources,id'],
                'source_url' => ['nullable', 'url', 'max:255'],
                'source_reference' => ['nullable', 'string', 'max:255'],
                'verification_status' => ['required', Rule::in(self::VERIFICATION_STATUSES)],
                'effective_date' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
            ])->after(function ($validator) use ($reference): void {
                if (!$reference->ai_data_source_id && blank($reference->source_reference) && blank($reference->source_url)) {
                    $validator->errors()->add('source_reference', 'Provide a data source or source reference.');
                }
            })->validate();
        });
    }

    public function commodity()
    {
        return $this->belongsTo(CommodityReference::class, 'commodity_reference_id');
    }

    public function dataSource()
    {
        return $this->belongsTo(AiDataSource::class, 'ai_data_source_id');
    }
}
