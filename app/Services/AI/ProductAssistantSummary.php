<?php

namespace App\Services\AI;

/**
 * Composes the short, farmer-facing market reading shown by default.
 *
 * WHY THIS EXISTS
 * ---------------
 * The first interpretation repeated the same limitations in four places: a seasonality paragraph,
 * a price-evidence paragraph, a geographic paragraph, and then an advisory block that said the
 * same things again. The farmer had to read roughly four times the necessary text to reach the
 * one number they care about: what to charge.
 *
 * This class produces ONE paragraph of 80-120 words. Everything it omits is still available in
 * the full narrative fields and in the collapsed evidence section; nothing is discarded, it is
 * simply no longer repeated.
 *
 * WHAT IT MUST NOT DO
 * -------------------
 * - Invent a price, a harvest peak, or an October-2026 figure.
 * - Call a regional historical series a current Aparri price.
 * - Present an ambiguous commodity as having no price evidence when positive observations exist
 *   for candidate varieties. Ambiguity and absence are different facts.
 * - Claim itself to be AI-generated. It is deterministic phrasing.
 */
final class ProductAssistantSummary
{
    /**
     * @param  array<string, mixed>  $analysis  Output of DeterministicMarketAnalysisService::analyze()
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $analysis, array $input): array
    {
        $seasonality = is_array($analysis['seasonality'] ?? null) ? $analysis['seasonality'] : [];
        $reference = is_array($seasonality['references'][0] ?? null) ? $seasonality['references'][0] : null;
        $signal = is_array($reference['signal'] ?? null) ? $reference['signal'] : [];
        $comparison = is_array($analysis['producer_price_comparison'] ?? null) ? $analysis['producer_price_comparison'] : [];
        $basis = is_array($comparison['reference_basis'] ?? null) ? $comparison['reference_basis'] : null;

        // Candidate variety count lives on the commodity resolution, not on the comparison.
        $candidates = is_array($analysis['psa_reference_candidates'] ?? null) ? $analysis['psa_reference_candidates'] : [];

        $month = (string) ($seasonality['analysis_month_name'] ?? 'the selected month');
        $origin = trim((string) ($input['origin'] ?? ''));

        $sentences = [];
        $sentences[] = $this->seasonSentence($month, $reference, $signal);
        $sentences[] = $this->priceSentence($comparison, $basis, $candidates);
        if ($origin !== '' && ! $this->mentionsAparri($origin)) {
            $sentences[] = "Since you source from {$origin}, local transport and market conditions may differ, and HarborAI cannot estimate that difference for you.";
        }
        $sentences[] = $this->questionSentence($input);

        $paragraph = trim(implode(' ', array_filter($sentences)));

        return [
            // The farmer-facing paragraph, kept within a readable length.
            'summary' => $paragraph,
            'word_count' => $this->wordCount($paragraph),
            'question' => $this->questionSentence($input),
            // Short, unambiguous facts for the "Key Market Facts" card.
            'key_facts' => [
                [
                    'label' => 'Season',
                    'value' => $reference === null
                        ? 'No seasonal reference'
                        : (string) ($signal['seasonal_activity'] ?? $reference['status'] ?? 'Recorded'),
                ],
                [
                    'label' => 'Price evidence',
                    'value' => $this->priceFactLabel($comparison, $basis, $candidates),
                ],
                [
                    'label' => 'Harvest',
                    'value' => $this->harvestFactLabel($signal),
                ],
                [
                    'label' => 'Geography',
                    'value' => (string) ($basis['geography'] ?? ($candidates !== [] ? 'Per-variety regional series' : 'No comparable reference')),
                ],
            ],
            // Variety detail belongs in the expanded evidence, never in the summary line.
            'variety_candidates' => array_map(fn ($c) => [
                'commodity_label' => (string) ($c['commodity_label'] ?? ''),
                'comparable_observation_count' => (int) ($c['psa_reference']['comparable_observation_count'] ?? 0),
                'geography' => (string) ($c['psa_reference']['geography'] ?? ''),
            ], $candidates),
        ];
    }

    private function mentionsAparri(string $origin): bool
    {
        return (bool) preg_match('/\baparri\b/i', $origin);
    }

    /** Renders a count with thousands separators, e.g. 837 -> "837". */
    private function countPhrase(int $count): string
    {
        return number_format($count);
    }

    private function wordCount(string $text): int
    {
        return count(preg_split('/\s+/', trim($text)) ?: []);
    }

    /** @param  array<string, mixed>|null  $reference */
    private function seasonSentence(string $month, ?array $reference, array $signal): string
    {
        if ($reference === null) {
            return "HarborAI has no seasonal reference for this commodity in {$month}, so it will not guess a season.";
        }

        $planting = (string) ($signal['named_planting_window'] ?? '');
        $harvest = (string) ($signal['harvest_signal'] ?? '');
        $plantingNamed = stripos($planting, 'named') !== false && stripos($planting, 'outside') === false;

        $first = $plantingNamed
            ? "{$month} sits inside a documented planting window for this crop"
            : "{$month} falls outside a named planting window for this crop";

        if ($harvest === 'possible_harvest') {
            return $first . ', with possible harvest activity recorded for the same month. '
                . 'That is a published inference, not a measured harvest peak in Aparri.';
        }
        if ($harvest === 'undetermined') {
            return $first . '. The reference says nothing about harvest, so HarborAI reports it as '
                . 'undetermined rather than as no production.';
        }

        return $first . '.';
    }

