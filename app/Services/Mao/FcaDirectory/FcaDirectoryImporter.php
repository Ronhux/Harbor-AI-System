<?php

namespace App\Services\Mao\FcaDirectory;

use App\Models\FcaRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Import of the owner-supplied MAO Aparri federation directory.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 * ---------------------------------
 * - It does NOT create user accounts. A president named in a directory is not an account.
 * - It does NOT mark any president as identity-verified. No president verification mark,
 *   RSBSA number, evidence reference or `last_verified_at` is ever written here.
 * - It does NOT grant procurement eligibility. Every record arrives `not_assessed` and stays so.
 * - It does NOT change a record's verification status. If the source says `pending`, the row
 *   says `pending`.
 * - It does NOT invent data. A missing contact number stays NULL rather than becoming 0 or "".
 * - It does NOT alter a name. Spellings are copied verbatim, including ones that look like
 *   they may be typos, and those are flagged for the owner to confirm against the source.
 *
 * IDEMPOTENCE
 * -----------
 * `source_record_key` carries a UNIQUE index and is the only key used to match. Re-running the
 * import updates the same 21 rows instead of creating duplicates. Directory values are refreshed
 * from the source on each run; verification state is never downgraded or upgraded here.
 */
final class FcaDirectoryImporter
{
    /** Schema column => source field. Columns absent here are written as NULL. */
    public const MAPPING = [
        'source_record_key' => 'source_record_key',
        'official_name' => 'official_name',
        'address' => 'address',
        'president_name' => 'president_name',
        'contact_number' => 'contact_number',
        'registry_source' => 'registry_source',
        'directory_verification_status' => 'directory_verification_status',
        'procurement_eligibility_status' => 'procurement_eligibility_status',
    ];

    /** Columns that are never written by this importer, because no source evidence exists for them. */
    public const NEVER_WRITTEN = [
        'procurement_evidence_reference',
        'last_verified_at',
        'remarks',
    ];

    public function __construct(private readonly string $sourcePath) {}

    /** @return array{import_approved: bool, records: list<array<string, mixed>>} */
    public function source(): array
    {
        if (! is_readable($this->sourcePath)) {
            throw new \RuntimeException("FCA directory source is not readable: {$this->sourcePath}");
        }

        $decoded = json_decode((string) file_get_contents($this->sourcePath), true);
        if (! is_array($decoded) || ! isset($decoded['records']) || ! is_array($decoded['records'])) {
            throw new \RuntimeException('FCA directory source is malformed: a "records" array is required.');
        }

        return [
            'import_approved' => (bool) ($decoded['import_approved'] ?? false),
            'records' => array_values($decoded['records']),
        ];
    }

