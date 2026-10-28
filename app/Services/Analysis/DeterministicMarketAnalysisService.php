<?php

namespace App\Services\Analysis;

use App\Models\AiDataSource;
use App\Models\CommodityReference;
use App\Models\DemandRequest;
use App\Models\MarketPriceReference;
use App\Services\Seasonality\SeasonalityReferenceService;
use App\Models\ProductListing;
use App\Models\SeasonalityReference;
use App\Models\UnitConversionReference;
use App\Services\Imports\ImportPsaMajorCropsFarmgatePrices;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeterministicMarketAnalysisService
{
    public const PRICE_POSITION_BELOW = 'BELOW_REFERENCE_RANGE';
    public const PRICE_POSITION_WITHIN = 'WITHIN_REFERENCE_RANGE';
    public const PRICE_POSITION_ABOVE = 'ABOVE_REFERENCE_RANGE';
    public const PRICE_POSITION_NO_REFERENCE = 'NO_REFERENCE_AVAILABLE';

    private const REGION_II_SCOPE = ImportPsaMajorCropsFarmgatePrices::GEOGRAPHIC_SCOPE;
    private const SOURCE_SCOPE_LABEL = 'PSA OpenSTAT';
    private const ACTIVE_LISTING_STATUSES = ['active', 'available'];
    private const ACTIVE_DEMAND_STATUSES = ['Active', 'Offers Received', 'open'];
    private const RELATED_SEARCH_TERMS = [
        'rice' => ['palay'],
        'palay' => ['rice'],
    ];

    /**
     * A generic stem can resolve to one mapped PSA series while a sibling PSA
     * series for the same stem has no CommodityReference at all. The service
     * never invents that sibling series, never merges it, and never fabricates
     * statistics for it; it only discloses that the reference data shown covers
     * a subset of the source's own commodity labels.
     *
     * These labels are intentionally absent from commodity_references, are never
     * imported, and are never used as a match target. They exist only so a
     * generic search term can state its own coverage limit honestly instead of
     * implying that one mapped series represents every PSA series for that crop.
     *
     * @var list<string>
     */
    private const UNMAPPED_SOURCE_SIBLINGS = [
        'Cassava for industrial use, fresh tubers',
    ];

    /**
     * Calculate market context from stored reference and HarborAI platform data.
     * This service performs no writes and does not generate recommendations.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function analyze(array $context): array
    {
        $analysisDate = isset($context['analysis_date']) && trim((string) $context['analysis_date']) !== ''
            ? CarbonImmutable::parse((string) $context['analysis_date'])->startOfDay()
            : CarbonImmutable::today();
        $commodityReferences = CommodityReference::query()->where('is_active', true)->get();
        $matches = $this->resolveCommodityMatches($context, $commodityReferences, $analysisDate);
        $commodity = count($matches) === 1 ? $matches[0]['commodity'] : null;
        $unitInput = $this->optionalString($context['unit'] ?? null);
        $normalizedUnit = $this->normalizeUnit($unitInput);
        $quantity = $this->nonNegativeNumber($context['quantity'] ?? null);
        $producerPrice = $this->nonNegativeNumber($context['producer_price'] ?? null);
        $currency = strtoupper($this->optionalString($context['currency'] ?? null) ?? 'PHP');
        $qualityGrade = $this->optionalString($context['quality_grade'] ?? $context['quality'] ?? null);
        $conversions = UnitConversionReference::query()
            ->where('verification_status', 'verified')
            ->get();

        $psaReferenceCandidates = [];
        foreach ($matches as $match) {
            /** @var CommodityReference $candidate */
            $candidate = $match['commodity'];
            $observations = $this->psaObservations($candidate, $analysisDate, $conversions);
            $reference = $this->psaReferenceSummary($observations, $candidate, $analysisDate);
            $psaReferenceCandidates[] = [
                'commodity_label' => $candidate->canonical_name,
                'match_method' => $match['method'],
                'match_confidence' => $match['confidence'],
                'psa_reference' => $reference,
                'producer_price_comparison' => $this->compareProducerPrice(
                    $producerPrice,
                    $currency,
                    $normalizedUnit,
                    $reference,
                    $candidate,
                    $analysisDate,
                    $conversions
                ),
            ];
        }

        $psaReference = count($psaReferenceCandidates) === 1
            ? $psaReferenceCandidates[0]['psa_reference']
            : $this->psaReferenceSummary(collect());
        if (count($psaReferenceCandidates) > 1) {
            $psaReference['status'] = 'MULTIPLE_REFERENCES';
        }

        $priceComparison = count($psaReferenceCandidates) === 1
            ? $psaReferenceCandidates[0]['producer_price_comparison']
            : $this->compareProducerPrice(
                $producerPrice,
                $currency,
                $normalizedUnit,
                $psaReference,
                null,
                $analysisDate,
                $conversions
            );
        if (count($psaReferenceCandidates) > 1) {
            $priceComparison['status'] = 'MULTIPLE_REFERENCES';
            $priceComparison['position_relative_to_range'] = null;
        }

        $inputProductName = $this->optionalString($context['product_name'] ?? null);
        $listingLabels = $commodity
            ? $this->commodityLabels($commodity)
            : $this->normalizedSearchLabels($inputProductName);
        $listings = $this->activeListingsByLabels($listingLabels);
        $listingContext = $this->listingContext($listings, $commodity, $normalizedUnit, $analysisDate, $conversions);
        $demandLabels = $commodity
            ? $this->commodityLabels($commodity)
            : $this->normalizedSearchLabels($inputProductName);
        $demands = $this->activeDemandsByLabels($demandLabels, $analysisDate);
        $demandContext = $this->demandContext($demands, $commodity, $normalizedUnit, $analysisDate, $conversions);
        $seasonality = $this->seasonalityContext($matches, $analysisDate, $this->optionalString($context['product_name'] ?? null));
        $grossListingValue = $this->grossListingValue($quantity, $producerPrice, $unitInput, $normalizedUnit, $currency);
        $dataSufficiency = $this->analysisDataSufficiency(
            $psaReferenceCandidates,
            $commodity,
            $priceComparison['status'],
            $listingContext['active_listing_count'],
            $demandContext['active_demand_request_count']
        );
        $resolutionStatus = match (count($matches)) {
            1 => 'RESOLVED',
            default => count($matches) > 1 ? 'MULTIPLE_MATCHES' : 'UNRESOLVED',
        };
        $sourceCoverage = $this->sourceCoverageContext($matches, $context);

        return [
            'analysis_date' => $analysisDate->toDateString(),
            'input_context' => [
                'product_name' => $this->optionalString($context['product_name'] ?? null),
                'normalized_commodity' => $this->optionalString($context['normalized_commodity'] ?? null),
                'category' => $this->optionalString($context['category'] ?? null),
                'origin' => $this->optionalString($context['origin'] ?? null),
                'unit' => $unitInput,
                'quantity' => $quantity,
                'producer_price' => $producerPrice,
                'currency' => $currency,
                'quality_grade' => $qualityGrade,
            ],
            'commodity_resolution' => [
                'status' => $resolutionStatus,
                'resolved_by' => count($matches) === 1 ? $matches[0]['method'] : null,
                'commodity_reference_id' => $commodity?->getKey(),
                'canonical_name' => $commodity?->canonical_name,
                'category' => $commodity?->category,
                'candidate_count' => count($matches),
                'candidates' => array_map(fn (array $match): array => [
                    'canonical_name' => $match['commodity']->canonical_name,
                    'match_method' => $match['method'],
                    'match_confidence' => $match['confidence'],
                ], $matches),
                'source_coverage' => $sourceCoverage,
            ],
            'psa_reference' => $psaReference,
            'psa_reference_candidates' => $psaReferenceCandidates,
            'producer_price_comparison' => $priceComparison,
            'harborai_listing_reference' => $listingContext,
            'supply_context' => [
                'scope' => 'HarborAI active public listings from verified producers; in-stock only',
                'status' => $listingContext['active_listing_count'] > 0 ? 'AVAILABLE' : 'NONE',
                'active_listing_count' => $listingContext['active_listing_count'],
                'quantity_total' => $listingContext['active_listing_quantity_total'],
                'quantity_unit' => $listingContext['quantity_unit'],
                'quantity_status' => $listingContext['quantity_status'],
            ],
            'demand_context' => $demandContext,
            'gross_listing_value' => $grossListingValue,
            'seasonality' => $seasonality,
            'quality_context' => [
                'quality_grade' => $qualityGrade,
                'price_adjustment_applied' => false,
                'status' => $qualityGrade === null
                    ? 'NOT_PROVIDED'
                    : 'NO_VALIDATED_QUALITY_PRICE_RELATIONSHIP',
            ],
            'data_sufficiency' => $dataSufficiency,
        ];
    }

    /**
     * @return list<array{commodity: CommodityReference, method: string, confidence: float}>
     */
    private function resolveCommodityMatches(
        array $context,
        EloquentCollection $references,
        CarbonImmutable $analysisDate
    ): array
    {
        if (isset($context['commodity_reference_id'])
            && filter_var($context['commodity_reference_id'], FILTER_VALIDATE_INT) !== false) {
            $commodity = $references->firstWhere('id', (int) $context['commodity_reference_id']);
            if ($commodity) {
                return [[
                    'commodity' => $commodity,
                    'method' => 'COMMODITY_REFERENCE_ID',
                    'confidence' => 1.0,
                ]];
            }
            return [];
        }

        $terms = [];
        $exactFallback = null;
        $seriesIds = $this->psaSeriesCommodityIds($analysisDate);
        foreach (['normalized_commodity', 'product_name'] as $inputKey) {
            $term = $this->optionalString($context[$inputKey] ?? null);
            if ($term === null) {
                continue;
            }
            $terms[] = $term;

            $exact = collect();
            $method = $inputKey === 'product_name' ? 'EXACT_PRODUCT_NAME' : 'NORMALIZED_COMMODITY';
            if ($inputKey === 'normalized_commodity') {
                $normalized = $references->filter(
                    fn (CommodityReference $reference) => mb_strtolower($reference->normalized_name) === mb_strtolower($term)
                );
                if ($normalized->isNotEmpty()) {
                    $exact = $normalized;
                }
            }

            if ($exact->isEmpty()) {
                $exact = $this->exactCommodityMatches($term, $references);
            }
            if ($exact->isNotEmpty()) {
                $seriesExact = $exact->whereIn('id', $seriesIds);
                if ($seriesExact->isNotEmpty()) {
                    return $seriesExact->map(fn (CommodityReference $commodity): array => [
                        'commodity' => $commodity,
                        'method' => $method,
                        'confidence' => 1.0,
                    ])->values()->all();
                }
                $exactFallback ??= $exact->map(fn (CommodityReference $commodity): array => [
                    'commodity' => $commodity,
                    'method' => $method,
                    'confidence' => 1.0,
                ])->values()->all();
            }
        }

        if ($seriesIds === []) {
            return $exactFallback ?? [];
        }

        $seriesReferences = $references->whereIn('id', $seriesIds)->values();
        $ranked = [];
        foreach ($terms as $term) {
            foreach ($this->relatedSearchTerms($term) as $searchTerm) {
                $normalizedInput = $this->normalizeCommodityText($searchTerm);
                $inputTokens = $this->commodityTokens($normalizedInput);
                $compactInput = $this->compactCommodityText($normalizedInput);
                if ($compactInput === '' || mb_strlen($compactInput) < 3) {
                    continue;
                }

                foreach ($seriesReferences as $reference) {
                    $candidateText = $this->normalizeCommodityText($reference->canonical_name);
                    $candidateTokens = $this->commodityTokens($candidateText);
                    $candidateCompact = $this->compactCommodityText($candidateText);
                    $method = null;
                    $confidence = 0.0;

                    if ($candidateCompact !== '' && str_starts_with($candidateCompact, $compactInput)) {
                        $method = 'PSA_SERIES_PREFIX';
                        $confidence = 0.92;
                    } elseif (count($inputTokens) === 1 && count($candidateTokens) > 0) {
                        $firstToken = $candidateTokens[0];
                        $length = max(mb_strlen($inputTokens[0]), mb_strlen($firstToken));
                        $distance = levenshtein($inputTokens[0], $firstToken);
                        $similarity = $length > 0 ? 1 - ($distance / $length) : 0.0;
                        if (min(mb_strlen($inputTokens[0]), mb_strlen($firstToken)) >= 5
                            && $similarity >= 0.85) {
                            $method = 'CONSERVATIVE_TOKEN_SIMILARITY';
                            $confidence = round($similarity, 2);
                        }
                    }

                    if ($method !== null) {
                        $current = $ranked[$reference->getKey()] ?? null;
                        if ($current === null || $confidence > $current['confidence']) {
                            $ranked[$reference->getKey()] = [
                                'commodity' => $reference,
                                'method' => $method,
                                'confidence' => $confidence,
                            ];
                        }
                    }
                }
            }
        }

        return array_values($ranked) ?: ($exactFallback ?? []);
    }

    /**
     * State which mapped series the returned reference data actually covers, and
     * disclose any sibling PSA series for the same crop that has no
     * CommodityReference.
     *
     * This is disclosure only. It never adds, merges, substitutes, or estimates a
     * series, and it never affects the numerical market analysis.
     *
     * @param  list<array{commodity: CommodityReference, method: string, confidence: float}>  $matches
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sourceCoverageContext(array $matches, array $context): array
    {
        $mappedSeries = array_values(array_unique(array_map(
            fn (array $match): string => $match['commodity']->canonical_name,
            $matches
        )));

        $covered = $mappedSeries;
        $unmappedSiblings = [];
        if ($matches !== []) {
            foreach ($this->searchTermsFromContext($context) as $term) {
                $compactTerm = $this->compactCommodityText($this->normalizeCommodityText($term));
                if ($compactTerm === '' || mb_strlen($compactTerm) < 3) {
                    continue;
                }

                foreach (self::UNMAPPED_SOURCE_SIBLINGS as $sibling) {
                    $compactSibling = $this->compactCommodityText($this->normalizeCommodityText($sibling));
                    if ($compactSibling === '' || !str_starts_with($compactSibling, $compactTerm)) {
                        continue;
                    }

                    // A sibling that IS mapped is already reported as its own
                    // candidate, so it is not an unmapped coverage gap.
                    $isMapped = collect($mappedSeries)->contains(
                        fn (string $name): bool => $this->normalizeCommodityText($name)
                            === $this->normalizeCommodityText($sibling)
                    );
                    if ($isMapped) {
                        continue;
                    }

                    if (!in_array($sibling, $unmappedSiblings, true)) {
                        $unmappedSiblings[] = $sibling;
                    }
                }
            }
        }

        $status = match (true) {
            $unmappedSiblings !== [] => 'PARTIAL_SOURCE_COVERAGE',
            $covered !== [] => 'COMPLETE_MAPPED_COVERAGE',
            default => 'NO_MATCHED_SERIES',
        };

        $interpretation = match ($status) {
            'PARTIAL_SOURCE_COVERAGE' => 'The PSA source publishes additional series for this product that have no '
                . 'registered commodity reference. Only the listed mapped series are represented here. '
                . 'Statistics are never combined with, or substituted by, an unmapped series.',
            'COMPLETE_MAPPED_COVERAGE' => 'Every PSA series matched for this product has a registered commodity '
                . 'reference, so the figures shown cover the full matched set.',
            default => 'No mapped commodity series was matched for this product.',
        };

        return [
            'status' => $status,
            'covered_series' => $covered,
            'unmapped_source_siblings' => $unmappedSiblings,
            'interpretation' => $interpretation,
        ];
    }

    /**
     * The exact search terms a caller supplied, before related-term expansion.
     *
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function searchTermsFromContext(array $context): array
    {
        $terms = [];
        foreach (['normalized_commodity', 'product_name'] as $key) {
            $term = $this->optionalString($context[$key] ?? null);
            if ($term !== null) {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }

private function exactCommodityMatches(string $term, EloquentCollection $references): Collection
    {
        $key = $this->normalizeCommodityText($term);

        return $references->filter(function (CommodityReference $reference) use ($key): bool {
            $names = [$reference->canonical_name, ...($reference->aliases ?? [])];
            foreach ($names as $name) {
                if ($this->normalizeCommodityText((string) $name) === $key) {
                    return true;
                }
            }

            return false;
        })->values();
    }
    /**
     * The single eligibility definition for PSA reference observations.
     *
     * Commodity-series discovery and observation retrieval both start from this
     * query so a series can never be advertised as available while its own rows
     * would be rejected here. It selects the official PSA source only, and keeps
     * the documented scope: Region II / Cagayan Valley, farmgate, PHP, monthly,
     * a recorded observation period, a verified observation status, and an
     * observation dated on or before the analysis date.
     *
     * Price values are deliberately not constrained here. How a published
     * numeric value is interpreted is a separate, still-unverified question and
     * is not decided by this filter.
     *
     * @return Builder<MarketPriceReference>|null null when the official PSA source is unavailable
     */
    private function psaEligibleQuery(CarbonImmutable $analysisDate)
    {
        $source = $this->psaDataSource();
        if (!$source) {
            return null;
        }

        return MarketPriceReference::query()
            ->where('ai_data_source_id', $source->getKey())
            ->where('geographic_scope', self::REGION_II_SCOPE)
            ->where('price_type', 'farmgate')
            ->where('currency', 'PHP')
            ->where('period_frequency', 'monthly')
            ->whereNotNull('observation_period')
            ->where('verification_status', 'verified')
            ->whereDate('observation_date', '<=', $analysisDate->toDateString());
    }

    /** @return list<int> */
    private function psaSeriesCommodityIds(CarbonImmutable $analysisDate): array
    {
        $query = $this->psaEligibleQuery($analysisDate);
        if (!$query) {
            return [];
        }

        return $query
            ->distinct()
            ->pluck('commodity_reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function psaDataSource(): ?AiDataSource
    {
        return AiDataSource::query()
            ->where('source_type', AiDataSource::TYPE_OFFICIAL_REFERENCE)
            ->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)
            ->where('is_official', true)
            ->where('is_active', true)
            ->first();
    }

    /** @return list<string> */
    private function relatedSearchTerms(string $term): array
    {
        $normalized = $this->normalizeCommodityText($term);
        $terms = [$normalized];
        foreach (self::RELATED_SEARCH_TERMS as $base => $related) {
            if (in_array($normalized, [$base, ...$related], true)) {
                $terms = array_merge($terms, [$base, ...$related]);
            }
        }

        return array_values(array_unique(array_filter($terms)));
    }

    private function normalizeCommodityText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function compactCommodityText(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', $value) ?? '';
    }

    /** @return list<string> */
    private function commodityTokens(string $value): array
    {
        return $value === '' ? [] : explode(' ', $value);
    }

    /** @param list<string> $names @return list<string> */
    private function normalizedSearchLabels(?string $name): array
    {
        if ($name === null) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $term) => mb_strtolower(trim($term)),
            $this->relatedSearchTerms($name)
        ))));
    }

    /** @return Collection<int, array{reference: MarketPriceReference, price_per_kg: float}> */
    private function psaObservations(
        CommodityReference $commodity,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): Collection {
        $query = $this->psaEligibleQuery($analysisDate);
        if (!$query) {
            return collect();
        }

        return $query
            ->where('commodity_reference_id', $commodity->getKey())
            ->orderBy('observation_date')
            ->orderBy('id')
            ->get()
            ->map(function (MarketPriceReference $reference) use ($commodity, $analysisDate, $conversions): ?array {
                if (!is_numeric($reference->price) || (float) $reference->price <= 0) {
                    return null;
                }
                if (!$this->hasValidMonthlyPeriod($reference)) {
                    return null;
                }

                $sourceUnit = $this->normalizeReferencePriceUnit($reference->unit);
                if ($sourceUnit === null) {
                    return null;
                }
                $pricePerKg = $this->priceIntoUnit(
                    (float) $reference->price,
                    $sourceUnit,
                    'kg',
                    $commodity,
                    $analysisDate,
                    $conversions
                );

                return $pricePerKg === null ? null : [
                    'reference' => $reference,
                    'price_per_kg' => $pricePerKg,
                ];
            })
            ->filter()
            ->values();
    }

    private function psaReferenceSummary(Collection $observations, ?CommodityReference $commodity = null, ?CarbonImmutable $analysisDate = null): array
    {
        // Raw zeros remain stored; their ordinary selling-price meaning is unestablished.
        // Counts cover source-eligible raw rows before comparability filtering.
        $raw = $commodity && $analysisDate
            ? ($this->psaEligibleQuery($analysisDate)?->where('commodity_reference_id', $commodity->getKey())->get() ?? collect())
            : collect();
        $syntheticCount = $raw->filter(fn ($row) => preg_match(
            '/synthetic|seeded for integration testing|test (?:fixture|observation)|zero price test observation/i',
            (string) $row->notes . ' ' . (string) $row->source_reference
        ) === 1)->count();
        $provenance = $raw->isEmpty() ? 'NO_OBSERVATIONS'
            : ($syntheticCount === $raw->count() ? 'SYNTHETIC'
                : ($syntheticCount > 0 ? 'MIXED_SYNTHETIC_AND_SOURCE_ATTRIBUTED' : 'SOURCE_ATTRIBUTED'));
        $synthetic = $syntheticCount > 0;
        $prices = $observations->pluck('price_per_kg')->all();
        $statistics = $this->statistics($prices);
        $first = $observations->first();
        $latest = $observations->last();
        $firstReference = $first['reference'] ?? null;
        $latestReference = $latest['reference'] ?? null;

        return [
            'status' => $statistics['observation_count'] > 0 ? 'AVAILABLE' : self::PRICE_POSITION_NO_REFERENCE,
            'source' => $synthetic ? 'Synthetic test references' : self::SOURCE_SCOPE_LABEL,
            'source_name' => $synthetic ? 'Synthetic observations; PSA metadata attribution only' : AiDataSource::SOURCE_PSA_MAJOR_CROPS,
            'source_metadata_name' => AiDataSource::SOURCE_PSA_MAJOR_CROPS,
            'provenance_status' => $provenance,
            'independently_authenticated' => false,
            'raw_observation_count' => $raw->count(),
            'comparable_observation_count' => $statistics['observation_count'],
            'excluded_observation_count' => $raw->count() - $statistics['observation_count'],
            'excluded_zero_count' => $raw->filter(fn ($row) => is_numeric($row->price) && (float) $row->price === 0.0)->count(),
            'synthetic_observation_count' => $syntheticCount,
            'comparison_policy' => 'POSITIVE_COMPARABLE_ONLY',
            'provenance_notice' => $synthetic ? 'Synthetic test data, not authenticated government price history.' : 'Source attribution is not independent authentication; dates describe historical regional observations.',
            'table_id' => AiDataSource::PSA_MAJOR_CROPS_TABLE_ID,
            'geography' => self::REGION_II_SCOPE,
            'price_type' => 'farmgate',
            'currency' => 'PHP',
            'unit' => 'PHP/kg',
            'observation_count' => $statistics['observation_count'],
            'minimum_price' => $statistics['minimum_price'],
            'maximum_price' => $statistics['maximum_price'],
            'average_price' => $statistics['average_price'],
            'median_price' => $statistics['median_price'],
            'first_observation_date' => $firstReference?->observation_date?->toDateString(),
            'latest_available_observation' => $latestReference ? [
                'label' => $synthetic ? 'Latest comparable synthetic test observation' : 'Latest available PSA Region II farmgate reference',
                'date' => $latestReference->observation_date?->toDateString(),
                'period' => $latestReference->observation_period,
                'price' => round((float) $latest['price_per_kg'], 4),
            ] : null,
        ];
    }

    private function compareProducerPrice(
        ?float $producerPrice,
        string $currency,
        ?string $unit,
        array $reference,
        ?CommodityReference $commodity,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): array {
        $position = null;
        $normalizedPrice = null;
        $difference = null;
        $differencePercent = null;
        $differenceFromLatest = null;
        $differencePercentFromLatest = null;

        if ($reference['observation_count'] === 0) {
            $status = self::PRICE_POSITION_NO_REFERENCE;
            $position = self::PRICE_POSITION_NO_REFERENCE;
        } elseif ($producerPrice === null) {
            $status = 'PRICE_NOT_PROVIDED';
        } elseif ($currency !== 'PHP') {
            $status = 'CURRENCY_INCOMPATIBLE';
        } elseif (!$commodity || !$unit
            || ($normalizedPrice = $this->priceIntoUnit(
                $producerPrice,
                $unit,
                'kg',
                $commodity,
                $analysisDate,
                $conversions
            )) === null) {
            $status = 'UNIT_INCOMPATIBLE';
            $normalizedPrice = null;
        } else {
            $status = 'COMPARABLE';
            $average = $reference['average_price'];
            $minimum = $reference['minimum_price'];
            $maximum = $reference['maximum_price'];
            $difference = round($normalizedPrice - $average, 4);
            $differencePercent = $average > 0
                ? round(($difference / $average) * 100, 2)
                : null;
            $latestPrice = $reference['latest_available_observation']['price'] ?? null;
            if (is_numeric($latestPrice)) {
                $differenceFromLatest = round($normalizedPrice - (float) $latestPrice, 4);
                $differencePercentFromLatest = (float) $latestPrice > 0
                    ? round(($differenceFromLatest / (float) $latestPrice) * 100, 2)
                    : null;
            }

            $position = $normalizedPrice < $minimum
                ? self::PRICE_POSITION_BELOW
                : ($normalizedPrice > $maximum ? self::PRICE_POSITION_ABOVE : self::PRICE_POSITION_WITHIN);
        }

        return [
            'status' => $status,
            'input_price' => $producerPrice,
            'input_currency' => $currency,
            'input_unit' => $unit,
            'normalized_price_per_kg' => $normalizedPrice === null ? null : round($normalizedPrice, 4),
            'reference_average_price' => $reference['average_price'],
            'difference_from_average' => $difference,
            'difference_percent_from_average' => $differencePercent,
            'latest_reference_price' => $reference['latest_available_observation']['price'] ?? null,
            'difference_from_latest_reference' => $differenceFromLatest,
            'difference_percent_from_latest_reference' => $differencePercentFromLatest,
            'position_relative_to_range' => $position,
            // Provenance travels WITH the comparison. Without it a percentage such as
            // "40% above the latest reference" reads as a current local market price, when the
            // figure is in fact a historical REGIONAL average. See referenceBasis().
            'reference_basis' => $this->referenceBasis($reference, $analysisDate),
        ];
    }

    /**
     * Honest description of what the compared reference actually is.
     *
     * A regional monthly series must never be presented as an Aparri price, and a historical
     * observation must never be presented as a current one. This block states the geography,
     * the observation span and how far the latest observation sits from the analysis month,
     * so any consumer of the comparison can say so in words rather than implying more than the
     * evidence supports.
     *
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed>
     */
    private function referenceBasis(array $reference, CarbonImmutable $analysisDate): array
    {
        $observationCount = (int) ($reference['observation_count'] ?? 0);
        if ($observationCount === 0) {
            return [
                'available' => false,
                'geography' => $reference['geography'] ?? null,
                'limitation' => 'No verified reference price is available, so no comparison is shown.',
            ];
        }

        $geography = (string) ($reference['geography'] ?? self::REGION_II_SCOPE);
        $first = $reference['first_observation_date'] ?? null;
        $latestRow = $reference['latest_available_observation'] ?? null;
        $latestDate = $latestRow['date'] ?? null;

        // How many months the newest observation sits BEHIND the month being analysed.
        $staleMonths = null;
        if ($latestDate) {
            try {
                $staleMonths = max(0, (int) $analysisDate->startOfMonth()
                    ->diffInMonths(CarbonImmutable::parse((string) $latestDate)->startOfMonth(), true));
            } catch (\Throwable) {
                $staleMonths = null;
            }
        }

        $parts = [];
        $parts[] = "These reference prices are {$geography} regional farmgate observations";
        $parts[] = $first && $latestDate
            ? "spanning {$first} to {$latestDate} ({$observationCount} observations across every month and year)"
            : "across {$observationCount} observations";
        $parts[] = 'not Aparri-specific prices';
        $parts[] = "not a single month-specific price";
        if ($staleMonths !== null && $staleMonths > 0) {
            $parts[] = 'the most recent observation is '
                . CarbonImmutable::parse((string) $latestDate)->format('F Y')
                . ", about {$staleMonths} month(s) before the analysed month of "
                . $analysisDate->format('F Y');
        } elseif ($staleMonths === 0) {
            $parts[] = 'the most recent observation is from the analysed month';
        }
        $parts[] = 'a current Aparri price is not available';

        return [
            'available' => true,
            'geography' => $geography,
            'is_local_to_producer' => false,
            'is_current' => $staleMonths === 0,
            'is_single_month' => false,
            'observation_count' => $observationCount,
            'first_observation_date' => $first,
            'latest_observation_date' => $latestDate,
            'months_behind_analysis' => $staleMonths,
            'unit' => $reference['unit'] ?? 'PHP/kg',
            'price_type' => $reference['price_type'] ?? 'farmgate',
            'provenance_notice' => $reference['provenance_notice'] ?? null,
            'limitation' => implode('; ', $parts) . '.',
        ];
    }

    /** @param list<string> $labels */
    private function activeListingsByLabels(array $labels): Collection
    {
        if ($labels === []) {
            return collect();
        }

        return ProductListing::query()
            ->whereIn(DB::raw('LOWER(TRIM(product_name))'), $labels)
            ->whereHas('producer', fn ($query) => $query->where('verification_status', 'Verified'))
            ->orderBy('listing_id')
            ->get()
            ->filter(function (ProductListing $listing): bool {
                $status = mb_strtolower(trim((string) $listing->status));
                $quantity = $listing->quantity_available ?? $listing->quantity;

                return in_array($status, self::ACTIVE_LISTING_STATUSES, true)
                    && is_numeric($quantity)
                    && (float) $quantity > 0;
            })
            ->values();
    }

    private function listingContext(
        Collection $listings,
        ?CommodityReference $commodity,
        ?string $targetUnit,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): array {
        $compatiblePrices = [];
        $incompatiblePriceCount = 0;
        $listingQuantities = [];
        $quantityCoverageComplete = $targetUnit !== null;

        foreach ($listings as $listing) {
            $listingUnit = $this->normalizeUnit($listing->unit_of_measure ?? $listing->unit);
            $rawPrice = $listing->current_price_per_unit ?? $listing->price_per_unit;

            if ($targetUnit && $listingUnit && is_numeric($rawPrice) && (float) $rawPrice >= 0) {
                $convertedPrice = $this->priceIntoUnit(
                    (float) $rawPrice,
                    $listingUnit,
                    $targetUnit,
                    $commodity,
                    $analysisDate,
                    $conversions
                );
                if ($convertedPrice === null) {
                    $incompatiblePriceCount++;
                } else {
                    $compatiblePrices[] = $convertedPrice;
                }
            } else {
                $incompatiblePriceCount++;
            }

            $rawQuantity = $listing->quantity_available ?? $listing->quantity;
            if (!$targetUnit || !$listingUnit || !is_numeric($rawQuantity)) {
                $quantityCoverageComplete = false;
                continue;
            }

            $convertedQuantity = $this->quantityIntoUnit(
                (float) $rawQuantity,
                $listingUnit,
                $targetUnit,
                $commodity,
                $analysisDate,
                $conversions
            );
            if ($convertedQuantity === null) {
                $quantityCoverageComplete = false;
            } else {
                $listingQuantities[] = $convertedQuantity;
            }
        }

        $priceStatistics = $this->statistics($compatiblePrices);
        $quantityTotal = null;
        if ($targetUnit && $listings->isEmpty()) {
            $quantityTotal = 0.0;
        } elseif ($quantityCoverageComplete && count($listingQuantities) === $listings->count()) {
            $quantityTotal = round(array_sum($listingQuantities), 4);
        }

        return [
            'label' => 'HARBORAI LISTING REFERENCE',
            'scope' => 'HarborAI active public listings from verified producers; in-stock only',
            'active_listing_count' => $listings->count(),
            'active_listing_price_count' => $priceStatistics['observation_count'],
            'incompatible_price_listing_count' => $incompatiblePriceCount,
            'minimum_active_listing_price' => $priceStatistics['minimum_price'],
            'maximum_active_listing_price' => $priceStatistics['maximum_price'],
            'average_active_listing_price' => $priceStatistics['average_price'],
            'price_currency' => 'PHP',
            'price_unit' => $targetUnit ? 'PHP/' . $targetUnit : null,
            'active_listing_quantity_total' => $quantityTotal,
            'quantity_unit' => $targetUnit,
            'quantity_status' => $targetUnit === null
                ? 'UNIT_INCOMPATIBLE'
                : ($listings->isEmpty()
                    ? 'NO_ACTIVE_LISTINGS'
                    : ($quantityTotal === null ? 'UNIT_INCOMPATIBLE' : 'AVAILABLE')),
        ];
    }

    /** @param list<string> $labels */
    private function activeDemandsByLabels(
        array $labels,
        CarbonImmutable $analysisDate
    ): Collection {
        if ($labels === []) {
            return collect();
        }

        return DemandRequest::published()
            ->whereIn(DB::raw('LOWER(TRIM(product_name))'), $labels)
            ->whereIn(DB::raw('LOWER(TRIM(status))'), array_map('mb_strtolower', self::ACTIVE_DEMAND_STATUSES))
            ->whereHas('buyer.user', fn ($query) => $query->where('user_type', 'Buyer'))
            ->where(fn ($query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', $analysisDate->toDateString()))
            ->orderBy('request_id')
            ->get();
    }

    private function demandContext(
        Collection $demands,
        ?CommodityReference $commodity,
        ?string $targetUnit,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): array {
        $total = null;
        $quantityStatus = $targetUnit === null ? 'UNIT_INCOMPATIBLE' : 'NO_ACTIVE_REQUESTS';

        if ($targetUnit && $demands->isEmpty()) {
            $total = 0.0;
        } elseif ($targetUnit && $demands->isNotEmpty()) {
            $converted = [];
            $complete = true;
            foreach ($demands as $demand) {
                $quantity = $demand->quantity_needed;
                $sourceUnit = $this->normalizeUnit($demand->unit);
                if (!is_numeric($quantity) || (float) $quantity < 0 || !$sourceUnit) {
                    $complete = false;
                    break;
                }

                $value = $this->quantityIntoUnit(
                    (float) $quantity,
                    $sourceUnit,
                    $targetUnit,
                    $commodity,
                    $analysisDate,
                    $conversions
                );
                if ($value === null) {
                    $complete = false;
                    break;
                }
                $converted[] = $value;
            }

            if ($complete && count($converted) === $demands->count()) {
                $total = round(array_sum($converted), 4);
                $quantityStatus = 'AVAILABLE';
            } else {
                $quantityStatus = 'UNIT_INCOMPATIBLE';
            }
        }

        return [
            'scope' => 'HarborAI active buyer requests; locations are not filtered or inferred',
            'status' => $demands->isEmpty() ? 'NONE' : 'AVAILABLE',
            'active_demand_request_count' => $demands->count(),
            'requested_quantity_total' => $total,
            'quantity_unit' => $targetUnit,
            'quantity_status' => $quantityStatus,
        ];
    }

    /**
     * @param list<array{commodity: CommodityReference, method: string, confidence: float}> $matches
     * @return array<string, mixed>
     */
    private function seasonalityContext(array $matches, CarbonImmutable $analysisDate, ?string $productName = null): array
    {
        $base = [
            'analysis_month' => $analysisDate->month,
            'analysis_month_name' => $analysisDate->format('F'),
            'status' => 'INSUFFICIENT_DATA',
            'interpretation' => 'No verified seasonality reference is currently available for this product. HarborAI will not infer a season without supporting reference data.',
            'geographic_limitation' => 'Any future seasonality reference may be broader than the producer’s location and should not be treated as locally specific.',
            'validated_reference_count' => 0,
            'references' => [],
            'candidates' => [],
        ];

        if ($matches === []) {
            // Exact dataset labels can carry seasonal evidence without a mapped price series.
            // This does not resolve a price commodity or choose among varieties.
            $dataset = app(SeasonalityReferenceService::class);
            if ($productName !== null && in_array(SeasonalityReferenceService::normalize($productName),
                array_map([SeasonalityReferenceService::class, 'normalize'], $dataset->commodities()), true)) {
                return $this->seasonalityContextFromDataset([], $analysisDate, $productName) ?? $base;
            }
            return $base;
        }

        $commodityIds = array_map(
            fn (array $match): int => (int) $match['commodity']->getKey(),
            $matches
        );
        $records = SeasonalityReference::query()
            ->with(['dataSource', 'commodity'])
            ->whereIn('commodity_reference_id', $commodityIds)
            ->where('verification_status', 'verified')
            ->whereHas('dataSource', fn ($query) => $query
                ->where('source_type', AiDataSource::TYPE_OFFICIAL_REFERENCE)
                ->where('is_official', true)
                ->where('is_active', true))
            ->orderBy('commodity_reference_id')
            ->orderBy('geographic_scope')
            ->orderBy('ai_data_source_id')
            ->orderBy('month')
            ->orderBy('id')
            ->get();

        // The database seasonality table is the primary source. When it holds no
        // usable verified row for the matched commodity, fall back to the owner-supplied
        // derived-seasonality CSV so the decision support is still grounded in real
        // records instead of inventing a season or falling silent.
        if ($records->isEmpty()) {
            $fromDataset = $this->seasonalityContextFromDataset($matches, $analysisDate);
            if ($fromDataset !== null) {
                return $fromDataset;
            }
        }

        $groups = $records->groupBy(fn (SeasonalityReference $reference): string => implode('|', [
            (string) $reference->commodity_reference_id,
            (string) $reference->ai_data_source_id,
            mb_strtolower(trim($reference->geographic_scope)),
        ]));
        $seasonalityReferences = $groups->map(function ($group) use ($analysisDate): array {
            /** @var SeasonalityReference $first */
            $first = $group->first();
            $source = $first->dataSource;
            $rows = $group->map(fn (SeasonalityReference $reference): array => [
                'month' => $reference->month,
                'period_label' => $reference->period_label,
                'season_status' => $reference->season_status,
                'start_month' => $reference->start_month,
                'end_month' => $reference->end_month,
                'peak_period' => $reference->peak_period,
                'reference_date' => $reference->reference_date?->toDateString(),
                'notes' => $reference->notes,
            ])->values()->all();
            $status = $this->seasonalityStatus(
                $first->commodity?->category,
                $group->all(),
                $analysisDate->month
            );
            $periods = array_values(array_unique(array_filter(array_map(
                fn (SeasonalityReference $reference): ?string => $this->seasonalityPeriodLabel($reference),
                $group->all()
            ))));
            $sourceName = $source?->source_name;
            $sourceReference = $first->source_reference;
            $sourceLabel = $sourceReference ?: $sourceName ?: 'Official reference source';

            return [
                'commodity_label' => $first->commodity?->canonical_name,
                'commodity_category' => $first->commodity?->category,
                'status' => $status,
                'periods' => $periods,
                'rows' => $rows,
                'geographic_scope' => $first->geographic_scope,
                'source_name' => $sourceName,
                'organization' => $source?->organization,
                'source_url' => $first->source_url ?: $source?->source_url,
                'source_reference' => $sourceReference,
                'reference_date' => $first->reference_date?->toDateString(),
                'source_label' => $sourceLabel,
                'interpretation' => $this->seasonalityInterpretation($status, $analysisDate->format('F'), $first->commodity?->category),
            ];
        })->values();

        $candidates = count($matches) > 1
            ? collect($matches)->map(function (array $match) use ($seasonalityReferences): array {
                $commodity = $match['commodity'];
                return [
                    'commodity_label' => $commodity->canonical_name,
                    'references' => $seasonalityReferences
                        ->where('commodity_label', $commodity->canonical_name)
                        ->values()
                        ->all(),
                ];
            })->values()->all()
            : [];

        $referenceCount = $records->count();
        if ($referenceCount === 0) {
            $base['candidates'] = $candidates;
            if (count($matches) > 1) {
                $base['interpretation'] = 'Multiple commodity varieties matched. No verified seasonality reference is available for any matched variety, so HarborAI will not select a season.';
            }

            return $base;
        }

        $references = $seasonalityReferences->all();
        $distinctCommodityCount = count($matches);
        $statusValues = array_values(array_unique(array_column($references, 'status')));
        $status = $distinctCommodityCount > 1
            ? 'AMBIGUOUS'
            : (count($references) > 1 ? 'MULTIPLE_REFERENCES' : ($statusValues[0] ?? 'INSUFFICIENT_DATA'));

        return [
            ...$base,
            'status' => $status,
            'interpretation' => $distinctCommodityCount > 1
                ? 'Multiple commodity varieties have separate seasonality references. No single variety or seasonal status has been selected.'
                : (count($references) > 1
                    ? 'More than one official seasonality reference applies at different source or geographic scopes. Review each separately; HarborAI does not combine them.'
                    : ($references[0]['interpretation'] ?? $base['interpretation'])),
            'geographic_limitation' => $this->seasonalityGeographicLimitation($references, $base['geographic_limitation']),
            'validated_reference_count' => $referenceCount,
            'references' => $references,
            'candidates' => $candidates,
        ];
    }

    /**
     * Seasonality from the owner-supplied derived-seasonality dataset.
     *
     * This deliberately reuses the same response shape and status vocabulary as the
     * database-backed path so no consumer has to know which source produced the block.
     * It never promotes a derived indicator to a verified local season: the source's own
     * record_classification and local_validation_status are carried through verbatim, and
     * an ambiguous commodity match returns nothing rather than picking a variety.
     *
     * @param  list<array{commodity: CommodityReference}>  $matches
     * @return array<string, mixed>|null null when no unambiguous dataset row applies
     */
    private function seasonalityContextFromDataset(array $matches, CarbonImmutable $analysisDate, ?string $exactDatasetName = null): ?array
    {
        $month = (int) $analysisDate->month;
        $monthName = $analysisDate->format('F');
        $service = app(SeasonalityReferenceService::class);

        $limitations = [
            'geographic_limitation' => 'Seasonality is derived from published regional and national reference '
                .'material, not measured Aparri production. It may not reflect exact farm or local conditions.',
            'analysis_month' => $month,
            'analysis_month_name' => $monthName,
        ];

        if (!$service->coversMonth($month)) {
            return [
                ...$limitations,
                'status' => 'INSUFFICIENT_DATA',
                'interpretation' => "The supplied seasonality reference covers July through November, so it does not "
                    ."establish a seasonal status for {$monthName}.",
                'validated_reference_count' => 0,
                'references' => [],
                'candidates' => [],
                'dataset_coverage' => ['supported_months' => SeasonalityReferenceService::SUPPORTED_MONTHS],
            ];
        }

        if (count($matches) > 1) {
            return [
                ...$limitations,
                'status' => 'AMBIGUOUS',
                'interpretation' => 'Several commodity varieties matched. No single variety or seasonal status '
                    .'has been selected, and HarborAI does not choose one for you.',
                'validated_reference_count' => 0,
                'references' => [],
                'candidates' => collect($matches)->map(fn (array $match): array => [
                    'commodity_label' => $match['commodity']->canonical_name,
                    'references' => [],
                ])->values()->all(),
                'dataset_coverage' => ['supported_months' => SeasonalityReferenceService::SUPPORTED_MONTHS],
            ];
        }

        $reference = $exactDatasetName !== null
            ? $service->find($exactDatasetName, $month)
            : $service->findForCanonical($matches[0]['commodity']->canonical_name, $month);
        if ($reference === null) {
            return [
                ...$limitations,
                'status' => 'INSUFFICIENT_DATA',
                'interpretation' => 'No seasonality reference is available for this commodity in '
                    ."{$monthName}. HarborAI will not infer a season without supporting reference data.",
                'validated_reference_count' => 0,
                'references' => [],
                'candidates' => [],
                'dataset_coverage' => ['supported_months' => SeasonalityReferenceService::SUPPORTED_MONTHS],
            ];
        }

        $status = match ($reference->harvestSignal()) {
            'possible_harvest' => $reference->isNamedPlantingWindow()
                ? 'PLANTING PERIOD'
                : 'APPROACHING SEASON',
            default => $reference->isNamedPlantingWindow() ? 'PLANTING PERIOD' : 'INSUFFICIENT_DATA',
        };
        if ($reference->harvestSignal() === 'possible_harvest' && !$reference->isNamedPlantingWindow()) {
            $status = 'APPROACHING SEASON';
        }

        $interpretation = match ($status) {
            'PLANTING PERIOD' => "The supplied reference records a named planting window for "
                ."{$reference->commodity} in {$monthName}; harvest in that month is "
                ."\"{$reference->harvestWindow}\". A planting window does not establish market availability.",
            'APPROACHING SEASON' => "The supplied reference records a possible harvest (inferred) for "
                ."{$reference->commodity} in {$monthName}, outside its named planting window.",
            default => "The supplied reference records \"{$reference->seasonalActivity}\" for "
                ."{$reference->commodity} in {$monthName}. This is not a confirmed harvest period.",
        };

        return [
            ...$limitations,
            'status' => $status,
            'interpretation' => $interpretation,
            'validated_reference_count' => 0,
            'references' => [[
                'commodity_label' => $reference->commodity,
                'commodity_category' => $reference->category,
                'status' => $status,
                'periods' => [],
                'geographic_scope' => 'Aparri-referenced, derived from Cagayan Valley and national sources',
                'source_name' => $reference->sourceIds,
                'source_url' => $reference->primarySourceUrl,
                'source_reference' => $reference->recordId,
                'rows' => [[
                    'month' => $reference->monthNumber,
                    'period_label' => $reference->monthName,
                    'season_status' => $status,
                    'reference_date' => null,
                    'notes' => $reference->cropBasisNote,
                ]],
                'source_label' => $reference->classification,
                'interpretation' => $interpretation,
                'derived_indicator' => true,
                'signal' => [
                    'named_planting_window' => $reference->plantingWindow,
                    'possible_harvest_window' => $reference->harvestWindow,
                    'seasonal_activity' => $reference->seasonalActivity,
                    'harvest_signal' => $reference->harvestSignal(),
                ],
                'classification' => $reference->classification,
                'local_validation_status' => $reference->localValidationStatus,
                'mao_validated' => $reference->isMaoValidated(),
                'verified' => $reference->isVerified(),
                'interpretation_safeguard' => $reference->interpretationSafeguard,
                'rainfall_context' => $reference->rainfallContext,
            ]],
            'candidates' => [],
            'dataset_coverage' => ['supported_months' => SeasonalityReferenceService::SUPPORTED_MONTHS],
        ];
    }

    /**
     * @param list<SeasonalityReference> $references
     */
    private function seasonalityStatus(?string $category, array $references, int $month): string
    {
        $isFishery = mb_strtolower((string) $category) === 'fish';
        $applies = fn (SeasonalityReference $reference): bool => $this->seasonalityReferenceIncludesMonth($reference, $month);
        $hasYearRound = collect($references)->contains(
            fn (SeasonalityReference $reference): bool => $reference->season_status === 'year_round'
        );
        $hasOffSeason = collect($references)->contains(
            fn (SeasonalityReference $reference): bool => $reference->season_status === 'off_season'
        );
        $currentOffSeason = collect($references)->contains(
            fn (SeasonalityReference $reference): bool => $reference->season_status === 'off_season' && $applies($reference)
        );
        $currentOtherPeriod = collect($references)->contains(
            fn (SeasonalityReference $reference): bool => $reference->season_status !== 'off_season'
                && $reference->season_status !== 'year_round'
                && $applies($reference)
        );

        if (($hasYearRound && $hasOffSeason) || ($currentOffSeason && $currentOtherPeriod)) {
            return 'CONFLICTING_REFERENCES';
        }
        if ($hasYearRound) {
            return 'YEAR-ROUND';
        }

        if ($isFishery) {
            if ($currentOffSeason) {
                return 'OFF-SEASON';
            }
            return collect($references)->contains($applies) ? 'DOCUMENTED PERIOD' : 'INSUFFICIENT_DATA';
        }

        if (collect($references)->contains(fn (SeasonalityReference $reference): bool => $reference->season_status === 'peak' && $applies($reference))) {
            return 'PEAK SEASON';
        }

        $currentHarvest = collect($references)->first(fn (SeasonalityReference $reference): bool => $reference->season_status === 'harvest' && $applies($reference));
        if ($currentHarvest) {
            if ($currentHarvest->start_month !== null && $currentHarvest->end_month !== null
                && $month === (int) $currentHarvest->end_month) {
                return 'ENDING SEASON';
            }
            return 'IN SEASON';
        }

        if (collect($references)->contains(fn (SeasonalityReference $reference): bool => $reference->season_status === 'off_season' && $applies($reference))) {
            return 'OFF-SEASON';
        }

        foreach (['planting' => 'PLANTING PERIOD', 'growing' => 'GROWING PERIOD'] as $seasonStatus => $label) {
            if (collect($references)->contains(fn (SeasonalityReference $reference): bool => $reference->season_status === $seasonStatus && $applies($reference))) {
                return $label;
            }
        }

        if (collect($references)->contains(fn (SeasonalityReference $reference): bool => $reference->season_status === 'other' && $applies($reference))) {
            return 'DOCUMENTED PERIOD';
        }

        $upcomingHarvest = collect($references)->first(function (SeasonalityReference $reference) use ($month): bool {
            if ($reference->season_status !== 'harvest' || $reference->start_month === null || $reference->end_month === null) {
                return false;
            }
            return $this->nextMonth($month) === (int) $reference->start_month;
        });
        if ($upcomingHarvest) {
            return 'APPROACHING SEASON';
        }

        $hasExplicitHarvestRange = collect($references)->contains(
            fn (SeasonalityReference $reference): bool => $reference->season_status === 'harvest'
                && $reference->start_month !== null
                && $reference->end_month !== null
        );

        return $hasExplicitHarvestRange ? 'OFF-SEASON' : 'INSUFFICIENT_DATA';
    }

    private function seasonalityReferenceIncludesMonth(SeasonalityReference $reference, int $month): bool
    {
        if ($reference->month !== null) {
            return (int) $reference->month === $month;
        }
        if ($reference->start_month === null || $reference->end_month === null) {
            return false;
        }

        $start = (int) $reference->start_month;
        $end = (int) $reference->end_month;
        return $start <= $end
            ? $month >= $start && $month <= $end
            : $month >= $start || $month <= $end;
    }

    private function seasonalityPeriodLabel(SeasonalityReference $reference): ?string
    {
        if ($reference->period_label) {
            return $reference->period_label;
        }
        if ($reference->start_month !== null && $reference->end_month !== null) {
            return $this->monthName((int) $reference->start_month) . ' – ' . $this->monthName((int) $reference->end_month);
        }
        if ($reference->month !== null) {
            return $this->monthName((int) $reference->month);
        }
        return null;
    }

    private function monthName(int $month): string
    {
        return CarbonImmutable::create(2020, $month, 1)->format('F');
    }

    private function nextMonth(int $month): int
    {
        return $month === 12 ? 1 : $month + 1;
    }

    private function seasonalityInterpretation(string $status, string $monthName, ?string $category): string
    {
        if (mb_strtolower((string) $category) === 'fish') {
            return match ($status) {
                'YEAR-ROUND' => 'The official fishery reference explicitly describes this product as year-round.',
                'CONFLICTING_REFERENCES' => 'Verified fishery rows conflict for this source and geographic scope. Review the source records; HarborAI does not select a status.',
                'OFF-SEASON' => "The current analysis month ({$monthName}) falls within a documented fishery off-season period.",
                'DOCUMENTED PERIOD' => "The current analysis month ({$monthName}) falls within a documented fishery period. This is not interpreted as crop planting or harvest.",
                default => 'The official fishery reference does not establish a status for the current analysis month.',
            };
        }

        return match ($status) {
            'YEAR-ROUND' => 'The official reference explicitly identifies year-round availability.',
            'CONFLICTING_REFERENCES' => 'Verified seasonality rows conflict for this source and geographic scope. Review the source records; HarborAI does not select a seasonal status.',
            'PEAK SEASON' => "The official reference identifies {$monthName} as a peak period.",
            'IN SEASON' => "The current analysis month ({$monthName}) is within the documented harvest period.",
            'APPROACHING SEASON' => 'The documented harvest period begins next month.',
            'ENDING SEASON' => "The current analysis month ({$monthName}) is the final month of the documented harvest period.",
            'OFF-SEASON' => "The current analysis month ({$monthName}) is outside the documented harvest period or within a documented off-season period.",
            'PLANTING PERIOD' => "The official reference identifies {$monthName} as a planting period; this does not establish current product availability.",
            'GROWING PERIOD' => "The official reference identifies {$monthName} as a growing period; this does not establish current product availability.",
            'DOCUMENTED PERIOD' => "The current analysis month ({$monthName}) is within an official documented period; no further seasonal meaning is inferred.",
            default => 'The official reference does not establish a status for the current analysis month.',
        };
    }

    /** @param list<array<string, mixed>> $references */
    private function seasonalityGeographicLimitation(array $references, string $default): string
    {
        $scopes = array_values(array_unique(array_filter(array_column($references, 'geographic_scope'))));
        if ($scopes === []) {
            return $default;
        }

        return 'Seasonality is reported for ' . implode('; ', $scopes)
            . ' and may not reflect exact farm, municipal, or local conditions.';
    }

    private function grossListingValue(
        ?float $quantity,
        ?float $producerPrice,
        ?string $inputUnit,
        ?string $normalizedUnit,
        string $currency
    ): array {
        if ($quantity === null || $producerPrice === null) {
            return ['status' => 'INSUFFICIENT_DATA', 'amount' => null, 'currency' => $currency, 'is_profit' => false];
        }
        if ($normalizedUnit === null || $inputUnit === null) {
            return ['status' => 'UNIT_INCOMPATIBLE', 'amount' => null, 'currency' => $currency, 'is_profit' => false];
        }

        return [
            'status' => 'CALCULATED',
            'amount' => round($quantity * $producerPrice, 2),
            'currency' => $currency,
            'is_profit' => false,
        ];
    }

    private function dataSufficiency(
        ?CommodityReference $commodity,
        int $referenceCount,
        string $comparisonStatus,
        int $listingCount,
        int $demandCount
    ): array {
        if (!$commodity) {
            return ['status' => 'INSUFFICIENT_DATA', 'basis' => 'COMMODITY_NOT_RESOLVED'];
        }
        if ($referenceCount > 0) {
            return $comparisonStatus === 'COMPARABLE'
                ? ['status' => 'HIGH', 'basis' => 'PSA_REFERENCE_AND_COMPATIBLE_PRODUCER_PRICE_AVAILABLE']
                : ['status' => 'MODERATE', 'basis' => 'PSA_REFERENCE_AVAILABLE_BUT_PRICE_COMPARISON_INCOMPLETE'];
        }
        if ($listingCount > 0 || $demandCount > 0) {
            return ['status' => 'LIMITED', 'basis' => 'HARBORAI_PLATFORM_CONTEXT_ONLY_NO_PSA_REFERENCE'];
        }

        return ['status' => 'INSUFFICIENT_DATA', 'basis' => 'NO_MATCHED_REFERENCE_OR_PLATFORM_CONTEXT'];
    }

    /**
     * @param list<array{psa_reference: array<string, mixed>}> $referenceCandidates
     */
    private function analysisDataSufficiency(
        array $referenceCandidates,
        ?CommodityReference $commodity,
        string $comparisonStatus,
        int $listingCount,
        int $demandCount
    ): array {
        if (count($referenceCandidates) > 1) {
            $verifiedObservations = array_sum(array_map(
                fn (array $candidate) => (int) ($candidate['psa_reference']['observation_count'] ?? 0),
                $referenceCandidates
            ));

            if ($verifiedObservations > 0) {
                return [
                    'status' => 'MODERATE',
                    'basis' => 'MULTIPLE_PSA_SERIES_REQUIRE_VARIETY_SELECTION',
                ];
            }
            if ($listingCount > 0 || $demandCount > 0) {
                return ['status' => 'LIMITED', 'basis' => 'HARBORAI_PLATFORM_CONTEXT_WITHOUT_VERIFIED_PSA_OBSERVATIONS'];
            }

            return ['status' => 'INSUFFICIENT_DATA', 'basis' => 'MATCHED_PSA_SERIES_WITHOUT_VALID_OBSERVATIONS'];
        }

        if (count($referenceCandidates) === 1) {
            $reference = $referenceCandidates[0]['psa_reference'];

            return $this->dataSufficiency(
                $commodity,
                (int) ($reference['observation_count'] ?? 0),
                $comparisonStatus,
                $listingCount,
                $demandCount
            );
        }

        if ($listingCount > 0 || $demandCount > 0) {
            return ['status' => 'LIMITED', 'basis' => 'HARBORAI_PLATFORM_CONTEXT_ONLY_NO_PSA_REFERENCE'];
        }

        return ['status' => 'INSUFFICIENT_DATA', 'basis' => 'NO_MATCHED_REFERENCE_OR_PLATFORM_CONTEXT'];
    }

    /** @param array<int, float> $values */
    private function statistics(array $values): array
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        if ($count === 0) {
            return [
                'observation_count' => 0,
                'minimum_price' => null,
                'maximum_price' => null,
                'average_price' => null,
                'median_price' => null,
            ];
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;

        return [
            'observation_count' => $count,
            'minimum_price' => round($values[0], 4),
            'maximum_price' => round($values[$count - 1], 4),
            'average_price' => round(array_sum($values) / $count, 4),
            'median_price' => round($median, 4),
        ];
    }

    private function priceIntoUnit(
        float $price,
        string $sourceUnit,
        string $targetUnit,
        ?CommodityReference $commodity,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): ?float {
        if ($sourceUnit === $targetUnit) {
            return $price;
        }

        // A price per source unit becomes a price per target unit by multiplying
        // by the number of source units in one target unit.
        $factor = $this->conversionFactor($targetUnit, $sourceUnit, $commodity, $analysisDate, $conversions);

        return $factor === null ? null : $price * $factor;
    }

    private function quantityIntoUnit(
        float $quantity,
        string $sourceUnit,
        string $targetUnit,
        ?CommodityReference $commodity,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): ?float {
        if ($sourceUnit === $targetUnit) {
            return $quantity;
        }

        $factor = $this->conversionFactor($sourceUnit, $targetUnit, $commodity, $analysisDate, $conversions);

        return $factor === null ? null : $quantity * $factor;
    }

    private function conversionFactor(
        string $fromUnit,
        string $toUnit,
        ?CommodityReference $commodity,
        CarbonImmutable $analysisDate,
        EloquentCollection $conversions
    ): ?float {
        $matches = $conversions->filter(function (UnitConversionReference $reference) use ($fromUnit, $toUnit, $commodity, $analysisDate): bool {
            $referenceFrom = $this->normalizeUnit($reference->from_unit);
            $referenceTo = $this->normalizeUnit($reference->to_unit);
            if ($referenceFrom !== $fromUnit || $referenceTo !== $toUnit) {
                return false;
            }
            // Generic conversions are valid only between supported mass units.
            // Sack and piece weights require an applicable commodity-specific basis.
            if ($reference->commodity_reference_id === null
                && (!in_array($referenceFrom, ['kg', 'g'], true)
                    || !in_array($referenceTo, ['kg', 'g'], true))) {
                return false;
            }
            if ($reference->commodity_reference_id !== null
                && (!$commodity || (int) $reference->commodity_reference_id !== (int) $commodity->getKey())) {
                return false;
            }

            return $reference->effective_date === null
                || $reference->effective_date->lessThanOrEqualTo($analysisDate);
        });

        $match = $matches->sort(function (UnitConversionReference $left, UnitConversionReference $right): int {
            $leftSpecificity = $left->commodity_reference_id === null ? 0 : 1;
            $rightSpecificity = $right->commodity_reference_id === null ? 0 : 1;
            if ($leftSpecificity !== $rightSpecificity) {
                return $rightSpecificity <=> $leftSpecificity;
            }

            $leftDate = $left->effective_date?->getTimestamp() ?? 0;
            $rightDate = $right->effective_date?->getTimestamp() ?? 0;
            return ($rightDate <=> $leftDate) ?: ($right->getKey() <=> $left->getKey());
        })->first();

        if (!$match || !is_numeric($match->conversion_factor) || (float) $match->conversion_factor <= 0) {
            return null;
        }

        return (float) $match->conversion_factor;
    }

    /** @return array<int, string> */
    private function commodityLabels(CommodityReference $commodity): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($name) => mb_strtolower(trim((string) $name)),
            [$commodity->canonical_name, ...($commodity->aliases ?? [])]
        ))));
    }

    private function normalizeReferencePriceUnit(string $unit): ?string
    {
        return match (mb_strtolower(trim($unit))) {
            'kg', 'kilo', 'kilogram', 'kilograms', 'peso per kilogram', 'pesos per kilogram', 'php/kg', 'php per kg' => 'kg',
            'g', 'gram', 'grams', 'peso per gram', 'pesos per gram', 'php/g', 'php per g' => 'g',
            default => $this->normalizeUnit($unit),
        };
    }

    private function hasValidMonthlyPeriod(MarketPriceReference $reference): bool
    {
        if (!$reference->observation_date || !$reference->observation_period) {
            return false;
        }

        if (!preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December) (\d{4})$/i', trim($reference->observation_period), $matches)) {
            return false;
        }

        $periodMonths = [
            'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
            'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
            'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
        ];
        $periodMonth = $periodMonths[mb_strtolower($matches[1])] ?? null;
        return $reference->observation_date->format('Y-m') === $matches[2] . '-' . $periodMonth
            && $reference->observation_date->format('d') === '01';
    }

    private function normalizeUnit(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }

        return match (mb_strtolower(trim($unit))) {
            'kg', 'kilo', 'kilogram', 'kilograms' => 'kg',
            'g', 'gram', 'grams' => 'g',
            'sack', 'sacks' => 'sack',
            'piece', 'pieces', 'pc', 'pcs' => 'piece',
            default => null,
        };
    }

    private function nonNegativeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        return is_finite($number) && $number >= 0 ? $number : null;
    }

    private function optionalString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
