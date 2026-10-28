<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MarketPriceReference extends Model
{
    public const PRICE_TYPES = [
        'farmgate',
        'wholesale',
        'retail',
        'harborai_listing',
        'negotiated_transaction',
    ];

    public const VERIFICATION_STATUSES = ['unverified', 'pending', 'verified', 'rejected'];
    public const PERIOD_FREQUENCIES = ['daily', 'monthly', 'quarterly', 'annual', 'other'];
    public const OBSERVATION_STATUSES = ['not_stated', 'final', 'preliminary', 'revised'];

    protected $attributes = [
        'currency' => 'PHP',
        'verification_status' => 'unverified',
        'observation_status' => 'not_stated',
    ];

    protected $fillable = [
        'commodity_reference_id',
        'ai_data_source_id',
        'price',
        'currency',
        'unit',
        'price_type',
        'geographic_scope',
        'source_url',
        'source_reference',
        'source_commodity_name',
        'source_geographic_scope',
        'observation_date',
        'observation_period',
        'period_frequency',
        'observation_status',
        'source_retrieved_at',
        'deduplication_key',
        'verification_status',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'observation_date' => 'date',
        'source_retrieved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $reference): void {
            Validator::make($reference->attributesToArray(), [
                'commodity_reference_id' => ['required', 'integer', 'exists:commodity_references,id'],
                'ai_data_source_id' => ['required', 'integer', 'exists:ai_data_sources,id'],
                // Preserve explicit official farmgate zeroes without loosening other price records.
                'price' => [
                    'required',
                    'numeric',
                    function (string $attribute, mixed $value, \Closure $fail) use ($reference): void {
                        $amount = (float) $value;
                        if ($amount < 0) {
                            $fail('The price must be greater than zero, except for zero-valued official farmgate observations.');
                            return;
                        }

                        if ($amount === 0.0) {
                            $allowsOfficialZero = $reference->price_type === 'farmgate'
                                && AiDataSource::query()
                                    ->whereKey($reference->ai_data_source_id)
                                    ->where('source_type', AiDataSource::TYPE_OFFICIAL_REFERENCE)
                                ->where('is_official', true)
                                    ->exists();

                            if (!$allowsOfficialZero) {
                                $fail('The price must be greater than zero, except for zero-valued official farmgate observations.');
                            }
                        }
                    },
                ],
                'currency' => ['required', 'string', 'size:3'],
                'unit' => ['required', 'string', 'max:50'],
                'price_type' => ['required', Rule::in(self::PRICE_TYPES)],
                'geographic_scope' => ['required', 'string', 'max:255'],
                'source_url' => ['nullable', 'url', 'max:255'],
                'source_reference' => ['nullable', 'string', 'max:255'],
                'source_commodity_name' => ['nullable', 'string', 'max:255'],
                'source_geographic_scope' => ['nullable', 'string', 'max:255'],
                'observation_date' => ['required', 'date'],
                'observation_period' => ['nullable', 'string', 'max:50', 'required_with:period_frequency'],
                'period_frequency' => ['nullable', Rule::in(self::PERIOD_FREQUENCIES), 'required_with:observation_period'],
                'observation_status' => ['required', Rule::in(self::OBSERVATION_STATUSES)],
                'source_retrieved_at' => ['nullable', 'date'],
                'deduplication_key' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
                'verification_status' => ['required', Rule::in(self::VERIFICATION_STATUSES)],
                'notes' => ['nullable', 'string'],
            ])->validate();
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