    /**
     * @param  array<string, mixed>|null  $basis
     * @param  list<array<string, mixed>>  $candidates
     */
    private function priceSentence(array $comparison, ?array $basis, array $candidates = []): string
    {
        $status = (string) ($comparison['status'] ?? '');

        if ($basis !== null && ($basis['available'] ?? false) === true) {
            $geography = (string) ($basis['geography'] ?? 'the recorded region');
            $count = (int) ($basis['observation_count'] ?? 0);
            $behind = $basis['months_behind_analysis'] ?? null;

            $text = "Historical {$geography} farmgate data ({$count} observations) give you context";
            $text .= is_numeric($behind) && (int) $behind > 0
                ? ", though the newest is " . (int) $behind . ' month(s) old. '
                : '. ';
            $text .= 'They are regional history, not current Aparri prices.';

            if ($status === 'COMPARABLE' && $comparison['difference_percent_from_latest_reference'] !== null) {
                $direction = (float) ($comparison['difference_from_latest_reference'] ?? 0) >= 0 ? 'above' : 'below';
                $text .= sprintf(
                    ' Your price sits about %s%% %s that series.',
                    number_format(abs((float) $comparison['difference_percent_from_latest_reference']), 1),
                    $direction
                );
            }

            return $text;
        }

        // Ambiguity is NOT the same as absence. Positive observations can exist for candidate
        // varieties that simply have not been chosen as the comparable one, so this must never
        // read as "no positive price observations".
        if ($status === 'MULTIPLE_REFERENCES') {
            // Three different numbers must never be conflated:
            //   $varietyCount  how many separate PSA series could match
            //   $varietiesWithData  how many of those actually contain usable observations
            //   $observationCount how many observations exist in total
            // Reporting the variety count as if it were an observation count would understate
            // the evidence by two orders of magnitude.
            $observationCount = 0;
            $varietiesWithData = 0;
            foreach ($candidates as $candidate) {
                $n = (int) ($candidate['psa_reference']['comparable_observation_count'] ?? 0);
                $observationCount += $n;
                if ($n > 0) {
                    $varietiesWithData++;
                }
            }
            $varietyCount = count($candidates);

            if ($varietyCount === 0) {
                return 'Several PSA varieties could match this crop, but no series was returned, '
                    . 'so no comparison is shown.';
            }

            if ($observationCount > 0) {
                return sprintf(
                    'Historical PSA price records are available for %d %s of this crop, holding %s in total. '
                        . 'HarborAI cannot select one as your comparable reference until you identify the '
                        . 'specific variety, and combining different varieties into one benchmark would '
                        . 'mislead you, so no single comparison is shown.',
                    $varietyCount,
                    $varietyCount === 1 ? 'variety' : 'varieties',
                    $this->countPhrase($observationCount)
                );
            }

            return sprintf(
                'There are %d PSA %s for this crop, but none currently holds a usable positive '
                    . 'observation, so no comparison is shown. That is missing evidence, not a price of zero.',
                $varietyCount,
                $varietyCount === 1 ? 'series' : 'series'
            );
        }

        return 'No verified price reference matches this commodity yet, so no comparison is shown. '
            . 'That is missing evidence, not a price of zero.';
    }

    /**
     * @param  array<string, mixed>|null  $basis
     * @param  list<array<string, mixed>>  $candidates
     */
    private function priceFactLabel(array $comparison, ?array $basis, array $candidates = []): string
    {
        $status = (string) ($comparison['status'] ?? '');
        if ($status === 'COMPARABLE' && $basis !== null && ($basis['available'] ?? false) === true) {
            return 'Comparable regional series';
        }
        if ($status === 'MULTIPLE_REFERENCES') {
            $observations = 0;
            foreach ($candidates as $candidate) {
                $observations += (int) ($candidate['psa_reference']['comparable_observation_count'] ?? 0);
            }

            return sprintf(
                'Ambiguous - %d %s, %s available',
                count($candidates),
                count($candidates) === 1 ? 'series' : 'series',
                $observations > 0 ? $this->countPhrase($observations) . ' observations' : 'none usable'
            );
        }
        if ($status === 'UNIT_INCOMPATIBLE') {
            return 'Not comparable (unit mismatch)';
        }

        return 'No comparable reference';
    }

    /** @param  array<string, mixed>  $signal */
    private function harvestFactLabel(array $signal): string
    {
        return match ((string) ($signal['harvest_signal'] ?? '')) {
            'possible_harvest' => 'Possible (illustrative)',
            'undetermined' => 'Undetermined - not zero',
            default => 'Not stated by reference',
        };
    }

    /** @param  array<string, mixed>  $input */
    private function questionSentence(array $input): string
    {
        $price = $input['producer_price'] ?? null;

        return is_numeric($price)
            ? 'Given your costs and this context, what price would you be comfortable listing?'
            : 'What price would you be comfortable listing, given your costs?';
    }
}