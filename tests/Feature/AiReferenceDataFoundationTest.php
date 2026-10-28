<?php

namespace Tests\Feature;

use App\Models\AiDataSource;
use App\Models\CommodityReference;
use App\Models\MarketPriceReference;
use App\Models\ProductListing;
use App\Models\SeasonalityReference;
use App\Models\UnitConversionReference;
use App\Services\Imports\ImportPsaMajorCropsFarmgatePrices as PsaMajorCropsImporter;
use Database\Seeders\AiReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class AiReferenceDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_tables_exist_after_migrations(): void
    {
        foreach ([
            'ai_data_sources',
            'commodity_references',
            'market_price_references',
            'seasonality_references',
            'unit_conversion_references',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table} table.");
        }
    }

    public function test_commodity_references_normalize_names_without_changing_listing_names(): void
    {
        $commodity = CommodityReference::create([
            'canonical_name' => '  Yellow Corn  ',
            'category' => 'Grains',
            'aliases' => ['maize'],
        ]);

        $listing = ProductListing::create([
            'producer_id' => 999,
            'product_name' => 'Our Yellow Corn (Native)',
            'category' => 'Grains',
            'product_category' => 'Grains',
            'unit' => 'kg',
            'unit_of_measure' => 'kg',
            'price_per_unit' => 40,
            'current_price_per_unit' => 40,
        ]);

        $this->assertSame('yellow-corn', $commodity->normalized_name);
        $this->assertSame(['maize'], $commodity->aliases);
        $this->assertSame('Our Yellow Corn (Native)', $listing->fresh()->product_name);
    }

    public function test_market_price_preserves_basis_unit_location_date_and_source_type(): void
    {
        $source = AiDataSource::create([
            'source_name' => 'Simulated training fixture',
            'source_type' => AiDataSource::TYPE_SYNTHETIC,
            'geographic_scope' => 'Aparri, Cagayan',
        ]);
        $commodity = CommodityReference::create(['canonical_name' => 'Tilapia', 'category' => 'Fish']);

        $price = MarketPriceReference::create([
            'commodity_reference_id' => $commodity->id,
            'ai_data_source_id' => $source->id,
            'price' => 125.5,
            'currency' => 'PHP',
            'unit' => 'kg',
            'price_type' => 'farmgate',
            'geographic_scope' => 'Aparri, Cagayan',
            'source_reference' => 'Fixture record for type distinction test',
            'observation_date' => '2026-10-01',
            'verification_status' => 'unverified',
        ]);

        $this->assertSame('farmgate', $price->price_type);
        $this->assertSame('kg', $price->unit);
        $this->assertSame('Aparri, Cagayan', $price->geographic_scope);
        $this->assertSame('2026-10-01', $price->observation_date->toDateString());
        $this->assertSame(AiDataSource::TYPE_SYNTHETIC, $price->dataSource->source_type);
        $this->assertSame('Tilapia', $price->commodity->canonical_name);
    }

    public function test_price_values_allow_zero_but_reject_negative_values_and_unsupported_categories(): void
    {
        $source = AiDataSource::create([
            'source_name' => 'HarborAI test source',
            'source_type' => AiDataSource::TYPE_HARBORAI_ACTUAL,
        ]);
        $commodity = CommodityReference::create(['canonical_name' => 'Rice', 'category' => 'Grains']);

        try {
            CommodityReference::create(['canonical_name' => 'Other', 'category' => 'Other']);
            $this->fail('Unsupported category should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            MarketPriceReference::create([
                'commodity_reference_id' => $commodity->id,
                'ai_data_source_id' => $source->id,
                'price' => '0.00',
                'currency' => 'PHP',
                'unit' => 'kg',
                'price_type' => 'retail',
                'geographic_scope' => 'Cagayan',
                'observation_date' => '2026-10-01',
            ]);
            $this->fail('A zero price from a nonofficial retail source should still be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $officialSource = AiDataSource::create([
            'source_name' => 'Official farmgate zero fixture',
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'is_official' => true,
        ]);
        $zeroPrice = MarketPriceReference::create([
            'commodity_reference_id' => $commodity->id,
            'ai_data_source_id' => $officialSource->id,
            'price' => '0.00',
            'currency' => 'PHP',
            'unit' => 'kg',
            'price_type' => 'farmgate',
            'geographic_scope' => 'Region II / Cagayan Valley',
            'observation_date' => '2026-10-01',
        ]);
        $this->assertSame('0.0000', $zeroPrice->price);

        try {
            MarketPriceReference::create([
                'commodity_reference_id' => $commodity->id,
                'ai_data_source_id' => $officialSource->id,
                'price' => '-0.01',
                'currency' => 'PHP',
                'unit' => 'kg',
                'price_type' => 'farmgate',
                'geographic_scope' => 'Cagayan',
                'observation_date' => '2026-10-02',
            ]);
            $this->fail('A negative price should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_seasonality_records_keep_geographic_scope_and_source(): void
    {
        $source = AiDataSource::create([
            'source_name' => 'Official reference fixture',
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'organization' => 'Test organization',
            'is_official' => true,
        ]);
        $commodity = CommodityReference::create(['canonical_name' => 'Rice', 'category' => 'Grains']);

        $season = SeasonalityReference::create([
            'commodity_reference_id' => $commodity->id,
            'ai_data_source_id' => $source->id,
            'month' => 8,
            'season_status' => 'harvest',
            'geographic_scope' => 'Cagayan Valley / Region II',
            'source_reference' => 'Fixture reference identifier',
            'reference_date' => '2026-01-15',
            'verification_status' => 'verified',
        ]);

        $this->assertSame(8, $season->month);
        $this->assertSame('Cagayan Valley / Region II', $season->geographic_scope);
        $this->assertSame('2026-01-15', $season->reference_date->toDateString());
        $this->assertSame(AiDataSource::TYPE_OFFICIAL_REFERENCE, $season->dataSource->source_type);
    }

    public function test_seeder_adds_only_the_universal_kilogram_gram_conversions_idempotently(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        $this->seed(AiReferenceDataSeeder::class);

        $this->assertDatabaseCount('unit_conversion_references', 2);
        $this->assertDatabaseHas('unit_conversion_references', [
            'from_unit' => 'kg',
            'to_unit' => 'g',
            'conversion_factor' => 1000,
            'verification_status' => 'verified',
        ]);
        $this->assertDatabaseHas('unit_conversion_references', [
            'from_unit' => 'g',
            'to_unit' => 'kg',
            'conversion_factor' => 0.001,
            'verification_status' => 'verified',
        ]);

        $reverse = UnitConversionReference::where('from_unit', 'g')->where('to_unit', 'kg')->firstOrFail();
        $this->assertNull($reverse->commodity_reference_id);
        $this->assertStringContainsString('1 kg = 1,000 g', $reverse->source_reference);
    }

    public function test_seeder_registers_psa_openstat_as_an_official_source_idempotently(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        $this->seed(AiReferenceDataSeeder::class);

        $this->assertDatabaseCount('ai_data_sources', 1);
        $source = AiDataSource::where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)->firstOrFail();
        $this->assertSame(AiDataSource::TYPE_OFFICIAL_REFERENCE, $source->source_type);
        $this->assertTrue($source->is_official);
        $this->assertTrue($source->is_active);
        $this->assertSame('Philippine Statistics Authority (PSA)', $source->organization);
        $this->assertStringContainsString('openstat.psa.gov.ph', $source->source_url);
        $this->assertStringContainsString(AiDataSource::PSA_MAJOR_CROPS_TABLE_ID, $source->description);
        $this->assertStringContainsString('not Aparri-specific', $source->description);
        $this->assertDatabaseCount('commodity_references', 28);
        $this->assertDatabaseHas('commodity_references', [
            'canonical_name' => 'Banana Cavendish',
            'normalized_name' => 'banana-cavendish',
            'category' => 'Fruits',
        ]);
    }

    public function test_psa_monthly_import_preserves_source_terms_period_and_region_scope(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        $commodity = CommodityReference::create([
            'canonical_name' => 'Mungbean',
            'category' => 'Grains',
            'aliases' => ['Mongo'],
        ]);
        $row = $this->psaFixtureRow();

        $result = app(PsaMajorCropsImporter::class)->importRows([$row]);
        $price = MarketPriceReference::firstOrFail();

        $this->assertSame(1, $result['created']);
        $this->assertSame('farmgate', $price->price_type);
        $this->assertSame('Peso per kilogram', $price->unit);
        $this->assertSame('Mongo', $price->source_commodity_name);
        $this->assertSame('Region II / Cagayan Valley', $price->geographic_scope);
        $this->assertSame('..REGION II (CAGAYAN VALLEY)', $price->source_geographic_scope);
        $this->assertNotSame('Aparri', $price->geographic_scope);
        $this->assertSame('January 2024', $price->observation_period);
        $this->assertSame('monthly', $price->period_frequency);
        $this->assertSame('2024-01-01', $price->observation_date->toDateString());
        $this->assertSame('preliminary', $price->observation_status);
        $this->assertSame('TEST FIXTURE ONLY - not actual PSA data', $price->source_reference);
        $this->assertSame(AiDataSource::TYPE_OFFICIAL_REFERENCE, $price->dataSource->source_type);
        $this->assertSame($commodity->id, $price->commodity_reference_id);
    }

    public function test_psa_import_is_idempotent_and_updates_revised_observations(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        CommodityReference::create(['canonical_name' => 'Corn', 'category' => 'Grains']);
        $importer = app(PsaMajorCropsImporter::class);
        $row = $this->psaFixtureRow([
            'commodity' => 'Corn',
            'price' => '20.00',
            'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
        ]);

        $first = $importer->importRows([$row]);
        $revised = $importer->importRows([array_merge($row, [
            'price' => '21.00',
            'observation_status' => 'revised',
        ])]);

        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $revised['updated']);
        $this->assertDatabaseCount('market_price_references', 1);
        $this->assertSame('21.0000', MarketPriceReference::firstOrFail()->price);
        $this->assertSame('revised', MarketPriceReference::firstOrFail()->observation_status);
    }

    public function test_missing_values_and_unmapped_commodities_do_not_create_observations(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        $missing = $this->psaFixtureRow(['price' => '..']);
        $unmapped = $this->psaFixtureRow(['commodity' => 'Similar but not an exact mapping']);

        $result = app(PsaMajorCropsImporter::class)->importRows([$missing, $unmapped]);

        $this->assertSame(1, $result['skipped_missing']);
        $this->assertSame(1, $result['skipped_unmapped']);
        $this->assertSame(['Similar but not an exact mapping'], $result['unmapped_commodities']);
        $this->assertDatabaseCount('market_price_references', 0);
    }

    public function test_import_rejects_aparri_scope_and_non_monthly_periods(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        CommodityReference::create(['canonical_name' => 'Corn', 'category' => 'Grains', 'aliases' => ['Mongo']]);
        $importer = app(PsaMajorCropsImporter::class);

        try {
            $importer->importRows([$this->psaFixtureRow(['geographic_scope' => 'Aparri'])]);
            $this->fail('Aparri must not be substituted for the PSA regional scope.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('No geography conversion was applied', $error->getMessage());
        }

        try {
            $importer->importRows([$this->psaFixtureRow(['period' => 'Annual'])]);
            $this->fail('Annual data must not be imported by the monthly major-crops importer.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('not a monthly PSA period', $error->getMessage());
        }

        $this->assertDatabaseCount('market_price_references', 0);
    }

    public function test_psa_csv_command_is_registered_and_supports_a_dry_run(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        CommodityReference::create(['canonical_name' => 'Corn', 'category' => 'Grains']);
        $path = tempnam(sys_get_temp_dir(), 'psa-csv-');
        file_put_contents($path, implode("\n", [
            'Commodity,Geolocation,Year,Period,Value,Unit,Currency,Observation Status,Source Reference',
            'Corn,"..REGION II (CAGAYAN VALLEY)",2024,January,123.45,"Peso per kilogram",PHP,not_stated,TEST FIXTURE ONLY - not actual PSA data',
        ]));

        try {
            $exitCode = Artisan::call('psa:import-major-crops', ['file' => $path, '--dry-run' => true]);
            $output = Artisan::output();
            $this->assertSame(0, $exitCode, $output);
            $this->assertStringContainsString('Dry run only', $output);
            $this->assertDatabaseCount('market_price_references', 0);
        } finally {
            @unlink($path);
        }
    }

    public function test_unit_conversion_factor_must_be_positive(): void
    {
        try {
            UnitConversionReference::create([
                'from_unit' => 'kg',
                'to_unit' => 'sack',
                'conversion_factor' => 0,
                'source_reference' => 'Invalid conversion test fixture',
                'verification_status' => 'verified',
            ]);
            $this->fail('A zero conversion factor should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function psaFixtureRow(array $overrides = []): array
    {
        return array_merge([
            'commodity' => 'Mongo',
            'geographic_scope' => '..REGION II (CAGAYAN VALLEY)',
            'year' => '2024',
            'period' => 'January',
            'price' => '123.45',
            'unit' => 'Peso per kilogram',
            'currency' => 'PHP',
            'observation_status' => 'preliminary',
            'source_reference' => 'TEST FIXTURE ONLY - not actual PSA data',
        ], $overrides);
    }
}
