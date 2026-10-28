<?php

namespace App\Services\Seasonality;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Read-only accessor for the owner-supplied seasonality dataset.
 *
 * WHY READ-ONLY CSV INSTEAD OF A TABLE
 * ------------------------------------
 * The supplied file is the authoritative source. Loading it directly guarantees the
 * application's wording can never drift from the owner's file, needs no database
 * migration or privilege, and cannot be mutated by a stray write. It is also inherently
 * idempotent: the same file always yields the same 35 records, with no insert-duplicate
 * risk. seasonality_references exists in the database but is empty; if you later want the
 * data in SQL, it can be imported from this same reader without changing any consumer.
 *
 * WHAT THIS IS NOT
 * ----------------
 * Every record is a DERIVED SEASONAL INDICATOR derived from published regional and
 * national reference material. It is not measured Aparri production, not a verified local
 * harvest peak, and contains no market price. All 35 rows carry
 * record_classification = "Secondary-source-derived illustrative signal" and
 * local_validation_status = "Not validated as Aparri seasonal production by MAO".
 * Those values are preserved verbatim and surfaced to the user.
 *
 * Geographic honesty: the dataset is Aparri-referenced but derived from Cagayan Valley /
 * national sources. A national crop calendar is never relabelled as Aparri-verified.
 */
final class SeasonalityReferenceService
{
    /** Month names the dataset can legitimately answer for. Structure allows all 12. */
    public const SUPPORTED_MONTHS = [7, 8, 9, 10, 11];

    /**
     * Explicit commodity aliases, dataset name => HarborAI canonical commodity names.
     * Only listed when the mapping is unambiguous. Anything absent resolves to
     * "no seasonality match" rather than a guess. Multiple values mean the dataset row is
     * genuinely ambiguous for HarborAI and is reported as such instead of being picked.
     */
    private const COMMODITY_ALIASES = [
        'ampalaya' => ['Ampalaya fruit [Bitter gourd]'],
        'mais (grain corn)' => [],
        'palay' => [],
        'talong' => ['Eggplant, long, purple', 'Eggplant, native, long', 'Eggplant, native, round'],
        'patola' => [],
        'sitaw' => [],
        'kalabasa' => [],
    ];

    private ?array $rows = null;

    private ?array $index = null;

    public function __construct(private readonly ?string $path = null) {}

    private function path(): string
    {
        return $this->path ?? base_path('docs/datasets/harborai_seasonality_july_november_derived.csv');
    }

    /** Normalises a commodity label for lookup without changing the source meaning. */
    public static function normalize(string $commodity): string
    {
        $s = mb_strtolower(trim($commodity));
        $s = str_replace(['(', ')', ',', '.', '"', "'"], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }

    /** @return SeasonalityReference[] */
    private function rows(): array
    {
        if ($this->rows !== null) return $this->rows;

        $file = $this->path();
        if (!is_readable($file)) {
            Log::warning('Seasonality dataset is not readable.', ['path' => $file]);
            return $this->rows = [];
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) return $this->rows = [];

        $header = fgetcsv($handle);
        if ($header === false) { fclose($handle); return $this->rows = []; }
        $header = array_map(static fn ($h) => trim((string) $h), $header);

        $required = ['month_number', 'commodity', 'seasonal_activity', 'record_classification',
            'local_validation_status', 'possible_harvest_window', 'named_planting_window'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                fclose($handle);
                throw new RuntimeException("Seasonality dataset is missing required column '{$column}'.");
            }
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || count(array_filter($line, static fn ($v) => $v !== null && $v !== '')) === 0) continue;
            $record = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
            if ($record === false) continue;

            $month = (int) $record['month_number'];
            if ($month < 1 || $month > 12) continue;

            $rainfall = trim((string) $record['aparri_rainfall_normal_mm']);
            $rows[] = new SeasonalityReference(
                recordId: (string) $record['record_id'],
                monthNumber: $month,
                monthName: (string) $record['month'],
                commodity: (string) $record['commodity'],
                category: (string) $record['commodity_category'],
                plantingWindow: (string) $record['named_planting_window'],
                harvestWindow: (string) $record['possible_harvest_window'],
                seasonalActivity: (string) $record['seasonal_activity'],
                rainfallNormalMm: is_numeric($rainfall) ? (float) $rainfall : null,
                rainfallContext: (string) $record['rainfall_context'],
                cropBasisNote: (string) $record['crop_basis_note'],
                interpretationSafeguard: (string) $record['interpretation_safeguard'],
                sourceIds: (string) $record['source_ids'],
                primarySourceUrl: (string) $record['primary_source_url'],
                classification: (string) $record['record_classification'],
                localValidationStatus: (string) $record['local_validation_status'],
            );
        }
        fclose($handle);

        return $this->rows = $rows;
    }

    /** @return array<string, SeasonalityReference> keyed "normalizedCommodity|month" */
    private function index(): array
    {
        if ($this->index !== null) return $this->index;

        $index = [];
        foreach ($this->rows() as $row) {
            $index[self::normalize($row->commodity) . '|' . $row->monthNumber] = $row;
        }

        return $this->index = $index;
    }

    /**
     * The HarborAI canonical names this dataset row could correspond to.
     * Empty array = no unambiguous mapping exists.
     *
     * @return string[]
     */
    public function canonicalCandidatesFor(string $datasetCommodity): array
    {
        return self::COMMODITY_ALIASES[self::normalize($datasetCommodity)] ?? [];
    }

    /**
     * Resolve a HarborAI commodity name to a dataset commodity, only when unambiguous.
     * Returns null when the caller must not be told the commodity has a seasonal record.
     */
    public function datasetCommodityFor(string $canonicalName): ?string
    {
        $target = self::normalize($canonicalName);
        foreach (self::COMMODITY_ALIASES as $datasetKey => $canonicals) {
            if ($canonicals === []) continue;
            foreach ($canonicals as $candidate) {
                if (self::normalize($candidate) === $target) return $datasetKey;
            }
        }

        return null;
    }

    /** True when the dataset row maps to more than one HarborAI commodity. */
    public function isAmbiguous(string $datasetCommodity): bool
    {
        return count($this->canonicalCandidatesFor($datasetCommodity)) > 1;
    }

    /**
     * Exact dataset lookup. Returns null rather than a default row when the
     * commodity/month combination is absent, so callers can say "no reference available".
     */
    public function find(string $datasetCommodity, int $monthNumber): ?SeasonalityReference
    {
        return $this->index()[self::normalize($datasetCommodity) . '|' . $monthNumber] ?? null;
    }

    public function findForCanonical(string $canonicalName, int $monthNumber): ?SeasonalityReference
    {
        $key = $this->datasetCommodityFor($canonicalName);

        return $key === null ? null : $this->find($key, $monthNumber);
    }

    /** @return SeasonalityReference[] */
    public function all(): array
    {
        return $this->rows();
    }

    public function count(): int
    {
        return count($this->rows());
    }

    /** @return string[] */
    public function commodities(): array
    {
        return array_values(array_unique(array_map(static fn (SeasonalityReference $r) => $r->commodity, $this->rows())));
    }

    /** True when the dataset can legitimately answer for this month at all. */
    public function coversMonth(int $monthNumber): bool
    {
        return in_array($monthNumber, self::SUPPORTED_MONTHS, true);
    }

    /** All months the dataset structurally supports, for honest coverage messaging. */
    public static function supportedMonthNumbers(): array
    {
        return range(1, 12);
    }
}