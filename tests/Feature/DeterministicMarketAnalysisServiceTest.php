<?php

namespace Tests\Feature;

use App\Models\AiDataSource;
use App\Models\Buyer;
use App\Models\CommodityReference;
use App\Models\DemandRequest;
use App\Models\MarketPriceReference;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\SeasonalityReference;
use App\Models\UnitConversionReference;
use App\Models\User;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use App\Services\Imports\ImportPsaMajorCropsFarmgatePrices;
use Database\Seeders\AiReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeterministicMarketAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeterministicMarketAnalysisService $analysis;
    private CommodityReference $cabbage;
    private AiDataSource $psaSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AiReferenceDataSeeder::class);
        $this->analysis = app(DeterministicMarketAnalysisService::class);
        $this->cabbage = CommodityReference::query()->where('canonical_name', 'Cabbage')->firstOrFail();
        $this->psaSource = AiDataSource::query()
            ->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)
            ->firstOrFail();
    }

    public function test_exact_dataset_names_have_seasonality_without_fabricating_price_aliases(): void
    {
        foreach (['Palay','Talong','Sitaw','Kalabasa'] as $name) {
            $result = $this->analysis->analyze(['product_name'=>$name,'analysis_date'=>'2026-10-01','unit'=>'kg']);
            $this->assertNotEmpty($result['seasonality']['references'], $name);
            $reference = $result['seasonality']['references'][0];
            $this->assertFalse($reference['mao_validated']);
            $this->assertFalse($reference['verified']);
            $this->assertSame(0, $result['psa_reference']['observation_count']);
            if (in_array($name,['Sitaw','Kalabasa'])) $this->assertSame('undetermined',$reference['signal']['harvest_signal']);
        }
        $unknown=$this->analysis->analyze(['product_name'=>'Unknown crop','analysis_date'=>'2026-10-01']);
        $this->assertSame([], $unknown['seasonality']['references']);
    }

    public function test_only_valid_verified_monthly_psa_region_ii_farmgate_php_observations_are_summarized(): void
    {
        $this->createPsaObservation(10, '2024-01-01', 'January 2024');
        $this->createPsaObservation(20, '2024-02-01', 'February 2024');
        $this->createPsaObservation(30, '2024-03-01', 'March 2024');

        $this->createPsaObservation(900, '2024-04-01', 'April 2024', ['geographic_scope' => 'Aparri, Cagayan']);
        $this->createPsaObservation(901, '2024-05-01', 'May 2024', ['price_type' => 'wholesale']);
        $this->createPsaObservation(902, '2024-06-01', 'June 2024', ['currency' => 'USD']);
        $this->createPsaObservation(903, '2024-07-01', 'July 2024', ['verification_status' => 'unverified']);
        $this->createPsaObservation(904, '2024-08-01', 'August 2024', [
            'period_frequency' => 'annual',
        ]);
        $this->createPsaObservation(905, '2024-09-01', 'September 2024', [
            'unit' => 'Peso per sack',
        ]);
        $this->createPsaObservation(906, '2024-10-01', 'October 2024', [
            'observation_period' => '2024-Q4',
        ]);
        $this->createPsaObservation(907, '2027-01-01', 'January 2027');

        $otherOfficialSource = AiDataSource::create([
            'source_name' => 'Other official source fixture',
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'is_official' => true,
            'is_active' => true,
        ]);
        $this->createPsaObservation(908, '2024-11-01', 'November 2024', [
            'ai_data_source_id' => $otherOfficialSource->id,
        ]);

        $result = $this->analyze();
        $reference = $result['psa_reference'];

        $this->assertSame(3, $reference['observation_count']);
        $this->assertSame(10.0, $reference['minimum_price']);
        $this->assertSame(30.0, $reference['maximum_price']);
        $this->assertSame(20.0, $reference['average_price']);
        $this->assertSame(20.0, $reference['median_price']);
        $this->assertSame('2024-01-01', $reference['first_observation_date']);
        $this->assertSame('2024-03-01', $reference['latest_available_observation']['date']);
        $this->assertSame('Region II / Cagayan Valley', $reference['geography']);
        $this->assertStringNotContainsString('Aparri', $reference['geography']);
    }

    public function test_producer_price_position_difference_percentage_and_zero_average_are_safe(): void
    {
        $this->createPsaObservation(10, '2024-01-01', 'January 2024');
        $this->createPsaObservation(20, '2024-02-01', 'February 2024');
        $this->createPsaObservation(30, '2024-03-01', 'March 2024');

        $below = $this->analyze(['producer_price' => 5, 'unit' => 'kg'])['producer_price_comparison'];
        $within = $this->analyze(['producer_price' => 20, 'unit' => 'kg'])['producer_price_comparison'];
        $above = $this->analyze(['producer_price' => 40, 'unit' => 'kg'])['producer_price_comparison'];

        $this->assertSame(DeterministicMarketAnalysisService::PRICE_POSITION_BELOW, $below['position_relative_to_range']);
        $this->assertSame(-15.0, $below['difference_from_average']);
        $this->assertSame(-75.0, $below['difference_percent_from_average']);
        $this->assertSame(DeterministicMarketAnalysisService::PRICE_POSITION_WITHIN, $within['position_relative_to_range']);
        $this->assertSame(DeterministicMarketAnalysisService::PRICE_POSITION_ABOVE, $above['position_relative_to_range']);
        $this->assertSame(20.0, $above['difference_from_average']);
        $this->assertSame(100.0, $above['difference_percent_from_average']);
        $this->assertSame(30.0, $within['latest_reference_price']);
        $this->assertSame(-10.0, $within['difference_from_latest_reference']);
        $this->assertSame(-33.33, $within['difference_percent_from_latest_reference']);

        MarketPriceReference::query()->delete();
        $this->createPsaObservation(0, '2025-01-01', 'January 2025');
        $zeroAverage = $this->analyze(['producer_price' => 10, 'unit' => 'kg'])['producer_price_comparison'];

        $this->assertNull($zeroAverage['reference_average_price']);
        $this->assertNull($zeroAverage['difference_from_average']);
        $this->assertNull($zeroAverage['difference_percent_from_average']);
        $this->assertSame(DeterministicMarketAnalysisService::PRICE_POSITION_NO_REFERENCE, $zeroAverage['position_relative_to_range']);
    }

    public function test_missing_psa_values_stay_absent_and_raw_zeroes_are_excluded_from_comparisons(): void
    {
        $importResult = app(ImportPsaMajorCropsFarmgatePrices::class)->importRows([[
            'commodity' => 'Cabbage',
            'geographic_scope' => '..REGION II (CAGAYAN VALLEY)',
            'year' => '2024',
            'period' => 'January',
            'price' => '..',
            'unit' => 'Peso per kilogram',
            'currency' => 'PHP',
            'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
        ]]);

        $this->assertSame(1, $importResult['skipped_missing']);
        $this->assertDatabaseCount('market_price_references', 0);
        $this->assertSame('NO_REFERENCE_AVAILABLE', $this->analyze()['psa_reference']['status']);

        $this->createPsaObservation(0, '2024-01-01', 'January 2024');
        $this->createPsaObservation(0, '2024-02-01', 'February 2024');
        $reference = $this->analyze()['psa_reference'];
        $this->assertSame(0, $reference['observation_count']);
        $this->assertSame(2, $reference['raw_observation_count']);
        $this->assertSame(2, $reference['excluded_zero_count']);
        $this->assertDatabaseCount('market_price_references', 2);
        $this->assertNull($reference['minimum_price']);
        $this->assertNull($reference['average_price']);
        $this->assertNull($reference['maximum_price']);
    }

    public function test_harborai_listing_prices_and_supply_remain_separate_from_psa_prices(): void
    {
        $this->createPsaObservation(10, '2024-01-01', 'January 2024');
        $producer = $this->createProducer('Verified');
        $this->createListing($producer, [
            'product_name' => 'Cabbage',
            'unit_of_measure' => 'kg',
            'unit' => 'kg',
            'current_price_per_unit' => 100,
            'price_per_unit' => 100,
            'quantity_available' => 10,
            'quantity' => 10,
        ]);
        $this->createListing($producer, [
            'product_name' => 'Cabbage',
            'unit_of_measure' => 'g',
            'unit' => 'g',
            'current_price_per_unit' => 0.03,
            'price_per_unit' => 0.03,
            'quantity_available' => 2500,
            'quantity' => 2500,
        ]);
        $this->createListing($producer, [
            'product_name' => 'Cabbage',
            'status' => 'Paused',
            'quantity_available' => 100,
            'quantity' => 100,
        ]);
        $unverifiedProducer = $this->createProducer('Pending');
        $this->createListing($unverifiedProducer, ['product_name' => 'Cabbage']);

        $result = $this->analyze(['unit' => 'kg']);

        $this->assertSame(10.0, $result['psa_reference']['average_price']);
        $this->assertSame('HARBORAI LISTING REFERENCE', $result['harborai_listing_reference']['label']);
        $this->assertSame(2, $result['harborai_listing_reference']['active_listing_count']);
        $this->assertSame(2, $result['harborai_listing_reference']['active_listing_price_count']);
        $this->assertSame(30.0, $result['harborai_listing_reference']['minimum_active_listing_price']);
        $this->assertSame(100.0, $result['harborai_listing_reference']['maximum_active_listing_price']);
        $this->assertSame(65.0, $result['harborai_listing_reference']['average_active_listing_price']);
        $this->assertSame(12.5, $result['supply_context']['quantity_total']);
        $this->assertSame('kg', $result['supply_context']['quantity_unit']);
        $this->assertSame('AVAILABLE', $result['supply_context']['status']);
    }

    public function test_active_buyer_requests_are_counted_with_totals_only_when_units_convert(): void
    {
        $this->createDemand('Active', 1000, 'g');
        $this->createDemand('Offers Received', 2, 'kg');
        $this->createDemand('open', 500, 'g');
        $this->createDemand('Rejected', 999, 'kg');
        $this->createDemand('Active', 999, 'kg', ['deadline' => '2026-09-30']);

        $result = $this->analyze(['unit' => 'kg']);
        $this->assertSame(3, $result['demand_context']['active_demand_request_count']);
        $this->assertSame(3.5, $result['demand_context']['requested_quantity_total']);
        $this->assertSame('AVAILABLE', $result['demand_context']['quantity_status']);
        $this->assertSame('HarborAI active buyer requests; locations are not filtered or inferred', $result['demand_context']['scope']);
        $this->assertStringNotContainsString('Aparri', $result['demand_context']['scope']);

        $this->createDemand('Active', 4, 'sack');
        $incompatible = $this->analyze(['unit' => 'kg'])['demand_context'];
        $this->assertSame(4, $incompatible['active_demand_request_count']);
        $this->assertNull($incompatible['requested_quantity_total']);
        $this->assertSame('UNIT_INCOMPATIBLE', $incompatible['quantity_status']);
    }

    public function test_demands_without_an_institutional_buyer_account_are_not_included(): void
    {
        $admin = User::create([
            'email' => 'demand-admin-' . uniqid() . '@example.test',
            'password_hash' => 'test-password',
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'user_type' => 'Admin',
        ]);
        $adminBuyer = Buyer::create([
            'user_id' => $admin->user_id,
            'organization_name' => 'Invalid admin-linked buyer fixture',
        ]);
        $this->createDemand('Active', 20, 'kg', ['buyer_id' => $adminBuyer->buyer_id]);

        $result = $this->analyze(['unit' => 'kg']);
        $this->assertSame(0, $result['demand_context']['active_demand_request_count']);
        $this->assertSame(0.0, $result['demand_context']['requested_quantity_total']);
    }

    public function test_only_verified_kg_gram_conversion_is_used_and_gross_listing_value_is_not_profit(): void
    {
        $this->createPsaObservation(20, '2024-01-01', 'January 2024');
        $perGram = $this->analyze([
            'unit' => 'g',
            'quantity' => 1000,
            'producer_price' => 0.02,
            'quality_grade' => 'Grade A',
        ]);

        $this->assertSame(20.0, $perGram['producer_price_comparison']['normalized_price_per_kg']);
        $this->assertSame(DeterministicMarketAnalysisService::PRICE_POSITION_WITHIN, $perGram['producer_price_comparison']['position_relative_to_range']);
        $this->assertSame('CALCULATED', $perGram['gross_listing_value']['status']);
        $this->assertSame(20.0, $perGram['gross_listing_value']['amount']);
        $this->assertFalse($perGram['gross_listing_value']['is_profit']);
        $this->assertSame('Grade A', $perGram['quality_context']['quality_grade']);
        $this->assertFalse($perGram['quality_context']['price_adjustment_applied']);

        $sack = $this->analyze(['unit' => 'sack', 'quantity' => 2, 'producer_price' => 50]);
        $this->assertSame('UNIT_INCOMPATIBLE', $sack['producer_price_comparison']['status']);
        $this->assertNull($sack['producer_price_comparison']['normalized_price_per_kg']);
        $this->assertSame('CALCULATED', $sack['gross_listing_value']['status']);
        $this->assertSame(100.0, $sack['gross_listing_value']['amount']);

        $commoditySpecificConversion = UnitConversionReference::create([
            'commodity_reference_id' => $this->cabbage->id,
            'from_unit' => 'kg',
            'to_unit' => 'sack',
            'conversion_factor' => 0.01,
            'source_reference' => 'TEST FIXTURE ONLY - unverified conversion check',
            'verification_status' => 'unverified',
        ]);
        $this->assertSame('unverified', $commoditySpecificConversion->verification_status);
        $stillIncompatible = $this->analyze(['unit' => 'sack', 'producer_price' => 500]);
        $this->assertSame('UNIT_INCOMPATIBLE', $stillIncompatible['producer_price_comparison']['status']);
    }

    public function test_no_validated_seasonality_returns_insufficient_data(): void
    {
        SeasonalityReference::create([
            'commodity_reference_id' => $this->cabbage->id,
            'ai_data_source_id' => $this->psaSource->id,
            'month' => 8,
            'season_status' => 'harvest',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'source_reference' => 'TEST FIXTURE ONLY - unverified seasonality',
            'verification_status' => 'unverified',
        ]);

        $seasonality = $this->analyze()['seasonality'];
        $this->assertSame('INSUFFICIENT_DATA', $seasonality['status']);
        $this->assertSame(0, $seasonality['validated_reference_count']);
        $this->assertSame([], $seasonality['references']);
        $this->assertSame('October', $seasonality['analysis_month_name']);
        $this->assertStringContainsString('will not infer a season', $seasonality['interpretation']);
    }

    public function test_verified_harvest_range_produces_deterministic_in_season_approaching_ending_and_off_season_statuses(): void
    {
        $source = $this->createOfficialSeasonalitySource();
        $this->createSeasonalityReference($source, [
            'season_status' => 'harvest',
            'start_month' => 9,
            'end_month' => 12,
            'period_label' => 'September–December',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'source_reference' => 'TEST FIXTURE ONLY - documented range',
        ]);

        $inSeason = $this->analyze(['analysis_date' => '2026-10-15'])['seasonality'];
        $this->assertSame('IN SEASON', $inSeason['status']);
        $this->assertSame(['September–December'], $inSeason['references'][0]['periods']);
        $this->assertSame('TEST Official Agriculture Source', $inSeason['references'][0]['source_name']);
        $this->assertSame('Region II / Cagayan Valley', $inSeason['references'][0]['geographic_scope']);
        $this->assertStringContainsString('documented harvest period', $inSeason['interpretation']);
        $this->assertStringContainsString('may not reflect exact farm', $inSeason['geographic_limitation']);

        $approaching = $this->analyze(['analysis_date' => '2026-08-15'])['seasonality'];
        $this->assertSame('APPROACHING SEASON', $approaching['status']);
        $this->assertSame('August', $approaching['analysis_month_name']);

        $ending = $this->analyze(['analysis_date' => '2026-12-15'])['seasonality'];
        $this->assertSame('ENDING SEASON', $ending['status']);

        $offSeason = $this->analyze(['analysis_date' => '2026-04-15'])['seasonality'];
        $this->assertSame('OFF-SEASON', $offSeason['status']);
    }

    public function test_cross_year_harvest_ranges_peak_months_and_year_round_are_explicit(): void
    {
        $source = $this->createOfficialSeasonalitySource();
        $this->createSeasonalityReference($source, [
            'season_status' => 'harvest',
            'start_month' => 11,
            'end_month' => 2,
            'geographic_scope' => 'Philippines',
        ]);
        $january = $this->analyze(['analysis_date' => '2026-01-15'])['seasonality'];
        $this->assertSame('IN SEASON', $january['status']);
        $this->assertSame(['November – February'], $january['references'][0]['periods']);

        SeasonalityReference::query()->delete();
        $this->createSeasonalityReference($source, [
            'season_status' => 'peak',
            'month' => 10,
            'period_label' => 'October peak (test fixture)',
            'geographic_scope' => 'Region II / Cagayan Valley',
        ]);
        $peak = $this->analyze(['analysis_date' => '2026-10-15'])['seasonality'];
        $this->assertSame('PEAK SEASON', $peak['status']);

        SeasonalityReference::query()->delete();
        $this->createSeasonalityReference($source, [
            'season_status' => 'year_round',
            'period_label' => 'Year-round',
            'geographic_scope' => 'Philippines',
        ]);
        $yearRound = $this->analyze(['analysis_date' => '2026-04-15'])['seasonality'];
        $this->assertSame('YEAR-ROUND', $yearRound['status']);

        $this->createSeasonalityReference($source, [
            'season_status' => 'off_season',
            'month' => 4,
            'period_label' => 'April off-season (test fixture)',
            'geographic_scope' => 'Philippines',
        ]);
        $conflicting = $this->analyze(['analysis_date' => '2026-04-15'])['seasonality'];
        $this->assertSame('CONFLICTING_REFERENCES', $conflicting['status']);
        $this->assertStringContainsString('does not select a seasonal status', $conflicting['interpretation']);
    }

    public function test_ambiguous_varieties_keep_separate_seasonality_and_fisheries_do_not_use_crop_phase_logic(): void
    {
        $source = $this->createOfficialSeasonalitySource();
        $this->createPsaObservation(20, '2025-01-01', 'January 2025', [
            'commodity_reference_id' => CommodityReference::query()->where('canonical_name', 'Banana Bungulan')->value('id'),
            'source_commodity_name' => 'Banana Bungulan',
        ]);
        $this->createPsaObservation(30, '2025-01-01', 'January 2025', [
            'commodity_reference_id' => CommodityReference::query()->where('canonical_name', 'Banana Saba')->value('id'),
            'source_commodity_name' => 'Banana Saba',
        ]);
        foreach ([
            ['Banana Bungulan', 9, 11],
            ['Banana Saba', 2, 4],
        ] as [$label, $start, $end]) {
            $commodity = CommodityReference::query()->where('canonical_name', $label)->firstOrFail();
            $this->createSeasonalityReference($source, [
                'commodity_reference_id' => $commodity->id,
                'season_status' => 'harvest',
                'start_month' => $start,
                'end_month' => $end,
                'geographic_scope' => 'Region II / Cagayan Valley',
            ]);
        }

        $ambiguous = $this->analysis->analyze(['product_name' => 'Banana', 'analysis_date' => '2026-10-15'])['seasonality'];
        $this->assertSame('AMBIGUOUS', $ambiguous['status']);
        $this->assertCount(2, $ambiguous['candidates']);
        $this->assertSame('IN SEASON', $ambiguous['candidates'][0]['references'][0]['status']);
        $this->assertSame('OFF-SEASON', $ambiguous['candidates'][1]['references'][0]['status']);
        $this->assertStringContainsString('No single variety', $ambiguous['interpretation']);

        $fish = CommodityReference::create(['canonical_name' => 'Tilapia', 'category' => 'Fish']);
        $fishStatus = $this->createSeasonalityReference($source, [
            'commodity_reference_id' => $fish->id,
            'season_status' => 'harvest',
            'start_month' => 9,
            'end_month' => 12,
            'geographic_scope' => 'Region II / Cagayan Valley',
        ]);
        $this->assertSame('verified', $fishStatus->verification_status);
        $fishAnalysis = $this->analysis->analyze(['product_name' => 'Tilapia', 'analysis_date' => '2026-10-15'])['seasonality'];
        $this->assertSame('DOCUMENTED PERIOD', $fishAnalysis['status']);
        $this->assertStringContainsString('not interpreted as crop planting or harvest', $fishAnalysis['interpretation']);

        $bangus = CommodityReference::create(['canonical_name' => 'Bangus', 'category' => 'Fish']);
        $fishWithoutEvidence = $this->analysis->analyze(['product_name' => $bangus->canonical_name])['seasonality'];
        $this->assertSame('INSUFFICIENT_DATA', $fishWithoutEvidence['status']);
        $this->assertSame([], $fishWithoutEvidence['references']);
    }

    public function test_verified_records_from_inactive_or_non_official_sources_are_not_used(): void
    {
        $inactiveSource = $this->createOfficialSeasonalitySource();
        $inactiveSource->is_active = false;
        $inactiveSource->save();
        $this->createSeasonalityReference($inactiveSource);

        $nonOfficialSource = AiDataSource::create([
            'source_name' => 'TEST Non-official seasonality source',
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'is_official' => false,
            'is_active' => true,
        ]);
        $this->createSeasonalityReference($nonOfficialSource, [
            'source_reference' => 'TEST non-official source record',
        ]);

        $result = $this->analyze(['analysis_date' => '2026-10-15'])['seasonality'];
        $this->assertSame('INSUFFICIENT_DATA', $result['status']);
        $this->assertSame(0, $result['validated_reference_count']);
        $this->assertSame([], $result['references']);
    }

    public function test_data_sufficiency_follows_the_explicit_reference_and_context_rules(): void
    {
        $unresolved = $this->analysis->analyze(['product_name' => 'Cabbage-ish', 'analysis_date' => '2026-10-01']);
        $this->assertSame('INSUFFICIENT_DATA', $unresolved['data_sufficiency']['status']);

        $noMatchedData = $this->analyze();
        $this->assertSame('INSUFFICIENT_DATA', $noMatchedData['data_sufficiency']['status']);

        $this->createDemand('Active', 1, 'kg');
        $platformOnly = $this->analyze();
        $this->assertSame('LIMITED', $platformOnly['data_sufficiency']['status']);

        $this->createPsaObservation(20, '2024-01-01', 'January 2024');
        $referenceWithoutPrice = $this->analyze();
        $this->assertSame('MODERATE', $referenceWithoutPrice['data_sufficiency']['status']);

        $comparable = $this->analyze(['producer_price' => 20, 'unit' => 'kg']);
        $this->assertSame('HIGH', $comparable['data_sufficiency']['status']);
        $this->assertSame('PSA_REFERENCE_AND_COMPATIBLE_PRODUCER_PRICE_AVAILABLE', $comparable['data_sufficiency']['basis']);
    }

    public function test_generic_eggplant_discovers_each_psa_variety_with_independent_statistics(): void
    {
        $expected = [
            'Eggplant, long, purple' => [10, 20],
            'Eggplant, native, long' => [100, 200],
            'Eggplant, native, round' => [1000, 2000],
        ];

        foreach ($expected as $label => $prices) {
            $reference = CommodityReference::query()->where('canonical_name', $label)->firstOrFail();
            $this->createPsaObservation($prices[0], '2024-01-01', 'January 2024', [
                'commodity_reference_id' => $reference->id,
                'source_commodity_name' => $label,
            ]);
            $this->createPsaObservation($prices[1], '2024-02-01', 'February 2024', [
                'commodity_reference_id' => $reference->id,
                'source_commodity_name' => $label,
            ]);
        }

        $result = $this->analyze(['product_name' => '  eGgPlAnT  ', 'producer_price' => 15, 'unit' => 'kg']);
        $series = collect($result['psa_reference_candidates'])->keyBy('commodity_label');

        $this->assertSame('MULTIPLE_MATCHES', $result['commodity_resolution']['status']);
        $this->assertSame(3, $result['commodity_resolution']['candidate_count']);
        $this->assertSame('MULTIPLE_REFERENCES', $result['psa_reference']['status']);
        $this->assertSame(0, $result['psa_reference']['observation_count']);
        $this->assertSame('MULTIPLE_REFERENCES', $result['producer_price_comparison']['status']);
        $this->assertSame(3, $series->count());
        foreach ($expected as $label => [$minimum, $maximum]) {
            $this->assertSame(2, $series[$label]['psa_reference']['observation_count']);
            $this->assertSame((float) $minimum, $series[$label]['psa_reference']['minimum_price']);
            $this->assertSame((float) $maximum, $series[$label]['psa_reference']['maximum_price']);
            $this->assertSame((float) (($minimum + $maximum) / 2), $series[$label]['psa_reference']['average_price']);
            $this->assertSame((float) (($minimum + $maximum) / 2), $series[$label]['producer_price_comparison']['reference_average_price']);
        }
    }

    public function test_ambiguous_coconut_series_and_piece_prices_are_not_combined_or_converted(): void
    {
        foreach (['Coconut Mature', 'Coconut Young'] as $label) {
            $commodity = CommodityReference::query()->where('canonical_name', $label)->firstOrFail();
            $this->createPsaObservation(25, '2025-01-01', 'January 2025', [
                'commodity_reference_id' => $commodity->id,
                'source_commodity_name' => $label,
            ]);
        }

        $result = $this->analyze([
            'product_name' => 'Coconut',
            'producer_price' => 20,
            'unit' => 'piece',
        ]);

        $this->assertSame('MULTIPLE_MATCHES', $result['commodity_resolution']['status']);
        $this->assertSame('MULTIPLE_REFERENCES', $result['psa_reference']['status']);
        $this->assertSame('MULTIPLE_REFERENCES', $result['producer_price_comparison']['status']);
        $this->assertNull($result['psa_reference']['latest_available_observation']);
        $this->assertCount(2, $result['psa_reference_candidates']);
        foreach ($result['psa_reference_candidates'] as $candidate) {
            $this->assertSame(1, $candidate['psa_reference']['observation_count']);
            $this->assertSame('UNIT_INCOMPATIBLE', $candidate['producer_price_comparison']['status']);
        }
    }

    public function test_conservative_spelling_matching_and_approved_aliases_are_supported(): void
    {
        $eggplant = CommodityReference::query()->where('canonical_name', 'Eggplant, long, purple')->firstOrFail();
        $this->createPsaObservation(20, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $eggplant->id,
            'source_commodity_name' => $eggplant->canonical_name,
        ]);
        $misspelled = $this->analyze(['product_name' => 'Eggplnt']);

        $this->assertSame('RESOLVED', $misspelled['commodity_resolution']['status']);
        $this->assertSame('CONSERVATIVE_TOKEN_SIMILARITY', $misspelled['commodity_resolution']['resolved_by']);
        $this->assertSame($eggplant->canonical_name, $misspelled['commodity_resolution']['canonical_name']);

        $this->cabbage->aliases = [...($this->cabbage->aliases ?? []), 'Green cabbage'];
        $this->cabbage->save();
        $alias = $this->analyze(['product_name' => '  GREEN   cabbage ']);
        $this->assertSame($this->cabbage->id, $alias['commodity_resolution']['commodity_reference_id']);
        $this->assertSame('EXACT_PRODUCT_NAME', $alias['commodity_resolution']['resolved_by']);

        $repolyo = $this->analyze(['product_name' => 'Repolyo']);
        $this->assertSame($this->cabbage->id, $repolyo['commodity_resolution']['commodity_reference_id']);
        $this->assertSame('EXACT_PRODUCT_NAME', $repolyo['commodity_resolution']['resolved_by']);

        $cassava = $this->analyze(['product_name' => 'Cassava (Kamoteng Kahoy)']);
        $this->assertSame('Cassava for food, fresh tubers', $cassava['commodity_resolution']['canonical_name']);
    }

    public function test_rice_and_palay_do_not_claim_a_psa_reference_without_matching_series(): void
    {
        foreach (['Rice', 'Palay'] as $productName) {
            $result = $this->analyze(['product_name' => $productName]);
            $this->assertSame('UNRESOLVED', $result['commodity_resolution']['status']);
            $this->assertSame(0, $result['commodity_resolution']['candidate_count']);
            $this->assertSame([], $result['psa_reference_candidates']);
            $this->assertSame('NO_REFERENCE_AVAILABLE', $result['psa_reference']['status']);
            $this->assertSame(0, $result['psa_reference']['observation_count']);
        }
    }

    public function test_rice_and_palay_related_search_terms_only_match_actual_psa_series(): void
    {
        $palay = CommodityReference::create(['canonical_name' => 'Palay, dry', 'category' => 'Grains']);
        $this->createPsaObservation(35, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $palay->id,
            'source_commodity_name' => 'Palay, dry',
        ]);

        $result = $this->analyze(['product_name' => 'Rice']);

        $this->assertSame('RESOLVED', $result['commodity_resolution']['status']);
        $this->assertSame('Palay, dry', $result['commodity_resolution']['canonical_name']);
        $this->assertSame('PSA_SERIES_PREFIX', $result['commodity_resolution']['resolved_by']);
        $this->assertSame('Palay, dry', $result['psa_reference_candidates'][0]['commodity_label']);
        $this->assertSame(1, $result['psa_reference_candidates'][0]['psa_reference']['observation_count']);
        $this->assertSame('Rice', $result['input_context']['product_name']);
    }

    public function test_new_psa_backed_series_are_discovered_without_product_specific_code(): void
    {
        $dragonfruit = CommodityReference::create([
            'canonical_name' => 'Dragonfruit, red',
            'category' => 'Fruits',
        ]);
        $this->createPsaObservation(80, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $dragonfruit->id,
            'source_commodity_name' => 'Dragonfruit, red',
        ]);

        $result = $this->analyze(['product_name' => 'Dragonfruit']);

        $this->assertSame('RESOLVED', $result['commodity_resolution']['status']);
        $this->assertSame('Dragonfruit, red', $result['commodity_resolution']['canonical_name']);
        $this->assertSame(1, $result['psa_reference']['observation_count']);
        $this->assertSame(80.0, $result['psa_reference']['average_price']);
    }

    public function test_exact_tomato_analysis_keeps_legacy_single_reference_fields(): void
    {
        $tomato = CommodityReference::query()->where('canonical_name', 'Tomato')->firstOrFail();
        $this->createPsaObservation(30, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $tomato->id,
            'source_commodity_name' => 'Tomato',
        ]);
        $this->createPsaObservation(50, '2024-02-01', 'February 2024', [
            'commodity_reference_id' => $tomato->id,
            'source_commodity_name' => 'Tomato',
        ]);

        $result = $this->analyze(['product_name' => '  tOmAtO  ', 'producer_price' => 40, 'unit' => 'kg']);

        $this->assertSame('RESOLVED', $result['commodity_resolution']['status']);
        $this->assertSame('Tomato', $result['commodity_resolution']['canonical_name']);
        $this->assertSame(1, $result['commodity_resolution']['candidate_count']);
        $this->assertSame(2, $result['psa_reference']['observation_count']);
        $this->assertSame(40.0, $result['psa_reference']['average_price']);
        $this->assertSame('COMPARABLE', $result['producer_price_comparison']['status']);
        $this->assertSame(1, count($result['psa_reference_candidates']));
    }

    public function test_unrelated_multiword_product_does_not_receive_a_fuzzy_psa_match(): void
    {
        $this->createPsaObservation(20, '2024-01-01', 'January 2024');

        $result = $this->analyze(['product_name' => 'Cabbage-like vegetable']);

        $this->assertSame('UNRESOLVED', $result['commodity_resolution']['status']);
        $this->assertSame([], $result['psa_reference_candidates']);
        $this->assertSame('NO_REFERENCE_AVAILABLE', $result['psa_reference']['status']);
    }

    public function test_psa_series_discovery_and_observation_retrieval_use_identical_eligibility(): void
    {
        $tomato = CommodityReference::query()->where('canonical_name', 'Tomato')->firstOrFail();
        $potato = CommodityReference::query()->where('canonical_name', 'Potato')->firstOrFail();

        // A series whose only rows fail one shared eligibility rule must not be
        // discovered as available at all.
        $this->createPsaObservation(50, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $tomato->id,
            'source_commodity_name' => $tomato->canonical_name,
            'verification_status' => 'unverified',
        ]);
        $this->createPsaObservation(60, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $potato->id,
            'source_commodity_name' => $potato->canonical_name,
            'observation_period' => null,
            'period_frequency' => null,
        ]);

        // An eligible series exists, so PSA series discovery is active.
        $this->createPsaObservation(25, '2024-01-01', 'January 2024');

        // Discovery must not surface a series whose only rows fail the shared
        // eligibility rules, even though another series is discoverable.
        $this->assertSame('UNRESOLVED', $this->analyze(['product_name' => 'Toma'])['commodity_resolution']['status']);
        $this->assertSame([], $this->analyze(['product_name' => 'Toma'])['psa_reference_candidates']);
        $this->assertSame([], $this->analyze(['product_name' => 'Pota'])['psa_reference_candidates']);

        // An exact name may still resolve by name, but an ineligible row
        // contributes no observation and no reference.
        $unverifiedOnly = $this->analyze(['product_name' => 'Tomato']);
        $this->assertSame('RESOLVED', $unverifiedOnly['commodity_resolution']['status']);
        $this->assertSame('EXACT_PRODUCT_NAME', $unverifiedOnly['commodity_resolution']['resolved_by']);
        $this->assertSame(0, $unverifiedOnly['psa_reference']['observation_count']);
        $this->assertSame('NO_REFERENCE_AVAILABLE', $unverifiedOnly['psa_reference']['status']);
        $this->assertSame(0, $this->analyze(['product_name' => 'Potato'])['psa_reference']['observation_count']);

        // One eligible row makes the same series discoverable and summarisable.
        $this->createPsaObservation(30, '2024-02-01', 'February 2024', [
            'commodity_reference_id' => $tomato->id,
            'source_commodity_name' => $tomato->canonical_name,
        ]);

        $discovered = $this->analyze(['product_name' => 'Toma']);
        $this->assertSame('RESOLVED', $discovered['commodity_resolution']['status']);
        $this->assertSame('PSA_SERIES_PREFIX', $discovered['commodity_resolution']['resolved_by']);
        $this->assertSame($tomato->id, $discovered['commodity_resolution']['commodity_reference_id']);
        $this->assertSame(1, $discovered['psa_reference']['observation_count']);
        $this->assertSame(30.0, $discovered['psa_reference']['average_price']);
    }

    public function test_generic_cassava_discloses_the_unmapped_sibling_without_adding_or_merging_it(): void
    {
        $food = CommodityReference::query()
            ->where('canonical_name', 'Cassava for food, fresh tubers')
            ->firstOrFail();
        $this->createPsaObservation(20, '2024-01-01', 'January 2024', [
            'commodity_reference_id' => $food->id,
            'source_commodity_name' => $food->canonical_name,
        ]);

        $generic = $this->analyze(['product_name' => 'Cassava']);
        $coverage = $generic['commodity_resolution']['source_coverage'];

        $this->assertSame('RESOLVED', $generic['commodity_resolution']['status']);
        $this->assertSame('Cassava for food, fresh tubers', $generic['commodity_resolution']['canonical_name']);
        $this->assertCount(1, $generic['psa_reference_candidates']);
        $this->assertSame('Cassava for food, fresh tubers', $generic['psa_reference_candidates'][0]['commodity_label']);
        $this->assertSame('PARTIAL_SOURCE_COVERAGE', $coverage['status']);
        $this->assertSame(['Cassava for food, fresh tubers'], $coverage['covered_series']);
        $this->assertSame(['Cassava for industrial use, fresh tubers'], $coverage['unmapped_source_siblings']);
        $this->assertStringContainsString('never combined', $coverage['interpretation']);

        // The unmapped sibling is never created, mapped, or given statistics.
        $this->assertDatabaseMissing('commodity_references', ['canonical_name' => 'Cassava for industrial use, fresh tubers']);
        $this->assertSame(1, MarketPriceReference::count());
        $this->assertSame(20.0, $generic['psa_reference']['average_price']);

        // Explicitly naming the mapped series is unaffected and raises no sibling
        // warning, because the caller did not ask for the whole crop.
        $explicit = $this->analyze(['product_name' => 'Cassava for food, fresh tubers']);
        $this->assertSame('RESOLVED', $explicit['commodity_resolution']['status']);
        $this->assertSame($food->id, $explicit['commodity_resolution']['commodity_reference_id']);
        $this->assertSame('EXACT_PRODUCT_NAME', $explicit['commodity_resolution']['resolved_by']);
        $this->assertSame('COMPLETE_MAPPED_COVERAGE', $explicit['commodity_resolution']['source_coverage']['status']);
        $this->assertSame([], $explicit['commodity_resolution']['source_coverage']['unmapped_source_siblings']);
        $this->assertSame(20.0, $explicit['psa_reference']['average_price']);

        // The documented alias for the mapped series behaves the same way.
        $aliased = $this->analyze(['product_name' => 'Cassava (Kamoteng Kahoy)']);
        $this->assertSame('EXACT_PRODUCT_NAME', $aliased['commodity_resolution']['resolved_by']);
        $this->assertSame('COMPLETE_MAPPED_COVERAGE', $aliased['commodity_resolution']['source_coverage']['status']);

        // An unrelated product is not given a cassava coverage warning.
        $this->assertSame('NO_MATCHED_SERIES', $this->analyze(['product_name' => 'Kalabasa'])['commodity_resolution']['source_coverage']['status']);

        // A fully mapped generic stem reports complete coverage.
        $this->assertSame(
            'COMPLETE_MAPPED_COVERAGE',
            $this->analyze(['product_name' => 'Cabbage'])['commodity_resolution']['source_coverage']['status']
        );
    }

    public function test_fractional_reference_ids_do_not_cast_to_a_different_commodity(): void
    {
        $banana = CommodityReference::query()->where('canonical_name', 'Banana Cavendish')->firstOrFail();
        $result = $this->analysis->analyze([
            'commodity_reference_id' => $banana->id . '.5',
            'product_name' => 'Cabbage',
            'analysis_date' => '2026-10-01',
        ]);

        $this->assertSame($this->cabbage->id, $result['commodity_resolution']['commodity_reference_id']);
        $this->assertSame('EXACT_PRODUCT_NAME', $result['commodity_resolution']['resolved_by']);
    }

    private function analyze(array $overrides = []): array
    {
        return $this->analysis->analyze(array_merge([
            'product_name' => 'Cabbage',
            'analysis_date' => '2026-10-01',
        ], $overrides));
    }

    private function createPsaObservation(float $price, string $date, string $period, array $overrides = []): MarketPriceReference
    {
        return MarketPriceReference::create(array_merge([
            'commodity_reference_id' => $this->cabbage->id,
            'ai_data_source_id' => $this->psaSource->id,
            'price' => $price,
            'currency' => 'PHP',
            'unit' => 'Peso per kilogram',
            'price_type' => 'farmgate',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
            'source_commodity_name' => 'Cabbage',
            'source_geographic_scope' => '..REGION II (CAGAYAN VALLEY)',
            'observation_date' => $date,
            'observation_period' => $period,
            'period_frequency' => 'monthly',
            'observation_status' => 'preliminary',
            'verification_status' => 'verified',
        ], $overrides));
    }

    private function createOfficialSeasonalitySource(): AiDataSource
    {
        return AiDataSource::create([
            'source_name' => 'TEST Official Agriculture Source',
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'organization' => 'TEST organization - not a real source',
            'geographic_scope' => 'Test geography',
            'is_official' => true,
            'is_active' => true,
        ]);
    }

    private function createSeasonalityReference(AiDataSource $source, array $overrides = []): SeasonalityReference
    {
        return SeasonalityReference::create(array_merge([
            'commodity_reference_id' => $this->cabbage->id,
            'ai_data_source_id' => $source->id,
            'season_status' => 'harvest',
            'start_month' => 9,
            'end_month' => 12,
            'geographic_scope' => 'Test geography',
            'source_reference' => 'TEST FIXTURE ONLY',
            'verification_status' => 'verified',
        ], $overrides));
    }

    private function createProducer(string $verificationStatus): Producer
    {
        $user = User::create([
            'email' => 'producer-' . uniqid() . '@example.test',
            'password_hash' => 'test-password',
            'first_name' => 'Test',
            'last_name' => 'Producer',
            'user_type' => 'Farmer',
        ]);

        return Producer::create([
            'user_id' => $user->user_id,
            'location' => 'Aparri, Cagayan',
            'verification_status' => $verificationStatus,
        ]);
    }

    private function createListing(Producer $producer, array $overrides = []): ProductListing
    {
        return ProductListing::create(array_merge([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Cabbage',
            'product_category' => 'Vegetables',
            'category' => 'Vegetables',
            'current_price_per_unit' => 20,
            'price_per_unit' => 20,
            'unit_of_measure' => 'kg',
            'unit' => 'kg',
            'quantity_available' => 10,
            'quantity' => 10,
            'status' => 'Active',
        ], $overrides));
    }

    private function createDemand(string $status, int $quantity, string $unit, array $overrides = []): DemandRequest
    {
        if (!array_key_exists('buyer_id', $overrides)) {
            $user = User::create([
                'email' => 'demand-buyer-' . uniqid() . '@example.test',
                'password_hash' => 'test-password',
                'first_name' => 'Test',
                'last_name' => 'Buyer',
                'user_type' => 'Buyer',
            ]);
            $buyer = Buyer::create([
                'user_id' => $user->user_id,
                'organization_name' => 'Test buyer',
            ]);
            $overrides['buyer_id'] = $buyer->buyer_id;
        }

        $demand = DemandRequest::create(array_merge([
            'product_name' => 'Cabbage',
            'category' => 'Vegetables',
            'quantity_needed' => $quantity,
            'unit' => $unit,
            'status' => $status,
            'deadline' => '2026-10-01',
        ], $overrides));
        $reviewer = User::create(['email'=>'reviewer-'.uniqid().'@example.test','first_name'=>'Synthetic','last_name'=>'Reviewer','user_type'=>'Admin','password_hash'=>'test-only']);
        $demand->publicationReviews()->create(['actor_user_id'=>$reviewer->user_id,'decision'=>'Approved']);
        return $demand;
    }
}
