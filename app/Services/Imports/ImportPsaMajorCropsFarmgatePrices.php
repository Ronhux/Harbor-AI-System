<?php

namespace App\Services\Imports;

use App\Models\AiDataSource;
use App\Models\CommodityReference;
use App\Models\MarketPriceReference;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ImportPsaMajorCropsFarmgatePrices
{
    public const GEOGRAPHIC_SCOPE = 'Region II / Cagayan Valley';
    public const SOURCE_GEOGRAPHIC_LABEL = 'REGION II (CAGAYAN VALLEY)';
    public const PSA_UNIT = 'Peso per kilogram';
    public const PSA_CURRENCY = 'PHP';

    private const MONTHS = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
    ];

    /**
     * Import either the Phase 2A flat interchange CSV or the actual PSA wide export.
     * The wide export's title row, monthly headers, annual headers, and hierarchy labels
     * are handled explicitly; only Region II monthly cells are sent to importRows().
     *
     * @return array{created:int,updated:int,skipped_missing:int,skipped_unmapped:int,unmapped_commodities:array, dry_run:bool}
     */
    public function importCsv(string $path, bool $dryRun = false): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("The PSA CSV file is not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open the PSA CSV file: {$path}");
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new InvalidArgumentException('The PSA CSV file is empty.');
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $firstRecord = fgetcsv($handle, 0, $delimiter);
            if ($firstRecord === false) {
                throw new InvalidArgumentException('The PSA CSV header could not be read.');
            }

            if ($this->isOpenStatTitleRow($firstRecord)) {
                $headers = fgetcsv($handle, 0, $delimiter);
                if ($headers === false) {
                    throw new InvalidArgumentException('The PSA OpenSTAT CSV is missing its column header row.');
                }

                $headers = $this->normalizeWideHeaders($headers);
                $fixtureOnly = str_starts_with(
                    mb_strtoupper(trim((string) ($firstRecord[0] ?? ''))),
                    'TEST FIXTURE ONLY'
                );
                return $this->importWideRows($handle, $headers, $delimiter, $dryRun, $fixtureOnly, basename($path));
            }

            $headers = $this->normalizeHeaders($firstRecord);
            if (count(array_unique($headers)) !== count($headers)) {
                throw new InvalidArgumentException('The PSA CSV contains duplicate column names after normalization.');
            }
            $required = ['commodity', 'geographic_scope', 'year', 'period', 'price', 'unit', 'currency'];
            $missing = array_values(array_diff($required, $headers));
            if ($missing !== []) {
                throw new InvalidArgumentException('The PSA CSV is missing required columns: ' . implode(', ', $missing));
            }

            return $this->importRows($this->csvRows($handle, $headers, $delimiter), $dryRun);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Import rows with the same field names as the CSV interchange format.
     *
     * @param iterable<array<string, mixed>> $rows
     * @return array{created:int,updated:int,skipped_missing:int,skipped_unmapped:int,unmapped_commodities:array, dry_run:bool}
     */
    public function importRows(iterable $rows, bool $dryRun = false): array
    {
        $source = AiDataSource::query()
            ->where('source_type', AiDataSource::TYPE_OFFICIAL_REFERENCE)
            ->where('source_name', AiDataSource::SOURCE_PSA_MAJOR_CROPS)
            ->where('is_active', true)
            ->first();

        if (!$source) {
            throw new RuntimeException('PSA OpenSTAT source is not registered. Run AiReferenceDataSeeder before importing.');
        }

        $commodityIndex = $this->buildCommodityIndex();

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped_missing' => 0,
            'skipped_unmapped' => 0,
            'unmapped_commodities' => [],
            'unmapped_commodity_reasons' => [],
            'mapped_commodities' => [],
            'unmapped_observations' => 0,
            'dry_run' => $dryRun,
        ];
        $retrievedAt = now();

        return DB::transaction(function () use ($rows, $source, $commodityIndex, $dryRun, $retrievedAt, $result): array {
            foreach ($rows as $index => $row) {
                if (!empty($row['_invalid_value'])) {
                    $result['invalid_values'] = ($result['invalid_values'] ?? 0) + 1;
                    $result['invalid_value_details'][] = [
                        'row' => $row['_source_row'] ?? $index,
                        'commodity' => $row['commodity'] ?? null,
                        'period' => ($row['period'] ?? '') . ' ' . ($row['year'] ?? ''),
                        'value' => $row['price'] ?? null,
                    ];
                    continue;
                }

                $rawPrice = trim((string) ($row['price'] ?? ''));
                if ($rawPrice === '' || $rawPrice === '..') {
                    $result['skipped_missing']++;
                    continue;
                }

                $sourceCommodity = $this->requiredString($row, 'commodity', $index);
                $sourceGeography = $this->requiredString($row, 'geographic_scope', $index);
                $geographyLabel = $this->stripOpenStatHierarchy($sourceGeography);
                if (mb_strtoupper($geographyLabel) !== self::SOURCE_GEOGRAPHIC_LABEL) {
                    throw new InvalidArgumentException(
                        "Row {$index}: expected PSA geography " . self::SOURCE_GEOGRAPHIC_LABEL
                        . "; received '{$sourceGeography}'. No geography conversion was applied."
                    );
                }

                $commodity = $this->resolveCommodity($sourceCommodity, $commodityIndex);
                if (!$commodity) {
                    $result['skipped_unmapped']++;
                    $result['unmapped_commodities'][] = $sourceCommodity;
                    $result['unmapped_commodity_reasons'][$sourceCommodity] = $this->commodityMappingFailureReason(
                        $sourceCommodity,
                        $commodityIndex
                    );
                    $result['unmapped_observations']++;
                    continue;
                }

                $result['mapped_commodities'][] = $sourceCommodity;

                [$period, $observationDate] = $this->parseMonth(
                    $this->requiredString($row, 'period', $index),
                    $this->requiredString($row, 'year', $index),
                    $index
                );

                $price = $this->parsePrice($rawPrice, $index);
                $unit = $this->requiredString($row, 'unit', $index);
                $currency = $this->requiredString($row, 'currency', $index);
                if (strlen($currency) !== 3) {
                    throw new InvalidArgumentException("Row {$index}: currency must be a three-letter code.");
                }

                $observationStatus = $this->parseObservationStatus($row['observation_status'] ?? 'not_stated', $index);
                $deduplicationKey = hash('sha256', implode("\x1f", [
                    $source->getKey(),
                    $commodity->getKey(),
                    mb_strtolower($geographyLabel),
                    'monthly',
                    $observationDate,
                    mb_strtolower($unit),
                    mb_strtoupper($currency),
                    'farmgate',
                ]));

                $values = [
                    'commodity_reference_id' => $commodity->getKey(),
                    'ai_data_source_id' => $source->getKey(),
                    'price' => $price,
                    'currency' => mb_strtoupper($currency),
                    'unit' => $unit,
                    'price_type' => 'farmgate',
                    'geographic_scope' => self::GEOGRAPHIC_SCOPE,
                    'source_url' => $this->optionalString($row['source_url'] ?? null) ?? $source->source_url,
                    'source_reference' => $this->optionalString($row['source_reference'] ?? null)
                        ?? 'PSA OpenSTAT table ' . AiDataSource::PSA_MAJOR_CROPS_TABLE_ID,
                    'source_commodity_name' => $sourceCommodity,
                    'source_geographic_scope' => $sourceGeography,
                    'observation_date' => $observationDate,
                    'observation_period' => $period,
                    'period_frequency' => 'monthly',
                    'observation_status' => $observationStatus,
                    'source_retrieved_at' => $retrievedAt,
                    'deduplication_key' => $deduplicationKey,
                    'verification_status' => 'verified',
                ];

                $existing = MarketPriceReference::query()
                    ->where('deduplication_key', $deduplicationKey)
                    ->first();

                if ($dryRun) {
                    $result[$existing ? 'updated' : 'created']++;
                    continue;
                }

                if ($existing) {
                    $existing->fill($values)->save();
                    $result['updated']++;
                } else {
                    MarketPriceReference::create($values);
                    $result['created']++;
                }
            }

            $result['unmapped_commodities'] = array_values(array_unique($result['unmapped_commodities']));
            $result['mapped_commodities'] = array_values(array_unique($result['mapped_commodities']));

            return $result;
        });
    }

    /**
     * Read the actual wide OpenSTAT export without treating annual figures as monthly prices.
     * The source file has 2,000+ cells per data row, so rows are streamed into the shared
     * Phase 2A observation importer instead of creating a second persistence path.
     *
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function importWideRows(
        $handle,
        array $headers,
        string $delimiter,
        bool $dryRun,
        bool $fixtureOnly,
        string $snapshotName
    ): array
    {
        if (count($headers) < 3 || $headers[0] !== 'commodity' || $headers[1] !== 'geographic_scope') {
            throw new InvalidArgumentException('The PSA OpenSTAT export must start with Commodity and Geolocation columns.');
        }
        if (count(array_unique($headers)) !== count($headers)) {
            throw new InvalidArgumentException('The PSA OpenSTAT export contains duplicate column headers.');
        }

        $monthlyColumns = [];
        $annualColumns = 0;
        foreach (array_slice($headers, 2, null, true) as $columnIndex => $header) {
            if (!preg_match('/^(\d{4})\s+(January|February|March|April|May|June|July|August|September|October|November|December|Annual)$/i', $header, $matches)) {
                throw new InvalidArgumentException("Unsupported PSA OpenSTAT period column '{$header}'.");
            }

            if (mb_strtolower($matches[2]) === 'annual') {
                $annualColumns++;
                continue;
            }

            $period = ucfirst(mb_strtolower($matches[2]));
            $monthlyColumns[] = [
                'index' => $columnIndex,
                'year' => $matches[1],
                'period' => $period,
            ];
        }

        if ($monthlyColumns === []) {
            throw new InvalidArgumentException('The PSA OpenSTAT export contains no monthly observation columns.');
        }

        $stats = [
            'csv_rows_examined' => 0,
            'region_ii_rows_found' => 0,
            'monthly_cells_examined' => 0,
            'annual_columns_excluded' => $annualColumns,
            'annual_cells_excluded' => 0,
            'numeric_cells_found' => 0,
            'invalid_values' => 0,
            'invalid_value_details' => [],
        ];

        $rows = (function () use ($handle, $headers, $delimiter, $monthlyColumns, $fixtureOnly, $snapshotName, &$stats): iterable {
            $line = 2; // The first row is the PSA title and the second row is the header.
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new InvalidArgumentException("CSV row {$line} has a different number of columns than the header.");
                }

                $stats['csv_rows_examined']++;
                $sourceCommodity = trim((string) ($values[0] ?? ''));
                $sourceGeography = trim((string) ($values[1] ?? ''));
                if ($sourceCommodity === '' || $sourceGeography === '') {
                    throw new InvalidArgumentException("CSV row {$line} is missing a commodity or geolocation label.");
                }
                if (mb_strtoupper($this->stripOpenStatHierarchy($sourceGeography)) !== self::SOURCE_GEOGRAPHIC_LABEL) {
                    continue;
                }

                $stats['region_ii_rows_found']++;
                $stats['monthly_cells_examined'] += count($monthlyColumns);
                $stats['annual_cells_excluded'] += $stats['annual_columns_excluded'];

                foreach ($monthlyColumns as $column) {
                    $rawPrice = trim((string) ($values[$column['index']] ?? ''));
                    if ($rawPrice !== '' && $rawPrice !== '..') {
                        if (!$this->isSupportedNumericPrice($rawPrice)) {
                            $stats['invalid_values']++;
                            $stats['invalid_value_details'][] = [
                                'row' => $line,
                                'commodity' => $sourceCommodity,
                                'period' => $column['period'] . ' ' . $column['year'],
                                'value' => $rawPrice,
                            ];
                        } else {
                            $stats['numeric_cells_found']++;
                        }
                    }

                    yield [
                        'commodity' => $sourceCommodity,
                        'geographic_scope' => $sourceGeography,
                        'year' => $column['year'],
                        'period' => $column['period'],
                        'price' => $rawPrice,
                        'unit' => self::PSA_UNIT,
                        'currency' => self::PSA_CURRENCY,
                        'observation_status' => 'not_stated',
                        'source_reference' => ($fixtureOnly ? 'TEST FIXTURE ONLY - ' : '')
                            . 'PSA OpenSTAT table ' . AiDataSource::PSA_MAJOR_CROPS_TABLE_ID
                            . ' / 2M4EFGP0; snapshot ' . $snapshotName,
                        'source_url' => 'https://openstat.psa.gov.ph/Metadata/Prices/Farmgate-Prices',
                        '_invalid_value' => $rawPrice !== '' && $rawPrice !== '..'
                            && !$this->isSupportedNumericPrice($rawPrice),
                        '_source_row' => $line,
                    ];
                }
            }
        })();

        // Invalid cells are counted during dry-run but never written. A real import fails closed
        // before any observation is persisted if the official snapshot contains malformed values.
        if ($dryRun) {
            $result = $this->importRows($rows, true);
        } else {
            $validatedRows = [];
            foreach ($rows as $row) {
                if ($row['price'] !== '' && $row['price'] !== '..' && !$this->isSupportedNumericPrice($row['price'])) {
                    continue;
                }
                $validatedRows[] = $row;
            }
            if ($stats['invalid_values'] > 0) {
                throw new InvalidArgumentException(
                    'The PSA CSV contains ' . $stats['invalid_values'] . ' invalid monthly values; no observations were imported.'
                );
            }
            $result = $this->importRows($validatedRows, false);
        }

        $result['csv_rows_examined'] = $stats['csv_rows_examined'];
        $result['region_ii_rows_found'] = $stats['region_ii_rows_found'];
        $result['monthly_cells_examined'] = $stats['monthly_cells_examined'];
        $result['annual_columns_excluded'] = $stats['annual_columns_excluded'];
        $result['annual_cells_excluded'] = $stats['annual_cells_excluded'];
        $result['numeric_cells_found'] = $stats['numeric_cells_found'];
        $result['invalid_values'] = $stats['invalid_values'];
        $result['invalid_value_details'] = $stats['invalid_value_details'];
        $result['eligible_numeric_observations'] = $result['created'] + $result['updated'];

        return $result;
    }

    private function isOpenStatTitleRow(array $record): bool
    {
        if (count($record) !== 1) {
            return false;
        }

        $title = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $record[0]));
        $title = preg_replace('/^TEST FIXTURE ONLY\s*-\s*/i', '', $title);

        return str_starts_with(mb_strtolower($title), 'major crops: farmgate prices, by region, monthly');
    }

    /** @return array<int, string> */
    private function normalizeWideHeaders(array $headers): array
    {
        return array_map(static function ($header, int $index): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)) ?? trim((string) $header);
            if ($index === 0 || $index === 1) {
                return match (Str::snake($header)) {
                    'commodity' => 'commodity',
                    'geolocation', 'geographic_scope' => 'geographic_scope',
                    default => Str::snake($header),
                };
            }

            return $header;
        }, $headers, array_keys($headers));
    }

    private function isSupportedNumericPrice(string $rawPrice): bool
    {
        $valid = preg_match('/^\d+(?:\.\d+)?$/', $rawPrice)
            || preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $rawPrice);
        if (!$valid) {
            return false;
        }

        $price = str_replace(',', '', $rawPrice);
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return strlen($whole) <= 10 && strlen($fraction) <= 4;
    }

    /** @param array<string, array<int, CommodityReference>> $index */
    private function commodityMappingFailureReason(string $sourceName, array $index): string
    {
        $matches = $index[mb_strtolower(trim($sourceName))] ?? [];

        return count($matches) > 1
            ? 'More than one active CommodityReference has this exact canonical name or reviewed alias.'
            : 'No active CommodityReference has this exact canonical name or an explicitly reviewed exact alias.';
    }

    /** @return array<string> */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header));
            $normalized = Str::snake($header);

            return match ($normalized) {
                'geolocation' => 'geographic_scope',
                'value', 'price_value' => 'price',
                'source_status' => 'observation_status',
                default => $normalized,
            };
        }, $headers);
    }

    /** @return iterable<array<string, string|null>> */
    private function csvRows($handle, array $headers, string $delimiter): iterable
    {
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($values) !== count($headers)) {
                throw new InvalidArgumentException("CSV row {$line} has a different number of columns than the header.");
            }

            yield array_combine($headers, $values);
        }
    }

    /** @return array<string, array<int, CommodityReference>> */
    private function buildCommodityIndex(): array
    {
        $index = [];

        foreach (CommodityReference::query()->where('is_active', true)->get() as $commodity) {
            $names = [$commodity->canonical_name, ...($commodity->aliases ?? [])];
            foreach ($names as $name) {
                $exactKey = mb_strtolower(trim((string) $name));
                if ($exactKey !== '') {
                    $index[$exactKey][$commodity->getKey()] = $commodity;
                }
            }
        }

        return $index;
    }

    /** @param array<string, array<int, CommodityReference>> $index */
    private function resolveCommodity(string $sourceName, array $index): ?CommodityReference
    {
        $matches = $index[mb_strtolower(trim($sourceName))] ?? [];

        return count($matches) === 1 ? reset($matches) : null;
    }

    private function parseMonth(string $month, string $year, int|string $row): array
    {
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new InvalidArgumentException("Row {$row}: year must contain four digits.");
        }

        $monthNumber = array_search(mb_strtolower($month), self::MONTHS, true);
        if ($monthNumber === false) {
            throw new InvalidArgumentException("Row {$row}: '{$month}' is not a monthly PSA period; annual or quarterly data is not imported.");
        }

        $observationDate = sprintf('%04d-%02d-01', (int) $year, $monthNumber + 1);
        if (!checkdate($monthNumber + 1, 1, (int) $year)) {
            throw new InvalidArgumentException("Row {$row}: invalid monthly observation date.");
        }

        return [trim($month) . ' ' . $year, $observationDate];
    }

    private function parsePrice(string $rawPrice, int|string $row): string
    {
        $valid = preg_match('/^\d+(?:\.\d+)?$/', $rawPrice)
            || preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $rawPrice);

        if (!$valid) {
            throw new InvalidArgumentException("Row {$row}: price '{$rawPrice}' is not a supported numeric value.");
        }

        $price = str_replace(',', '', $rawPrice);
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        if (strlen($whole) > 10 || strlen($fraction) > 4) {
            throw new InvalidArgumentException("Row {$row}: price exceeds the supported precision of 10 integer and 4 decimal digits.");
        }
        return $whole . ($fraction === '' ? '' : '.' . $fraction);
    }

    private function parseObservationStatus(mixed $status, int|string $row): string
    {
        $status = Str::lower(trim((string) $status));
        $statuses = MarketPriceReference::OBSERVATION_STATUSES;

        if ($status === '') {
            $status = 'not_stated';
        }
        if (!in_array($status, $statuses, true)) {
            throw new InvalidArgumentException("Row {$row}: unsupported observation status '{$status}'.");
        }

        return $status;
    }

    private function requiredString(array $row, string $key, int|string $index): string
    {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException("Row {$index}: '{$key}' is required.");
        }

        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function stripOpenStatHierarchy(string $geography): string
    {
        return preg_replace('/^\s*\.*\s*/u', '', trim($geography)) ?? trim($geography);
    }
}
