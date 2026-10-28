<?php

namespace App\Services\AI;

/**
 * Selects the small set of deterministic facts permitted in a future AI request.
 * User identity, precise origin, database IDs, source records, and arbitrary fields
 * are deliberately excluded.
 */
final class ProductAssistantAiContextBuilder
{
    /**
     * @param  array<string, mixed>  $analysis  DeterministicMarketAnalysisService output
     * @return array<string, mixed>
     */
    public function build(array $analysis): array
    {
        $input = is_array($analysis['input_context'] ?? null) ? $analysis['input_context'] : [];
        $commodity = is_array($analysis['commodity_resolution'] ?? null) ? $analysis['commodity_resolution'] : [];
        $psa = is_array($analysis['psa_reference'] ?? null) ? $analysis['psa_reference'] : [];
        $latestPsa = is_array($psa['latest_available_observation'] ?? null)
            ? $psa['latest_available_observation']
            : [];
        $psaCandidates = [];
        $candidates = is_array($analysis['psa_reference_candidates'] ?? null)
            ? $analysis['psa_reference_candidates']
            : [];
        foreach (array_slice($candidates, 0, 20) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $candidateReference = is_array($candidate['psa_reference'] ?? null)
                ? $candidate['psa_reference']
                : [];
            $candidateLatest = is_array($candidateReference['latest_available_observation'] ?? null)
                ? $candidateReference['latest_available_observation']
                : [];

            $psaCandidates[] = [
                'provenance_status' => $this->scalar($candidateReference['provenance_status'] ?? null),
                'provenance_notice' => $this->scalar($candidateReference['provenance_notice'] ?? null),
                'source' => $this->scalar($candidateReference['source'] ?? null),
                'raw_observation_count' => $this->scalar($candidateReference['raw_observation_count'] ?? null),
                'excluded_zero_count' => $this->scalar($candidateReference['excluded_zero_count'] ?? null),
                'comparison_policy' => $this->scalar($candidateReference['comparison_policy'] ?? null),
                'commodity_label' => $this->scalar($candidate['commodity_label'] ?? null),
                'match_method' => $this->scalar($candidate['match_method'] ?? null),
                'observation_count' => $this->scalar($candidateReference['observation_count'] ?? null),
                'minimum_price' => $this->scalar($candidateReference['minimum_price'] ?? null),
                'maximum_price' => $this->scalar($candidateReference['maximum_price'] ?? null),
                'average_price' => $this->scalar($candidateReference['average_price'] ?? null),
                'median_price' => $this->scalar($candidateReference['median_price'] ?? null),
                'first_observation_date' => $this->scalar($candidateReference['first_observation_date'] ?? null),
                'latest_available_observation' => $this->pick($candidateLatest, ['date', 'period', 'price']),
                'producer_price_comparison' => $this->pick(
                    is_array($candidate['producer_price_comparison'] ?? null)
                        ? $candidate['producer_price_comparison']
                        : [],
                    [
                        'status',
                        'input_price',
                        'input_currency',
                        'input_unit',
                        'reference_average_price',
                        'difference_from_average',
                        'difference_percent_from_average',
                        'position_relative_to_range',
                    ]
                ),
            ];
        }
        $seasonality = is_array($analysis['seasonality'] ?? null) ? $analysis['seasonality'] : [];
        $analysisDate = $this->scalar($analysis['analysis_date'] ?? null);

        $seasonalityReferences = [];
        $references = is_array($seasonality['references'] ?? null) ? $seasonality['references'] : [];
        foreach (array_slice($references, 0, 12) as $reference) {
            if (is_array($reference)) {
                $selectedReference = $this->pick($reference, [
                    'commodity_label',
                    'status',
                    'geographic_scope',
                    'source_name',
                    'organization',
                    'source_url',
                    'source_reference',
                    'source_label',
                    'reference_date',
                    'interpretation',
                ]);
                foreach (['periods', 'rows'] as $listKey) {
                    $values = is_array($reference[$listKey] ?? null) ? $reference[$listKey] : [];
                    if ($listKey === 'periods') {
                        $selectedReference[$listKey] = array_values(array_filter(array_map(
                            fn (mixed $value) => $this->scalar($value),
                            array_slice($values, 0, 24)
                        ), fn (mixed $value): bool => is_string($value) && $value !== ''));
                    } else {
                        $selectedReference[$listKey] = array_values(array_filter(array_map(
                            fn (mixed $row) => is_array($row) ? $this->pick($row, [
                                'month',
                                'period_label',
                                'season_status',
                                'start_month',
                                'end_month',
                                'peak_period',
                                'reference_date',
                                'notes',
                            ]) : [],
                            array_slice($values, 0, 24)
                        ), fn (array $row): bool => $row !== []));
                    }
                }
                $seasonalityReferences[] = $selectedReference;
            }
        }

        $seasonalityCandidates = [];
        $candidates = is_array($seasonality['candidates'] ?? null) ? $seasonality['candidates'] : [];
        foreach (array_slice($candidates, 0, 20) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $candidateReferences = [];
            foreach (array_slice(is_array($candidate['references'] ?? null) ? $candidate['references'] : [], 0, 12) as $reference) {
                if (!is_array($reference)) {
                    continue;
                }
                $candidateReference = $this->pick($reference, [
                    'status',
                    'geographic_scope',
                    'source_name',
                    'source_reference',
                    'reference_date',
                    'interpretation',
                ]);
                $candidateReference['periods'] = array_values(array_filter(array_map(
                    fn (mixed $period) => $this->scalar($period),
                    array_slice(is_array($reference['periods'] ?? null) ? $reference['periods'] : [], 0, 24)
                ), fn (mixed $period): bool => is_string($period) && $period !== ''));
                $candidateReferences[] = $candidateReference;
            }
            $seasonalityCandidates[] = [
                'commodity_label' => $this->scalar($candidate['commodity_label'] ?? null),
                'references' => $candidateReferences,
            ];
        }

        return [
            'analysis_date' => $analysisDate === self::OMIT ? null : $analysisDate,
            'producer_input' => $this->pick($input, [
                'product_name',
                'category',
                'unit',
                'quantity',
                'producer_price',
                'currency',
                'quality_grade',
            ]),
            'commodity_resolution' => $this->pick($commodity, [
                'status',
                'resolved_by',
                'canonical_name',
                'candidate_count',
            ]),
            'psa_region_ii_series' => $psaCandidates,
            'psa_region_ii_reference' => array_merge([
                'source' => $this->scalar($psa['source'] ?? null),
                'geography' => 'Region II / Cagayan Valley',
                'price_type' => 'farmgate',
                'currency' => 'PHP',
                'unit' => 'PHP/kg',
            ], $this->pick($psa, [
                'provenance_status', 'provenance_notice', 'independently_authenticated',
                'raw_observation_count', 'comparable_observation_count', 'excluded_observation_count',
                'excluded_zero_count', 'synthetic_observation_count', 'comparison_policy',
                'status',
                'observation_count',
                'minimum_price',
                'maximum_price',
                'average_price',
                'median_price',
                'first_observation_date',
            ]), [
                'latest_available_observation' => $this->pick($latestPsa, ['date', 'period', 'price']),
            ]),
            'producer_price_comparison' => $this->pick(
                is_array($analysis['producer_price_comparison'] ?? null)
                    ? $analysis['producer_price_comparison']
                    : [],
                [
                    'status',
                    'input_price',
                    'normalized_price_per_kg',
                    'reference_average_price',
                    'difference_from_average',
                    'difference_percent_from_average',
                    'position_relative_to_range',
                ]
            ),
            'harborai_listing_reference' => $this->pick(
                is_array($analysis['harborai_listing_reference'] ?? null)
                    ? $analysis['harborai_listing_reference']
                    : [],
                [
                    'active_listing_count',
                    'active_listing_price_count',
                    'incompatible_price_listing_count',
                    'minimum_active_listing_price',
                    'maximum_active_listing_price',
                    'average_active_listing_price',
                    'price_currency',
                    'price_unit',
                ]
            ),
            'harborai_supply_context' => $this->pick(
                is_array($analysis['supply_context'] ?? null) ? $analysis['supply_context'] : [],
                ['status', 'quantity_total', 'quantity_unit', 'quantity_status']
            ),
            'harborai_buyer_requests' => $this->pick(
                is_array($analysis['demand_context'] ?? null) ? $analysis['demand_context'] : [],
                [
                    'status',
                    'active_demand_request_count',
                    'requested_quantity_total',
                    'quantity_unit',
                    'quantity_status',
                ]
            ),
            'seasonality' => array_merge(
                $this->pick($seasonality, [
                    'status',
                    'analysis_month',
                    'analysis_month_name',
                    'interpretation',
                    'geographic_limitation',
                    'validated_reference_count',
                ]),
                [
                    'references' => $seasonalityReferences,
                    'candidates' => $seasonalityCandidates,
                ]
            ),
            'quality_context' => $this->pick(
                is_array($analysis['quality_context'] ?? null) ? $analysis['quality_context'] : [],
                ['price_adjustment_applied', 'status']
            ),
            'data_sufficiency' => $this->pick(
                is_array($analysis['data_sufficiency'] ?? null) ? $analysis['data_sufficiency'] : [],
                ['status', 'basis']
            ),
        ];
    }

    /** @param array<string, mixed> $source @param list<string> $keys @return array<string, scalar|null> */
    private function pick(array $source, array $keys): array
    {
        $selected = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $value = $this->scalar($source[$key]);
                if ($value !== self::OMIT) {
                    $selected[$key] = $value;
                }
            }
        }

        return $selected;
    }

    private const OMIT = "\0omit\0";

    private function scalar(mixed $value): string|int|float|bool|null
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return is_finite($value) ? $value : self::OMIT;
        }
        if (is_string($value)) {
            $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($value));
            if ($normalized === null) {
                return self::OMIT;
            }

            return mb_substr($normalized, 0, 255);
        }

        return self::OMIT;
    }
}
