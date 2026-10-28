<?php

namespace Tests\Feature;

use App\Models\{AiDataSource, CommodityReference, MarketPriceReference};
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Provenance of the market price comparison.
 *
 * A producer who is told "your price is 40% above the reference" must not be able to read that
 * as a current Aparri market quote. The underlying series is REGIONAL and HISTORICAL, so the
 * comparison must carry its geography, its observation span and how far behind the analysed
 * month the newest observation sits.
 */
class PriceComparisonProvenanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedRegionalSeries(): void
    {
        // The service only trusts a source that is flagged official, active and named exactly
        // as the PSA major-crops reference, so the fixture mirrors that lookup.
        $source = AiDataSource::create([
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'source_name' => AiDataSource::SOURCE_PSA_MAJOR_CROPS,
            'geographic_scope' => 'Region II / Cagayan Valley',
            'is_official' => true,
            'is_active' => true,
        ]);
        $commodity = CommodityReference::create([
            'canonical_name' => 'Ampalaya fruit [Bitter gourd]',
            'normalized_name' => 'ampalaya-fruit-bitter-gourd',
            'category' => 'Vegetables',
        ]);

        // A multi-year regional series whose newest observation predates the analysed month.
        foreach ([['2015-10-01', 30.0], ['2020-10-01', 34.0], ['2026-06-01', 42.71]] as [$date, $price]) {
            MarketPriceReference::create([
                'commodity_reference_id' => $commodity->id,
                'ai_data_source_id' => $source->id,
                'price' => $price, 'currency' => 'PHP', 'unit' => 'Peso per kilogram',
                'price_type' => 'farmgate',
                'geographic_scope' => 'Region II / Cagayan Valley',
                'observation_date' => $date,
                // The service requires the full month name and the 1st of the month, and
                // requires the period to agree with the observation date.
                'observation_period' => Carbon::parse($date)->format('F Y'),
                'period_frequency' => 'monthly',
                'verification_status' => 'verified',
            ]);
        }
    }

    private function analyze(): array
    {
        return app(DeterministicMarketAnalysisService::class)->analyze([
            'product_name' => 'Ampalaya',
            'category' => 'Vegetables',
            'origin' => 'Aparri, Cagayan',
            'unit' => 'kg',
            'quantity' => 500,
            'producer_price' => 60,
        ]);
    }

    public function test_the_comparison_carries_the_geography_of_the_evidence(): void
    {
        $this->seedRegionalSeries();
        $basis = $this->analyze()['producer_price_comparison']['reference_basis'] ?? null;

        $this->assertIsArray($basis);
        $this->assertTrue($basis['available']);
        $this->assertSame('Region II / Cagayan Valley', $basis['geography']);
    }

    /** A regional series must never be flagged as local to a producer in Aparri. */
    public function test_the_comparison_is_never_marked_local_to_the_producer(): void
    {
        $this->seedRegionalSeries();
        $basis = $this->analyze()['producer_price_comparison']['reference_basis'];

        $this->assertFalse($basis['is_local_to_producer']);
        $this->assertStringContainsString('not Aparri-specific', $basis['limitation']);
    }

    /** The series must never be presented as a single month-specific price. */
    public function test_a_multi_year_series_is_not_presented_as_a_month_price(): void
    {
        $this->seedRegionalSeries();
        $basis = $this->analyze()['producer_price_comparison']['reference_basis'];

        $this->assertFalse($basis['is_single_month']);
        $this->assertStringContainsString('not a single month-specific price', $basis['limitation']);
        $this->assertSame(3, $basis['observation_count']);
        $this->assertSame('2015-10-01', $basis['first_observation_date']);
        $this->assertSame('2026-06-01', $basis['latest_observation_date']);
    }

    /** A June observation must not be called "latest" for an October analysis. */
    public function test_staleness_relative_to_the_analysed_month_is_reported(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16'));

        try {
            $this->seedRegionalSeries();
            $basis = $this->analyze()['producer_price_comparison']['reference_basis'];

            $this->assertSame(4, $basis['months_behind_analysis']);
            $this->assertFalse($basis['is_current']);
            $this->assertStringContainsString('June 2026', $basis['limitation']);
            $this->assertStringContainsString('October 2026', $basis['limitation']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_an_observation_in_the_analysed_month_is_current(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16'));

        try {
            $this->seedRegionalSeries();
            $basis = $this->analyze()['producer_price_comparison']['reference_basis'];

            $this->assertSame(0, $basis['months_behind_analysis']);
            $this->assertTrue($basis['is_current']);
        } finally {
            Carbon::setTestNow();
        }
    }

    /** Missing evidence stays missing: it is never presented as a zero price. */
    public function test_absent_evidence_is_reported_as_unavailable_not_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16'));

        try {
            $result = app(DeterministicMarketAnalysisService::class)->analyze([
                'product_name' => 'Ube', 'category' => 'Vegetables',
                'origin' => 'Aparri, Cagayan', 'unit' => 'kg',
                'quantity' => 500, 'producer_price' => 60,
            ]);
            $comparison = $result['producer_price_comparison'];

            $this->assertSame('NO_REFERENCE_AVAILABLE', $comparison['status']);
            $this->assertNull($comparison['reference_average_price']);
            $this->assertNull($comparison['latest_reference_price']);
            $this->assertFalse($comparison['reference_basis']['available']);
            $this->assertStringContainsString('No verified reference price is available', $comparison['reference_basis']['limitation']);
        } finally {
            Carbon::setTestNow();
        }
    }
}