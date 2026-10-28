<?php

namespace App\Services\AI;

/**
 * Turns a successful deterministic analysis into readable, evidence-grounded prose.
 *
 * WHY THIS EXISTS
 * ---------------
 * The Product Assistant could only narrate its findings when the optional Azure provider
 * answered. Because Azure is unconfigured in this deployment, every farmer saw a bare
 * "generative AI unavailable" notice even though the seasonal and price evidence had been
 * computed successfully and was sitting in the response. A missing optional provider was hiding
 * real analysis.
 *
 * This class is NOT a second analysis engine and NOT a substitute for Azure. It reads the
 * DeterministicMarketAnalysisService output that already exists and phrases it. It computes no
 * price, derives no season and invents no statistic. Every sentence is assembled from fields
 * that the deterministic engine already produced from the real datasets.
 *
 * WHAT IT MUST NEVER DO
 * ---------------------
 * - Label every commodity as definitively in season. The wording follows the matched row's own
 *   `harvest_signal`: possible_harvest, undetermined, or absent.
 * - Treat an undetermined harvest as zero production. "Undetermined" is stated as undetermined.
 * - Invent a harvest peak, or an October 2026 price, or an Aparri price.
 * - Describe itself as AI-generated. It is deterministic phrasing over deterministic evidence.
 * - Estimate a price premium or discount for an out-of-Aparri origin.
 */
final class ProductAssistantInterpretation
{
    /** Harvest must never be presented as confirmed unless the source says so. */
    private const HARVEST_POSSIBLE = 'possible_harvest';

    private const HARVEST_UNDETERMINED = 'undetermined';

    /**
     * @param  array<string, mixed>  $analysis  Output of DeterministicMarketAnalysisService::analyze()
     * @param  array<string, mixed>  $input     The validated producer input
     * @return array<string, mixed>
     */
    public function build(array $analysis, array $input, string $generativeAiStatus = 'UNAVAILABLE'): array
    {
        $seasonality = is_array($analysis['seasonality'] ?? null) ? $analysis['seasonality'] : [];
        $reference = is_array($seasonality['references'][0] ?? null) ? $seasonality['references'][0] : null;
        $signal = is_array($reference['signal'] ?? null) ? $reference['signal'] : [];
        $comparison = is_array($analysis['producer_price_comparison'] ?? null) ? $analysis['producer_price_comparison'] : [];
        $basis = is_array($comparison['reference_basis'] ?? null) ? $comparison['reference_basis'] : null;

        $origin = trim((string) ($input['origin'] ?? ''));
        $commodity = trim((string) ($input['product_name'] ?? '')) ?: 'this commodity';
        $month = (string) ($seasonality['analysis_month_name'] ?? 'the selected month');

        $originIsAparri = $origin === '' || $this->mentionsAparri($origin);

        return [
            // The four statuses the farmer needs to see kept apart. A failure in one is never
            // allowed to blank out the others.
            'market_analysis_status' => 'AVAILABLE',
            'seasonality_evidence_status' => $reference !== null ? 'AVAILABLE' : 'INSUFFICIENT_DATA',
            'price_evidence_status' => $this->priceEvidenceStatus($comparison),
            'generative_ai_status' => $generativeAiStatus,
            'interpretation_source' => 'HarborAI deterministic analysis (not Azure-generated)',

            'origin' => [
                'supplied' => $origin === '' ? null : $origin,
                'is_aparri' => $originIsAparri,
            ],

            'seasonality_narrative' => $this->seasonalityNarrative($commodity, $month, $reference, $signal),
            'price_narrative' => $this->priceNarrative($basis, $comparison),
            'geographic_narrative' => $this->geographicNarrative($origin, $originIsAparri, $basis),
            'pricing_question' => $this->pricingQuestion($commodity, $month, $input),

            'interpretation' => $this->compose(
                $commodity, $month,
                $this->seasonalityNarrative($commodity, $month, $reference, $signal),
                $this->priceNarrative($basis, $comparison),
                $this->geographicNarrative($origin, $originIsAparri, $basis),
                $this->pricingQuestion($commodity, $month, $input)
            ),
        ];
    }

    /** @param  array<string, mixed>  $comparison */
    private function priceEvidenceStatus(array $comparison): string
    {
        if (($comparison['status'] ?? '') === 'COMPARABLE') {
            return 'AVAILABLE';
        }
        if (($comparison['status'] ?? '') === 'MULTIPLE_REFERENCES') {
            return 'AMBIGUOUS';
        }
        if (($comparison['status'] ?? '') === 'UNIT_INCOMPATIBLE') {
            return 'NOT_COMPARABLE';
        }

        return 'INSUFFICIENT_DATA';
    }

    private function mentionsAparri(string $origin): bool
    {
        return (bool) preg_match('/\baparri\b/i', $origin);
    }

