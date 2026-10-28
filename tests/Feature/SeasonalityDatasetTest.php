<?php

namespace Tests\Feature;

use App\Services\Seasonality\SeasonalityReferenceService;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The owner-supplied July-November derived seasonality dataset.
 *
 * These tests pin the properties the dataset is allowed to have. They are deliberately
 * strict about NOT claiming more than the source supports: nothing here may be reported
 * as a verified Aparri season, an "undetermined" harvest may never be read as zero
 * harvest, and the four October commodities must never collapse into one label.
 */
class SeasonalityDatasetTest extends TestCase
{
    private SeasonalityReferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SeasonalityReferenceService::class);
    }

    public function test_the_whole_dataset_loads_with_all_35_records(): void
    {
        $this->assertSame(35, $this->service->count(), 'every supplied record must be loaded');
        // Dataset order of first appearance, which is how the owner's file is ordered.
        $this->assertSame(
            ['Palay', 'Mais (grain corn)', 'Ampalaya', 'Talong', 'Patola', 'Sitaw', 'Kalabasa'],
            $this->service->commodities()
        );
        // 7 commodities x 5 months.
        $this->assertCount(35, $this->service->all());
    }

    public function test_every_record_preserves_its_derived_and_unvalidated_classification(): void
    {
        foreach ($this->service->all() as $reference) {
            $this->assertSame(
                'Secondary-source-derived illustrative signal',
                $reference->classification,
                "classification drift on {$reference->recordId}"
            );
            $this->assertSame(
                'Not validated as Aparri seasonal production by MAO',
                $reference->localValidationStatus,
                "MAO validation status drift on {$reference->recordId}"
            );
            // Nothing in this dataset may ever be presented as verified or MAO-validated.
            $this->assertFalse($reference->isMaoValidated(), "{$reference->recordId} must not be MAO validated");
            $this->assertFalse($reference->isVerified(), "{$reference->recordId} must not be verified");
            $this->assertNotSame('', $reference->primarySourceUrl, 'source url must be preserved');
            $this->assertNotSame('', $reference->sourceIds, 'source ids must be preserved');
            $this->assertNotSame('', $reference->interpretationSafeguard, 'safeguard text must be preserved');
        }
    }

    /**
     * The four October cases have genuinely different classifications and must not be
     * reported with one shared "in season" label.
     */
    public function test_october_classifications_differ_and_are_not_a_shared_peak_season(): void
    {
        $october = 10;

        $palay = $this->service->find('Palay', $october);
        $talong = $this->service->find('Talong', $october);
        $sitaw = $this->service->find('Sitaw', $october);
        $kalabasa = $this->service->find('Kalabasa', $october);

        $this->assertNotNull($palay);
        $this->assertNotNull($talong);
        $this->assertNotNull($sitaw);
        $this->assertNotNull($kalabasa);

        // Palay: possible harvest (inferred), outside its named planting window.
        $this->assertSame('Possible harvest (inferred)', $palay->seasonalActivity);
        $this->assertSame('possible_harvest', $palay->harvestSignal());
        $this->assertFalse($palay->isNamedPlantingWindow());

        // Talong: planting window AND possible harvest.
        $this->assertSame('Planting window + possible harvest', $talong->seasonalActivity);
        $this->assertSame('possible_harvest', $talong->harvestSignal());
        $this->assertTrue($talong->isNamedPlantingWindow());

        // Sitaw and Kalabasa: named planting window, harvest UNDETERMINED.
        foreach ([$sitaw, $kalabasa] as $case) {
            $this->assertSame('Named planting window', $case->seasonalActivity);
            $this->assertSame('undetermined', $case->harvestSignal());
            $this->assertTrue($case->isNamedPlantingWindow());
            $this->assertStringContainsString('Undetermined', $case->harvestWindow);
        }

        // The four must not collapse into one answer.
        $signals = [
            $palay->seasonalActivity, $talong->seasonalActivity,
            $sitaw->seasonalActivity, $kalabasa->seasonalActivity,
        ];
        $this->assertCount(3, array_unique($signals), 'October must retain three distinct profiles');
        // Sitaw and Kalabasa must never inherit Palay's possible-harvest wording.
        $this->assertStringNotContainsStringIgnoringCase('possible harvest', $signals[2]);
        $this->assertStringNotContainsStringIgnoringCase('possible harvest', $signals[3]);
        $this->assertStringContainsStringIgnoringCase('possible harvest', $signals[0]);
        $this->assertStringContainsStringIgnoringCase('possible harvest', $signals[1]);
    }

    /** "Undetermined" must never be read as "no harvest" - the source says so explicitly. */
    public function test_undetermined_harvest_is_never_reported_as_zero_or_out_of_season(): void
    {
        foreach ($this->service->all() as $reference) {
            if ($reference->harvestSignal() !== 'undetermined') continue;
            $this->assertStringContainsStringIgnoringCase(
                'undetermined',
                $reference->harvestWindow,
                "{$reference->recordId} lost its undetermined marker"
            );
        }
    }

    public function test_lookup_uses_commodity_and_month_as_the_key(): void
    {
        $this->assertNotNull($this->service->find('Palay', 7));
        $this->assertNotNull($this->service->find('Palay', 11));
        // June and December are structurally valid months but outside the supplied window.
        $this->assertNull($this->service->find('Palay', 6));
        $this->assertNull($this->service->find('Palay', 12));
        // A commodity not in the dataset must return null, never a default row.
        $this->assertNull($this->service->find('Banana Saba', 10));
        $this->assertNull($this->service->find('Ube', 10));
    }

    public function test_only_unambiguous_commodity_matches_are_offered(): void
    {
        // Ampalaya maps to exactly one HarborAI reference.
        $this->assertSame(['Ampalaya fruit [Bitter gourd]'], $this->service->canonicalCandidatesFor('Ampalaya'));
        $this->assertFalse($this->service->isAmbiguous('Ampalaya'));

        // Talong maps to three eggplant variants, so it must be reported as ambiguous.
        $this->assertTrue($this->service->isAmbiguous('Talong'));
        $this->assertCount(3, $this->service->canonicalCandidatesFor('Talong'));

        // Commodities with no HarborAI reference must not be silently mapped.
        foreach (['Palay', 'Mais (grain corn)', 'Patola', 'Sitaw', 'Kalabasa'] as $commodity) {
            $this->assertSame([], $this->service->canonicalCandidatesFor($commodity));
            $this->assertNull($this->service->datasetCommodityFor('Some Other Crop'));
        }
    }

    public function test_month_coverage_is_declared_honestly(): void
    {
        $this->assertTrue($this->service->coversMonth(7));
        $this->assertTrue($this->service->coversMonth(10));
        $this->assertTrue($this->service->coversMonth(11));
        $this->assertFalse($this->service->coversMonth(6));
        $this->assertFalse($this->service->coversMonth(12));
        // The structure accommodates all twelve months even though only five are supplied.
        $this->assertCount(12, SeasonalityReferenceService::supportedMonthNumbers());
    }

    public function test_loading_the_dataset_is_idempotent(): void
    {
        $first = array_map(static fn ($r) => $r->recordId, $this->service->all());
        // A second reader instance must produce exactly the same records, never duplicates.
        $second = array_map(static fn ($r) => $r->recordId, app(SeasonalityReferenceService::class)->all());
        $this->assertSame($first, $second);
        $this->assertSame(35, count(array_unique($second)));
    }

    public function test_commodity_normalisation_handles_the_dataset_labels(): void
    {
        $this->assertSame('mais grain corn', SeasonalityReferenceService::normalize('Mais (grain corn)'));
        $this->assertSame('ampalaya', SeasonalityReferenceService::normalize('  Ampalaya '));
        $this->assertSame('sitaw', SeasonalityReferenceService::normalize('Sitaw'));
    }
}