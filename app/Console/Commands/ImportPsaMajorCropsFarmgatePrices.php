<?php

namespace App\Console\Commands;

use App\Services\Imports\ImportPsaMajorCropsFarmgatePrices as Importer;
use Illuminate\Console\Command;
use Throwable;

class ImportPsaMajorCropsFarmgatePrices extends Command
{
    protected $signature = 'psa:import-major-crops {file : PSA OpenSTAT wide export or Phase 2A flat interchange CSV} {--dry-run : Validate and count rows without writing them}';

    protected $description = 'Import PSA OpenSTAT monthly Major Crops farmgate observations for Region II.';

    public function handle(Importer $importer): int
    {
        try {
            $result = $importer->importCsv($this->argument('file'), (bool) $this->option('dry-run'));
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $rows = [
            ['Created', $result['created']],
            ['Updated / existing-key upserts', $result['updated']],
            ['Skipped missing values (`..` or blank)', $result['skipped_missing']],
            ['Skipped unmapped monthly observations', $result['skipped_unmapped']],
            ['Invalid monthly values', $result['invalid_values'] ?? 0],
        ];

        foreach ([
            'csv_rows_examined' => 'CSV data rows examined',
            'region_ii_rows_found' => 'Region II commodity rows found',
            'monthly_cells_examined' => 'Region II monthly cells examined',
            'numeric_cells_found' => 'Numeric Region II monthly cells',
            'eligible_numeric_observations' => 'Eligible mapped observations',
            'annual_columns_excluded' => 'Annual columns excluded',
            'annual_cells_excluded' => 'Region II annual cells excluded',
        ] as $key => $label) {
            if (array_key_exists($key, $result)) {
                $rows[] = [$label, $result[$key]];
            }
        }

        $this->table(['Result', 'Count'], $rows);

        if (($result['mapped_commodities'] ?? []) !== []) {
            $this->info('Mapped exact PSA commodity labels:');
            foreach ($result['mapped_commodities'] as $commodity) {
                $this->line(' - ' . $commodity);
            }
        }

        if ($result['unmapped_commodities'] !== []) {
            $this->warn('Unmapped PSA commodity labels (skipped; no fuzzy mapping was applied):');
            foreach ($result['unmapped_commodities'] as $commodity) {
                $reason = $result['unmapped_commodity_reasons'][$commodity] ?? 'No exact active commodity mapping.';
                $this->line(' - ' . $commodity . ' — ' . $reason);
            }
        }

        if ($result['dry_run']) {
            $this->comment('Dry run only; no price observations were written.');
        }

        return self::SUCCESS;
    }
}
