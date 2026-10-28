<?php

namespace Tests\Feature;

use App\Models\{AiDataSource, CommodityReference, MarketPriceReference};
use App\Services\AI\ProductAssistantSummary;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The compressed farmer-facing Product Assistant reading.
 *
 * The previous output repeated the same limitations in four places. These tests pin the new
 * behaviour: one short paragraph, one closing question, and no commodity-specific claim that the
 * underlying evidence does not support.
 */
class ProductAssistantSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-16'));

        $source = AiDataSource::create([
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'source_name' => AiDataSource::SOURCE_PSA_MAJOR_CROPS,
            'geographic_scope' => 'Region II / Cagayan Valley',
            'is_official' => true,
            'is_active' => true,
        ]);

        // A comparable Ampalaya series, so the "comparable" branch is genuinely exercised.
        $ampalaya = CommodityReference::create([
            'canonical_name' => 'Ampalaya fruit [Bitter gourd]',
            'normalized_name' => 'ampalaya-fruit-bitter-gourd',
            'category' => 'Vegetables',
        ]);
        foreach ([['2015-10-01', 30.0], ['2020-10-01', 34.0], ['2026-06-01', 42.71]] as $row) {
            MarketPriceReference::create([
                'commodity_reference_id' => $ampalaya->id,
                'ai_data_source_id' => $source->id,
                'price' => $row[1],
                'currency' => 'PHP',
                'unit' => 'Peso per kilogram',
                'price_type' => 'farmgate',
                'geographic_scope' => 'Region II / Cagayan Valley',
                'observation_date' => $row[0],
                'observation_period' => Carbon::parse($row[0])->format('F Y'),
                'period_frequency' => 'monthly',
                'verification_status' => 'verified',
            ]);
        }

        // Several Banana varieties, each holding multiple positive observations, so the
        // ambiguous branch has real data to count.
        $varieties = ['Banana Lakatan', 'Banana Latundan', 'Banana Saba'];
        foreach ($varieties as $index => $name) {
            $variety = CommodityReference::create([
                'canonical_name' => $name,
                'normalized_name' => 'banana-' . strtolower(str_replace(' ', '-', $name)),
                'category' => 'Fruits',
            ]);
            foreach ([['2024-10-01', 40.0], ['2025-10-01', 45.0 + $index]] as $obs) {
                MarketPriceReference::create([
                    'commodity_reference_id' => $variety->id,
                    'ai_data_source_id' => $source->id,
                    'price' => $obs[1],
                    'currency' => 'PHP',
                    'unit' => 'Peso per kilogram',
                    'price_type' => 'farmgate',
                    'geographic_scope' => 'Region II / Cagayan Valley',
                    'observation_date' => $obs[0],
                    'observation_period' => Carbon::parse($obs[0])->format('F Y'),
                    'period_frequency' => 'monthly',
                    'verification_status' => 'verified',
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function summary(array $overrides = []): array
    {
        $context = array_merge([
            'product_name' => 'Ampalaya',
            'category' => 'Vegetables',
            'origin' => 'Aparri, Cagayan',
            'unit' => 'kg',
            'quantity' => 500,
            'producer_price' => 60,
            'analysis_date' => '2026-10-16',
        ], $overrides);

        $analysis = app(DeterministicMarketAnalysisService::class)->analyze($context);

        return app(ProductAssistantSummary::class)->build($analysis, $context);
    }

    private function priceFact(array $summary): string
    {
        foreach ($summary['key_facts'] as $fact) {
            if ($fact['label'] === 'Price evidence') {
                return (string) $fact['value'];
            }
        }

        return '';
    }

    private function labels(array $summary): array
    {
        return array_column($summary['key_facts'], 'label');
    }

    public function test_the_reading_is_short_and_readable(): void
    {
        $summary = $this->summary();

        $this->assertGreaterThanOrEqual(40, $summary['word_count']);
        $this->assertLessThanOrEqual(130, $summary['word_count'], 'the default reading must stay short');
        $this->assertSame(
            $summary['word_count'],
            count(preg_split('/\s+/', trim($summary['summary'])) ?: [])
        );
    }

    public function test_it_ends_with_exactly_one_pricing_question(): void
    {
        $cases = [
            ['product_name' => 'Ampalaya'],
            ['product_name' => 'Palay', 'category' => 'Grains'],
            ['product_name' => 'Sitaw'],
            ['product_name' => 'Ube'],
            ['product_name' => 'Banana', 'category' => 'Fruits'],
        ];

        foreach ($cases as $overrides) {
            $summary = $this->summary($overrides);
            $name = (string) $overrides['product_name'];

            $this->assertStringContainsString('?', $summary['summary'], $name);
            $this->assertSame(1, substr_count($summary['summary'], '?'), 'exactly one question: ' . $name);
        }
    }

    public function test_key_facts_are_supplied_without_repeating_the_narrative(): void
    {
        $labels = $this->labels($this->summary());

        $this->assertContains('Season', $labels);
        $this->assertContains('Price evidence', $labels);
        $this->assertContains('Harvest', $labels);
        $this->assertContains('Geography', $labels);
    }

    public function test_ampalaya_reports_a_comparable_but_regional_series(): void
    {
        $summary = $this->summary(['product_name' => 'Ampalaya']);

        $this->assertStringContainsString('Historical Region II / Cagayan Valley', $summary['summary']);
        $this->assertStringContainsString('not current Aparri prices', $summary['summary']);
        $this->assertStringContainsString('not a measured harvest peak in Aparri', $summary['summary']);
    }

    public function test_a_commodity_without_price_evidence_says_so(): void
    {
        $palay = $this->summary(['product_name' => 'Palay', 'category' => 'Grains']);
        $talong = $this->summary(['product_name' => 'Talong']);

        foreach (['Palay' => $palay, 'Talong' => $talong] as $name => $summary) {
            $this->assertStringContainsString('not a price of zero', $summary['summary'], $name);
            $this->assertStringNotContainsString('0.00', $summary['summary'], $name);
        }
    }

    public function test_undetermined_harvest_is_not_reported_as_no_production(): void
    {
        foreach (['Sitaw', 'Kalabasa'] as $name) {
            $summary = $this->summary(['product_name' => $name]);

            $this->assertStringContainsString('undetermined', $summary['summary'], $name);
            $this->assertStringContainsString('rather than as no production', $summary['summary'], $name);

            $harvest = '';
            foreach ($summary['key_facts'] as $fact) {
                if ($fact['label'] === 'Harvest') {
                    $harvest = (string) $fact['value'];
                }
            }
            $this->assertStringContainsString('Undetermined', $harvest, $name);
        }
    }

    public function test_ambiguous_varieties_are_not_reported_as_having_no_observations(): void
    {
        $summary = $this->summary(['product_name' => 'Banana', 'category' => 'Fruits']);
        $text = strtolower($summary['summary']);

        $this->assertStringNotContainsString('no positive price observations', $text);
        $this->assertStringNotContainsString('no verified price reference', $text);
        $this->assertStringContainsString('cannot select one', $summary['summary']);
        $this->assertStringContainsString('mislead you', $summary['summary']);
        $this->assertStringContainsString('Ambiguous', $this->priceFact($summary));

        $this->assertNotEmpty($summary['variety_candidates']);
        foreach ($summary['variety_candidates'] as $variety) {
            $this->assertGreaterThan(0, $variety['comparable_observation_count']);
        }
    }

    /**
     * REGRESSION: the number of matched SERIES must never be printed as though it were the
     * number of OBSERVATIONS.
     *
     * An earlier build reported "6 positive observations exist across them" when the figure 6
     * was in fact the count of Banana varieties. Each variety holds many observations, so that
     * sentence understated the available evidence by two orders of magnitude. Both figures are
     * now derived from the analysis and reported separately.
     */
    public function test_the_series_count_is_never_reported_as_an_observation_count(): void
    {
        $summary = $this->summary(['product_name' => 'Banana', 'category' => 'Fruits']);

        $seriesCount = count($summary['variety_candidates']);
        $this->assertGreaterThan(1, $seriesCount, 'this fixture must be ambiguous to be meaningful');

        $observationTotal = array_sum(array_column($summary['variety_candidates'], 'comparable_observation_count'));
        $this->assertGreaterThan(
            $seriesCount,
            $observationTotal,
            'observations must outnumber series, otherwise this test cannot detect the bug'
        );

        $seriesPhrase = number_format($seriesCount) . ' varieties';
        $observationPhrase = number_format($observationTotal) . ' in total';

        $this->assertStringContainsString($seriesPhrase, $summary['summary'], 'series count must be stated');
        $this->assertStringContainsString(
            $observationPhrase,
            $summary['summary'],
            'the true observation total must be stated separately'
        );

        $this->assertStringNotContainsString(
            number_format($seriesCount) . ' positive observations',
            $summary['summary'],
            'the series count must never be phrased as an observation count'
        );

        $this->assertStringContainsString(number_format($observationTotal), $this->priceFact($summary));
        $this->assertStringContainsString('observations', $this->priceFact($summary));
    }

    public function test_variety_series_are_never_combined_into_one_benchmark(): void
    {
        $summary = $this->summary(['product_name' => 'Banana', 'category' => 'Fruits']);

        $this->assertStringContainsString('combining different varieties into one benchmark', $summary['summary']);
        $this->assertStringContainsString('cannot select one as your comparable reference', $summary['summary']);

        $analysis = app(DeterministicMarketAnalysisService::class)->analyze([
            'product_name' => 'Banana',
            'category' => 'Fruits',
            'origin' => 'Aparri, Cagayan',
            'unit' => 'kg',
            'quantity' => 500,
            'producer_price' => 60,
            'analysis_date' => '2026-10-16',
        ]);

        $this->assertSame('MULTIPLE_REFERENCES', $analysis['producer_price_comparison']['status']);
        $this->assertNull($analysis['producer_price_comparison']['latest_reference_price']);
        $this->assertNull($analysis['producer_price_comparison']['reference_average_price']);
    }

    /** The series count is derived from the analysis, never hardcoded. */
    public function test_the_series_count_is_derived_from_the_analysis_not_hardcoded(): void
    {
        $cases = [
            ['Banana', 'Fruits'],
            ['Ampalaya', 'Vegetables'],
            ['Ube', 'Vegetables'],
        ];
        $words = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight'];

        foreach ($cases as $case) {
            $name = $case[0];
            $summary = $this->summary(['product_name' => $name, 'category' => $case[1]]);
            $seriesCount = count($summary['variety_candidates']);

            if ($seriesCount < 2) {
                continue;
            }

            $this->assertStringContainsString(
                number_format($seriesCount) . ' varieties',
                $summary['summary'],
                $name
            );

            foreach ($words as $word) {
                $this->assertStringNotContainsString(
                    $word . ' varieties',
                    $summary['summary'],
                    $name . ' must not spell out a count'
                );
            }
        }
    }

    public function test_an_unknown_commodity_gets_no_invented_season(): void
    {
        $summary = $this->summary(['product_name' => 'Ube']);

        $this->assertStringContainsString('no seasonal reference', $summary['summary']);
        $this->assertStringContainsString('will not guess a season', $summary['summary']);
    }

    public function test_an_outside_origin_gets_no_invented_difference(): void
    {
        $summary = $this->summary(['origin' => 'Benguet']);

        $this->assertStringContainsString('Benguet', $summary['summary']);
        $this->assertStringContainsString('cannot estimate that difference', $summary['summary']);
        $this->assertStringNotContainsString('more expensive by', $summary['summary']);
        $this->assertStringNotContainsString('less expensive by', $summary['summary']);
    }

    public function test_a_missing_price_still_yields_a_useful_seasonal_reading(): void
    {
        $summary = $this->summary(['product_name' => 'Sitaw']);

        $this->assertStringContainsString('documented planting window', $summary['summary']);
        $this->assertStringContainsString('not a price of zero', $summary['summary']);
        $this->assertStringContainsString('?', $summary['summary']);
    }
}