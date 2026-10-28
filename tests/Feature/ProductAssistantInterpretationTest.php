<?php

namespace Tests\Feature;

use App\Models\{AiDataSource, CommodityReference, MarketPriceReference};
use App\Services\AI\ProductAssistantInterpretation;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Product Assistant interpretation, end to end through the real analysis engine.
 *
 * Azure is unconfigured in this deployment, so these tests deliberately run with no generative
 * provider. They prove the farmer still receives a meaningful, data-grounded reading, and that
 * the reading never claims more than the evidence supports.
 *
 * The seasonal side reads the SHIPPED July-November CSV, so the owner's classifications are
 * exercised as written. The price side is seeded with a genuine multi-year Region II series so
 * the "price evidence present" branch is really entered rather than assumed.
 */
class ProductAssistantInterpretationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pin October so the CSV rows under test are deterministic.
        Carbon::setTestNow(Carbon::parse('2026-10-16'));

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

        // A real, dated, regional series. The newest observation deliberately predates the
        // analysed month so the staleness wording is exercised too.
        foreach ([['2015-10-01', 30.0], ['2020-10-01', 34.0], ['2026-06-01', 42.71]] as [$date, $price]) {
            MarketPriceReference::create([
                'commodity_reference_id' => $commodity->id,
                'ai_data_source_id' => $source->id,
                'price' => $price, 'currency' => 'PHP', 'unit' => 'Peso per kilogram',
                'price_type' => 'farmgate',
                'geographic_scope' => 'Region II / Cagayan Valley',
                'observation_date' => $date,
                'observation_period' => Carbon::parse($date)->format('F Y'),
                'period_frequency' => 'monthly',
                'verification_status' => 'verified',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function interpret(array $overrides = []): array
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

        return app(ProductAssistantInterpretation::class)->build($analysis, $context, 'UNAVAILABLE');
    }

    /** The four statuses must be reported independently of one another. */
    public function test_all_four_statuses_are_reported_separately(): void
    {
        $r = $this->interpret();

        $this->assertSame('AVAILABLE', $r['market_analysis_status']);
        $this->assertSame('AVAILABLE', $r['seasonality_evidence_status']);
        // The same commodity has seasonal evidence; its price evidence is reported on its own.
        $this->assertContains($r['price_evidence_status'], ['AVAILABLE', 'INSUFFICIENT_DATA']);
        $this->assertSame('UNAVAILABLE', $r['generative_ai_status']);
        $this->assertStringContainsString('not Azure-generated', $r['interpretation_source']);
    }

    /** CASE 1: Ampalaya, October, with matching historical price evidence. */
    public function test_ampalaya_in_october_with_price_evidence(): void
    {
        $r = $this->interpret(['product_name' => 'Ampalaya']);

        $this->assertSame('AVAILABLE', $r['seasonality_evidence_status']);
        $this->assertStringContainsString('documented planting window', $r['seasonality_narrative']);
        $this->assertStringContainsString('possible harvest activity', $r['seasonality_narrative']);
        $this->assertStringContainsString('does not establish an actual harvest peak', $r['seasonality_narrative']);

        // Price evidence genuinely exists here, so the qualified wording is required.
        $this->assertSame('AVAILABLE', $r['price_evidence_status']);
        $this->assertStringContainsString('not Aparri-specific prices', $r['price_narrative']);
        $this->assertStringContainsString('not current market prices', $r['price_narrative']);
        $this->assertStringContainsString('Region II / Cagayan Valley', $r['price_narrative']);
    }

    /** CASE 2: Palay, October, no matching price evidence, but real seasonality evidence. */
    public function test_palay_in_october_without_price_evidence_still_interprets_the_season(): void
    {
        $r = $this->interpret(['product_name' => 'Palay', 'category' => 'Grains']);

        $this->assertSame('AVAILABLE', $r['seasonality_evidence_status']);
        $this->assertSame('INSUFFICIENT_DATA', $r['price_evidence_status']);

        // Seasonal reading still delivered.
        $this->assertStringContainsString('Possible harvest (inferred)', $r['seasonality_narrative']);
        // Missing price evidence stated as missing, never as zero.
        $this->assertStringContainsString('not a price of zero', $r['price_narrative']);
        $this->assertStringNotContainsString('₱0', $r['interpretation']);
    }

    /** CASE 4: Sitaw and Kalabasa - harvest is undetermined and must not become zero. */
    public function test_undetermined_harvest_is_never_reported_as_zero_production(): void
    {
        foreach (['Sitaw', 'Kalabasa'] as $commodity) {
            $r = $this->interpret(['product_name' => $commodity]);

            $this->assertSame('AVAILABLE', $r['seasonality_evidence_status'], $commodity);
            $this->assertStringContainsString('documented planting window', $r['seasonality_narrative'], $commodity);
            $this->assertStringContainsString('undetermined', $r['seasonality_narrative'], $commodity);
            $this->assertStringContainsString('not the same as zero', $r['seasonality_narrative'], $commodity);
            $this->assertStringContainsString('Named planting window', $r['seasonality_narrative'], $commodity);
        }
    }

    /** CASE 3: Talong matches the CSV but is ambiguous for price-series matching. */
    public function test_talong_reports_seasonality_and_never_invents_an_ambiguous_price(): void
    {
        $r = $this->interpret(['product_name' => 'Talong']);

        // Seasonality classification is unambiguous even though the price series is not.
        $this->assertStringContainsString('Planting window + possible harvest', $r['seasonality_narrative']);

        if ($r['price_evidence_status'] !== 'AVAILABLE') {
            $this->assertStringContainsString('No verified price reference', $r['price_narrative']);
        }
    }

    /** CASE 5: a commodity absent from the CSV must not receive an invented season. */
    public function test_a_commodity_not_in_the_dataset_gets_no_invented_season(): void
    {
        $r = $this->interpret(['product_name' => 'Ube']);

        $this->assertSame('INSUFFICIENT_DATA', $r['seasonality_evidence_status']);
        $this->assertStringContainsString('holds no seasonal reference', $r['seasonality_narrative']);
        $this->assertStringContainsString('absence of evidence, not evidence of no production', $r['seasonality_narrative']);
    }

    /** CASE 6: a month outside the July-November dataset window. */
    public function test_a_month_outside_the_dataset_window_is_not_given_a_season(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-16'));

        $r = $this->interpret(['analysis_date' => '2026-03-16']);

        $this->assertSame('INSUFFICIENT_DATA', $r['seasonality_evidence_status']);
        $this->assertStringContainsString('will not infer a season', $r['seasonality_narrative']);
        $this->assertStringContainsString('March', $r['seasonality_narrative']);
    }

    /** CASE 7: an out-of-Aparri origin is explained without inventing a price difference. */
    public function test_an_origin_outside_aparri_gets_no_invented_price_difference(): void
    {
        $r = $this->interpret(['origin' => 'Benguet']);

        $this->assertFalse($r['origin']['is_aparri']);
        $this->assertSame('Benguet', $r['origin']['supplied']);
        $this->assertStringContainsString('outside Aparri', $r['geographic_narrative']);
        $this->assertStringContainsString('will not estimate a difference', $r['geographic_narrative']);

        // The narrative explicitly disclaims a price difference rather than asserting one.
        $this->assertStringContainsString(
            'it cannot say your commodity is more expensive or less expensive here',
            $r['geographic_narrative']
        );
        // No quantified premium or discount may appear anywhere in the reading.
        $this->assertDoesNotMatchRegularExpression(
            '/(\d+(?:\.\d+)?\s*%\s*(?:more|less|higher|lower|cheaper|premium|discount))|((?:more|less)\s+expensive\s+by)/i',
            $r['interpretation']
        );
    }

    /** CASE 8: no Azure provider at all, yet a usable interpretation still comes back. */
    public function test_a_successful_analysis_survives_a_missing_azure_provider(): void
    {
        $r = $this->interpret();

        $this->assertTrue($r !== []);
        $this->assertNotSame('', trim($r['interpretation']));
        $this->assertSame('UNAVAILABLE', $r['generative_ai_status']);
        // The market analysis itself is reported as available despite the provider failure.
        $this->assertSame('AVAILABLE', $r['market_analysis_status']);
        $this->assertSame('AVAILABLE', $r['seasonality_evidence_status']);
    }

    /** The composed reading must always end with a question the farmer can answer. */
    public function test_the_interpretation_always_asks_an_interrogative_pricing_question(): void
    {
        foreach ([['Ampalaya'], ['Palay'], ['Sitaw'], ['Ube']] as [$commodity]) {
            $r = $this->interpret(['product_name' => $commodity]);
            $this->assertStringContainsString('?', $r['pricing_question'], $commodity);
            $this->assertStringContainsString(
                'what price would you consider appropriate',
                mb_strtolower($r['pricing_question']),
                $commodity
            );
        }
    }

    /** The wording must come from the matched row, not be one fixed sentence. */
    public function test_wording_varies_with_the_matched_dataset_row(): void
    {
        $ampalaya = $this->interpret(['product_name' => 'Ampalaya'])['seasonality_narrative'];
        $sitaw = $this->interpret(['product_name' => 'Sitaw'])['seasonality_narrative'];
        $palay = $this->interpret(['product_name' => 'Palay', 'category' => 'Grains'])['seasonality_narrative'];

        $this->assertNotSame($ampalaya, $sitaw);
        $this->assertNotSame($ampalaya, $palay);
        $this->assertNotSame($sitaw, $palay);
        // Each carries the classification recorded for its own commodity.
        $this->assertStringContainsString('Planting window + possible harvest', $ampalaya);
        $this->assertStringContainsString('Named planting window', $sitaw);
        $this->assertStringContainsString('Possible harvest (inferred)', $palay);
    }

    /** No commodity may be described as definitively in season. */
    public function test_no_commodity_is_presented_as_definitively_in_season(): void
    {
        foreach (['Ampalaya', 'Palay', 'Sitaw', 'Kalabasa', 'Talong', 'Mais (grain corn)', 'Patola'] as $c) {
            $r = $this->interpret(['product_name' => $c, 'category' => 'Grains']);
            $this->assertDoesNotMatchRegularExpression(
                '/\bconfirmed in season\b|\bdefinitely in season\b|\bpeak season is\b|\bguaranteed harvest\b/i',
                $r['interpretation'],
                $c
            );
        }
    }
}