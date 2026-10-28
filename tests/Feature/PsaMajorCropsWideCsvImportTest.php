<?php

namespace Tests\Feature;

use App\Models\MarketPriceReference;
use App\Services\Imports\ImportPsaMajorCropsFarmgatePrices as PsaMajorCropsImporter;
use Database\Seeders\AiReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PsaMajorCropsWideCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_wide_psa_fixture_imports_only_exactly_mapped_region_ii_months_and_preserves_zero(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        $path = $this->writeWideFixture([
            ['Ampalaya fruit [Bitter gourd]', '..REGION II (CAGAYAN VALLEY)', '0.00', '..', '999.00', '90.10'],
            ['Ampalaya fruit [Bitter gourd]', 'PHILIPPINES', '10.00', '10.00', '10.00', '10.00'],
            ['Ampalaya fruit Bitter gourd', '..REGION II (CAGAYAN VALLEY)', '12.00', '', '999.00', '..'],
        ]);

        try {
            $importer = app(PsaMajorCropsImporter::class);
            $dryRun = $importer->importCsv($path, true);

            $this->assertSame(3, $dryRun['csv_rows_examined']);
            $this->assertSame(2, $dryRun['region_ii_rows_found']);
            $this->assertSame(6, $dryRun['monthly_cells_examined']);
            $this->assertSame(1, $dryRun['annual_columns_excluded']);
            $this->assertSame(2, $dryRun['annual_cells_excluded']);
            $this->assertSame(3, $dryRun['numeric_cells_found']);
            $this->assertSame(2, $dryRun['eligible_numeric_observations']);
            $this->assertSame(3, $dryRun['skipped_missing']);
            $this->assertSame(1, $dryRun['skipped_unmapped']);
            $this->assertSame(['Ampalaya fruit Bitter gourd'], $dryRun['unmapped_commodities']);
            $this->assertSame(0, $dryRun['invalid_values']);
            $this->assertDatabaseCount('market_price_references', 0);

            $import = $importer->importCsv($path);
            $this->assertSame(2, $import['created']);
            $this->assertDatabaseCount('market_price_references', 2);

            $zero = MarketPriceReference::whereDate('observation_date', '2024-01-01')->firstOrFail();
            $laterYear = MarketPriceReference::whereDate('observation_date', '2025-01-01')->firstOrFail();

            $this->assertSame('0.0000', $zero->price);
            $this->assertSame('90.1000', $laterYear->price);
            $this->assertSame('Peso per kilogram', $zero->unit);
            $this->assertSame('PHP', $zero->currency);
            $this->assertSame('farmgate', $zero->price_type);
            $this->assertSame('Ampalaya fruit [Bitter gourd]', $zero->source_commodity_name);
            $this->assertSame('..REGION II (CAGAYAN VALLEY)', $zero->source_geographic_scope);
            $this->assertSame('Region II / Cagayan Valley', $zero->geographic_scope);
            $this->assertNotSame('Aparri', $zero->geographic_scope);
            $this->assertStringContainsString('TEST FIXTURE ONLY', $zero->source_reference);
            $this->assertSame($zero->ai_data_source_id, $zero->dataSource->id);
            $this->assertSame('January 2024', $zero->observation_period);
            $this->assertSame('monthly', $zero->period_frequency);
            $this->assertSame(0, MarketPriceReference::where('observation_period', 'Annual 2024')->count());

            $rerun = $importer->importCsv($path);
            $this->assertSame(0, $rerun['created']);
            $this->assertSame(2, $rerun['updated']);
            $this->assertDatabaseCount('market_price_references', 2);
        } finally {
            @unlink($path);
        }
    }

    public function test_wide_import_reports_bad_cells_in_dry_run_and_aborts_real_import(): void
    {
        $this->seed(AiReferenceDataSeeder::class);
        $path = $this->writeWideFixture([
            ['Ampalaya fruit [Bitter gourd]', '..REGION II (CAGAYAN VALLEY)', 'not a number', '..', '7.00', '8.00'],
        ]);

        try {
            $importer = app(PsaMajorCropsImporter::class);
            $dryRun = $importer->importCsv($path, true);
            $this->assertSame(1, $dryRun['invalid_values']);
            $this->assertSame(1, $dryRun['eligible_numeric_observations']);
            $this->assertDatabaseCount('market_price_references', 0);

            try {
                $importer->importCsv($path);
                $this->fail('A malformed source cell must stop the real import before writing rows.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('no observations were imported', mb_strtolower($error->getMessage()));
            }

            $this->assertDatabaseCount('market_price_references', 0);
        } finally {
            @unlink($path);
        }
    }

    /** @param array<int, array<int, string>> $rows */
    private function writeWideFixture(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'psa-wide-fixture-');
        if ($path === false) {
            $this->fail('Unable to allocate a temporary test fixture.');
        }

        $lines = [
            '"TEST FIXTURE ONLY - Major Crops: Farmgate Prices, by Region, Monthly, 2010-2026"',
            'Commodity,Geolocation,2024 January,2024 February,2024 Annual,2025 January',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(static function (string $value): string {
                return str_contains($value, ',') || str_contains($value, '"')
                    ? '"' . str_replace('"', '""', $value) . '"'
                    : $value;
            }, $row));
        }

        file_put_contents($path, implode("\n", $lines) . "\n");

        return $path;
    }
}
