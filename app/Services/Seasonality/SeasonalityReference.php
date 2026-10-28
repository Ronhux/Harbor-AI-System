<?php

namespace App\Services\Seasonality;

/**
 * One derived seasonal indicator exactly as it appears in the supplied dataset.
 *
 * This is NOT a verified Aparri harvest measurement. Every field below is carried through
 * verbatim from the source row so the application's wording can never drift from the data.
 * In particular $classification and $localValidationStatus are preserved so the product
 * can say "inferred" and "not validated by MAO" rather than implying a confirmed season.
 */
final class SeasonalityReference
{
    public function __construct(
        public readonly string $recordId,
        public readonly int $monthNumber,
        public readonly string $monthName,
        public readonly string $commodity,
        public readonly string $category,
        public readonly string $plantingWindow,
        public readonly string $harvestWindow,
        public readonly string $seasonalActivity,
        public readonly ?float $rainfallNormalMm,
        public readonly string $rainfallContext,
        public readonly string $cropBasisNote,
        public readonly string $interpretationSafeguard,
        public readonly string $sourceIds,
        public readonly string $primarySourceUrl,
        public readonly string $classification,
        public readonly string $localValidationStatus,
    ) {}

    /**
     * A harvest signal is only ever reported from the source text itself.
     * "Undetermined" and "No documented month-specific signal" are explicitly NOT in season.
     */
    public function harvestSignal(): string
    {
        return match (true) {
            str_contains(mb_strtolower($this->harvestWindow), 'undetermined') => 'undetermined',
            str_contains(mb_strtolower($this->seasonalActivity), 'no documented') => 'undetermined',
            str_contains(mb_strtolower($this->seasonalActivity), 'possible harvest') => 'possible_harvest',
            default => 'undetermined',
        };
    }

    public function isNamedPlantingWindow(): bool
    {
        return str_contains(mb_strtolower($this->plantingWindow), 'named source window');
    }

    /** Nothing in this dataset may be presented as an officially verified local season. */
    public function isMaoValidated(): bool
    {
        return mb_strtolower($this->localValidationStatus) === 'validated';
    }

    public function isVerified(): bool
    {
        return str_contains(mb_strtolower($this->classification), 'verified')
            && !str_contains(mb_strtolower($this->classification), 'unverified');
    }
}