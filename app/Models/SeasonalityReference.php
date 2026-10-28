<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SeasonalityReference extends Model
{
    public const SEASON_STATUSES = ['planting', 'growing', 'harvest', 'peak', 'off_season', 'year_round', 'other'];
    public const VERIFICATION_STATUSES = ['unverified', 'pending', 'verified', 'rejected'];

    protected $attributes = ['verification_status' => 'unverified'];

    protected $fillable = [
        'commodity_reference_id',
        'ai_data_source_id',
        'month',
        'period_label',
        'season_status',
        'start_month',
        'end_month',
        'peak_period',
        'production_duration_days',
        'geographic_scope',
        'source_url',
        'source_reference',
        'reference_date',
        'verification_status',
        'notes',
    ];

    protected $casts = [
        'month' => 'integer',
        'start_month' => 'integer',
        'end_month' => 'integer',
        'production_duration_days' => 'integer',
        'reference_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $reference): void {
            Validator::make($reference->attributesToArray(), [
                'commodity_reference_id' => ['required', 'integer', 'exists:commodity_references,id'],
                'ai_data_source_id' => ['required', 'integer', 'exists:ai_data_sources,id'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'period_label' => ['nullable', 'string', 'max:255'],
                'season_status' => ['required', Rule::in(self::SEASON_STATUSES)],
                'start_month' => ['nullable', 'integer', 'between:1,12', 'required_with:end_month'],
                'end_month' => ['nullable', 'integer', 'between:1,12', 'required_with:start_month'],
                'peak_period' => ['nullable', 'string', 'max:255'],
                'production_duration_days' => ['nullable', 'integer', 'min:1'],
                'geographic_scope' => ['required', 'string', 'max:255'],
                'source_url' => ['nullable', 'url', 'max:255'],
                'source_reference' => ['nullable', 'string', 'max:255'],
                'reference_date' => ['nullable', 'date'],
                'verification_status' => ['required', Rule::in(self::VERIFICATION_STATUSES)],
                'notes' => ['nullable', 'string'],
            ])->after(function ($validator) use ($reference): void {
                if ($reference->month === null && blank($reference->period_label)
                    && ($reference->start_month === null || $reference->end_month === null)) {
                    $validator->errors()->add('month', 'Provide a month, a period label, or a start and end month.');
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