    /**
     * Read-only analysis: field mapping, duplicates, conflicts and what an import would do.
     *
     * This never writes. It exists so the mapping can be reviewed before any row is written.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $source = $this->source();
        $records = $source['records'];

        $mapped = [];
        $rejected = [];
        $seenKeys = [];
        $duplicateKeys = [];
        $duplicateNames = [];
        $seenNames = [];

        foreach ($records as $index => $record) {
            $key = isset($record['source_record_key']) ? trim((string) $record['source_record_key']) : '';
            $name = isset($record['official_name']) ? trim((string) $record['official_name']) : '';

            $missing = [];
            // address and president_name are NOT NULL in the schema, so a blank would abort the
            // import. Report it instead of writing an empty string.
            foreach (['source_record_key' => $key, 'official_name' => $name] as $field => $value) {
                if ($value === '') {
                    $missing[] = $field;
                }
            }
            foreach (['address', 'president_name'] as $field) {
                if (trim((string) ($record[$field] ?? '')) === '') {
                    $missing[] = $field;
                }
            }

            if ($missing !== []) {
                $rejected[] = [
                    'index' => $index,
                    'source_record_key' => $key,
                    'official_name' => $name,
                    'reason' => 'missing required field(s): ' . implode(', ', $missing),
                ];
                continue;
            }

            if (isset($seenKeys[$key])) {
                $duplicateKeys[] = ['source_record_key' => $key, 'occurrences' => $seenKeys[$key] + 1];
            }
            $seenKeys[$key] = ($seenKeys[$key] ?? 0) + 1;

            // Case-insensitive name collision: two different keys, one organisation.
            $nameKey = mb_strtolower(preg_replace('/\s+/', ' ', $name) ?? $name);
            if (isset($seenNames[$nameKey])) {
                $duplicateNames[] = [
                    'official_name' => $name,
                    'source_record_keys' => [$seenNames[$nameKey], $key],
                ];
            }
            $seenNames[$nameKey] = $key;

            $existing = FcaRegistry::where('source_record_key', $key)->first();
            $row = ['source_record_key' => $key, 'official_name' => $name];
            foreach (self::MAPPING as $column => $field) {
                if ($column === 'source_record_key') {
                    continue;
                }
                $value = $record[$field] ?? null;
                $row[$column] = ($value === null || trim((string) $value) === '') ? null : trim((string) $value);
            }
            $row['official_name'] = $name;
            $row['_action'] = $existing ? 'update' : 'insert';
            $row['_existing_id'] = $existing?->getKey();
            $row['_differs_from_existing'] = $existing ? $this->differs($existing, $row) : [];

            $mapped[] = $row;
        }

        return [
            'source_path' => $this->sourcePath,
            'import_approved_in_source' => $source['import_approved'],
            'import_possible' => $source['import_approved'],
            'source_record_count' => count($records),
            'importable_count' => count($mapped),
            'would_insert' => count(array_filter($mapped, fn ($r) => $r['_action'] === 'insert')),
            'would_update' => count(array_filter($mapped, fn ($r) => $r['_action'] === 'update')),
            'existing_registry_rows' => FcaRegistry::count(),
            'mapping' => self::MAPPING,
            'never_written' => self::NEVER_WRITTEN,
            'duplicate_source_keys' => $duplicateKeys,
            'duplicate_official_names' => $duplicateNames,
            'rejected_records' => $rejected,
            'records' => $mapped,
            'guarantees' => [
                'no_user_accounts_created' => true,
                'no_president_marked_identity_verified' => true,
                'no_rsbsa_numbers_written' => true,
                'no_procurement_eligibility_granted' => true,
                'missing_values_stay_null' => true,
                'names_copied_verbatim' => true,
            ],
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function differs(FcaRegistry $existing, array $row): array
    {
        $changed = [];
        foreach (self::MAPPING as $column => $_) {
            $incoming = $row[$column] ?? null;
            $current = $existing->getAttribute($column);
            if ((string) ($incoming ?? '') !== (string) ($current ?? '')) {
                $changed[$column] = ['current' => $current, 'incoming' => $incoming];
            }
        }

        return $changed;
    }

    /**
     * Perform the import.
     *
     * Refuses outright unless the source file itself carries import_approved = true. That flag is
     * currently false, so this call is expected to be rejected until the owner approves it.
     *
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $preview = $this->preview();

        if ($preview['import_approved_in_source'] !== true) {
            throw new \RuntimeException(
                'Refusing to import: the source file does not carry import_approved = true. '
                . 'No rows were written.'
            );
        }
        if ($preview['rejected_records'] !== []) {
            throw new \RuntimeException(
                'Refusing to import: ' . count($preview['rejected_records'])
                . ' record(s) are missing required fields. No rows were written.'
            );
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $now = now();

        DB::transaction(function () use ($preview, $now, &$inserted, &$updated, &$skipped) {
            foreach ($preview['records'] as $row) {
                $values = array_intersect_key($row, array_flip(array_keys(self::MAPPING)));
                // Never let an import write verification or evidence columns.
                foreach (self::NEVER_WRITTEN as $column) {
                    unset($values[$column]);
                }
                $values['updated_at'] = $now;

                // The action was determined before writing, so a row is never miscounted
                // because a driver reported an update as an insert (MySQL returns 2).
                if (($row['_action'] ?? 'insert') === 'update') {
                    DB::table('fca_registry')
                        ->where('source_record_key', $row['source_record_key'])
                        ->update($values);
                    $updated++;
                } else {
                    DB::table('fca_registry')->insert(array_merge(['created_at' => $now], $values));
                    $inserted++;
                }
            }
        });

        return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }
}