    /**
     * Seasonality wording, driven by the matched row's own harvest signal.
     *
     * @param  array<string, mixed>|null  $reference
     * @param  array<string, mixed>  $signal
     */
    private function seasonalityNarrative(string $commodity, string $month, ?array $reference, array $signal): string
    {
        if ($reference === null) {
            return "HarborAI holds no seasonal reference for {$commodity}, so it will not infer a season for {$month}. "
                . 'A missing seasonal reference is an absence of evidence, not evidence of no production.';
        }

        $harvest = (string) ($signal['harvest_signal'] ?? '');
        $planting = (string) ($signal['named_planting_window'] ?? '');
        $activity = (string) ($signal['seasonal_activity'] ?? '');
        $name = (string) ($reference['commodity_label'] ?? $commodity);
        $scope = (string) ($reference['geographic_scope'] ?? 'the published reference scope');

        $plantingNamed = stripos($planting, 'named') !== false && stripos($planting, 'outside') === false;

        $sentence = $plantingNamed
            ? "{$month} falls within a documented planting window for {$name}."
            : "{$month} is not inside a named planting window for {$name}.";

        if ($harvest === self::HARVEST_POSSIBLE) {
            $sentence .= ' The reference also records possible harvest activity in this month. '
                . 'That is an inference from published reference material; it does not establish an actual harvest peak in Aparri.';
        } elseif ($harvest === self::HARVEST_UNDETERMINED) {
            $sentence .= ' Harvest activity for this month is undetermined. '
                . 'Undetermined is not the same as zero: HarborAI is not claiming there is no production, only that the reference does not state it.';
        } else {
            $sentence .= ' The reference records no harvest indicator for this month, and HarborAI will not supply one.';
        }

        if ($activity !== '') {
            $sentence .= " The classification recorded in the source dataset is \"{$activity}\".";
        }

        $sentence .= " This classification is derived from published reference material covering {$scope}.";

        $validation = (string) ($reference['source_label'] ?? '');
        if ($validation !== '') {
            $sentence .= " The source labels it \"{$validation}\".";
        }

        return $sentence;
    }

    /**
     * Price wording. Regional historical evidence is described as exactly that, and its absence
     * is stated as absence rather than as a zero price.
     *
     * @param  array<string, mixed>|null  $basis
     * @param  array<string, mixed>  $comparison
     */
    private function priceNarrative(?array $basis, array $comparison): string
    {
        if ($basis === null || ($basis['available'] ?? false) !== true) {
            return 'No verified price reference is currently available for this commodity, so HarborAI shows no price comparison. '
                . 'Missing price evidence is not a price of zero, and no price is estimated in its place.';
        }

        $geography = (string) ($basis['geography'] ?? 'the recorded region');
        $count = (int) ($basis['observation_count'] ?? 0);
        $first = (string) ($basis['first_observation_date'] ?? 'n/a');
        $latest = (string) ($basis['latest_observation_date'] ?? 'n/a');
        $behind = $basis['months_behind_analysis'] ?? null;

        $narrative = "Historical {$geography} farmgate observations ({$count} observations, spanning {$first} to {$latest}) "
            . "provide additional context. These are regional figures: they are not Aparri-specific prices, "
            . 'and they are not current market prices.';

        if (is_numeric($behind) && (int) $behind > 0) {
            $narrative .= ' The most recent observation is ' . (int) $behind . ' month(s) older than the month being analysed.';
        }

        if (($comparison['status'] ?? '') === 'COMPARABLE' && $comparison['difference_percent_from_latest_reference'] !== null) {
            $pct = abs((float) $comparison['difference_percent_from_latest_reference']);
            $direction = (float) ($comparison['difference_from_latest_reference'] ?? 0) >= 0 ? 'above' : 'below';
            $narrative .= sprintf(
                ' Your proposed price sits %s%% %s that reference; treat this as context for a regional, historical '
                    . 'series rather than as a verdict on your local price.',
                number_format($pct, 2),
                $direction
            );
        }

        return $narrative;
    }

    /**
     * Geographic wording. An origin outside Aparri is acknowledged and explained WITHOUT
     * estimating any price difference, because no evidence supports one.
     *
     * @param  array<string, mixed>|null  $basis
     */
    private function geographicNarrative(string $origin, bool $originIsAparri, ?array $basis): string
    {
        if ($origin === '') {
            return 'No origin was supplied. Geographic assumptions therefore remain open: the references used here may be '
                . 'broader than your farm, so they should not be read as local conditions.';
        }

        if ($originIsAparri) {
            $scope = $basis !== null && ($basis['available'] ?? false) === true
                ? (string) ($basis['geography'] ?? 'the recorded region')
                : 'the published reference material';

            return "You supplied {$origin}. The references used here cover {$scope}, which is broader than a single farm. "
                . 'They describe regional conditions and should be read alongside what you observe on your own land.';
        }

        return "You supplied {$origin} as the origin, which is outside Aparri. Climate, transport distance and input costs "
            . 'vary between places, so prices in one location should not be assumed to apply in another. '
            . 'HarborAI holds no verified price observation for this origin and will not estimate a difference in either '
            . 'direction: it cannot say your commodity is more expensive or less expensive here. '
            . 'Compare against references that are relevant to where the product actually comes from, and weigh that against '
            . 'your own transport and handling costs.';
    }

    /** @param  array<string, mixed>  $input */
    private function pricingQuestion(string $commodity, string $month, array $input): string
    {
        $price = $input['producer_price'] ?? null;
        $unit = trim((string) ($input['unit'] ?? ''));

        $question = is_numeric($price)
            ? "Considering your production costs, product quality, the seasonal signal recorded for {$month} and the price "
                . 'references above, what price would you consider appropriate for your ' . $commodity . ($unit !== '' ? " (per {$unit})" : '') . '?'
            : "Given the seasonal signal recorded for {$month} and the price references above, what price would you consider "
                . 'appropriate for your ' . $commodity . ', and what production and handling costs must it cover?';

        return $question;
    }

    private function compose(
        string $commodity,
        string $month,
        string $seasonality,
        string $price,
        string $geographic,
        string $question
    ): string {
        return implode(' ', array_filter([$seasonality, $price, $geographic, $question]));
    }
}