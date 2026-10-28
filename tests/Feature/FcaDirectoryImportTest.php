<?php

namespace Tests\Feature;

use App\Models\FcaRegistry;
use App\Models\User;
use App\Services\Mao\FcaDirectory\FcaDirectoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FCA directory import.
 *
 * The owner-supplied directory lists 21 organisations and the name of each president. Listing a
 * president is not proof of identity. These tests pin that an import never promotes a listed
 * name into a verified identity, never creates an account, and never invents a value.
 */
class FcaDirectoryImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'fca-dir-') . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function writeSource(array $overrides = [], int $count = 2): void
    {
        $records = [];
        for ($i = 1; $i <= $count; $i++) {
            $records[] = array_merge([
                'source_record_key' => "test-dir-{$i}",
                'official_name' => "Test Association {$i}",
                'address' => "Barangay {$i}, Aparri, Cagayan",
                'president_name' => "President {$i}",
                'contact_number' => null,
                'registry_source' => 'test source',
                'directory_verification_status' => 'pending',
                'procurement_eligibility_status' => 'not_assessed',
            ], $overrides);
        }

        file_put_contents($this->path, json_encode([
            'import_approved' => false,
            'records' => $records,
        ]));
    }

    private function importer(): FcaDirectoryImporter
    {
        return new FcaDirectoryImporter($this->path);
    }

    /** An unapproved source must be refused outright, writing nothing. */
    public function test_an_unapproved_source_is_refused_and_writes_nothing(): void
    {
        $this->writeSource();

        $this->expectException(\RuntimeException::class);
        try {
            $this->importer()->import();
        } finally {
            $this->assertSame(0, FcaRegistry::count(), 'a refused import must not write');
        }
    }

    public function test_preview_performs_no_write(): void
    {
        $this->writeSource(count: 3);

        $preview = $this->importer()->preview();

        $this->assertSame(3, $preview['source_record_count']);
        $this->assertSame(3, $preview['would_insert']);
        $this->assertSame(0, $preview['existing_registry_rows']);
        $this->assertFalse($preview['import_possible']);
        $this->assertSame(0, FcaRegistry::count(), 'preview must be read-only');
    }

    /** @param  array<string, mixed>  $overrides */
    private function writeApprovedSource(array $overrides = [], int $count = 2): void
    {
        $records = [];
        for ($i = 1; $i <= $count; $i++) {
            $records[] = array_merge([
                'source_record_key' => "test-dir-{$i}",
                'official_name' => "Test Association {$i}",
                'address' => "Barangay {$i}, Aparri, Cagayan",
                'president_name' => "President {$i}",
                'contact_number' => null,
                'registry_source' => 'test source',
                'directory_verification_status' => 'pending',
                'procurement_eligibility_status' => 'not_assessed',
            ], $overrides);
        }

        file_put_contents($this->path, json_encode(['import_approved' => true, 'records' => $records]));
    }

    /** The decisive guarantee: a listed president is never an identity-verified user. */
    public function test_a_listed_president_is_never_promoted_to_a_verified_identity(): void
    {
        $this->writeApprovedSource();
        $usersBefore = User::count();

        $this->importer()->import();

        $row = FcaRegistry::firstOrFail();
        $this->assertSame('President 1', $row->president_name);
        $this->assertSame('pending', $row->directory_verification_status);
        $this->assertSame('not_assessed', $row->procurement_eligibility_status);
        $this->assertNull($row->last_verified_at, 'nothing may be marked verified');
        $this->assertNull($row->procurement_evidence_reference);
        $this->assertNull($row->remarks);
        $this->assertSame($usersBefore, User::count(), 'an import must never create an account');
    }

    /** A missing contact number stays NULL. It is never written as 0 or an empty string. */
    public function test_a_missing_contact_number_stays_null(): void
    {
        $this->writeApprovedSource(['contact_number' => null]);

        $this->importer()->import();

        $this->assertNull(FcaRegistry::firstOrFail()->contact_number);
    }

    public function test_names_are_copied_verbatim_without_correction(): void
    {
        $this->writeApprovedSource(['official_name' => 'Sanja Ybanag Farmers Association'], count: 1);

        $this->importer()->import();

        $this->assertSame('Sanja Ybanag Farmers Association', FcaRegistry::firstOrFail()->official_name);
    }

    /** Re-running the import must update, never duplicate. */
    public function test_the_import_is_idempotent(): void
    {
        $this->writeApprovedSource();

        $first = $this->importer()->import();
        $this->assertSame(2, $first['inserted']);
        $this->assertSame(2, FcaRegistry::count());

        $second = $this->importer()->import();
        $this->assertSame(0, $second['inserted']);
        $this->assertSame(2, $second['updated']);
        $this->assertSame(2, FcaRegistry::count(), 're-import must not duplicate rows');
    }

    public function test_duplicate_source_keys_within_one_file_are_reported(): void
    {
        file_put_contents($this->path, json_encode([
            'import_approved' => true,
            'records' => [
                ['source_record_key' => 'dup', 'official_name' => 'A', 'address' => 'x', 'president_name' => 'P'],
                ['source_record_key' => 'dup', 'official_name' => 'B', 'address' => 'y', 'president_name' => 'Q'],
            ],
        ]));

        $preview = $this->importer()->preview();

        $this->assertCount(1, $preview['duplicate_source_keys']);
        $this->assertSame('dup', $preview['duplicate_source_keys'][0]['source_record_key']);
    }

    public function test_two_keys_claiming_one_name_are_reported_as_duplicates(): void
    {
        file_put_contents($this->path, json_encode([
            'import_approved' => true,
            'records' => [
                ['source_record_key' => 'k1', 'official_name' => 'Same Association', 'address' => 'x', 'president_name' => 'P'],
                ['source_record_key' => 'k2', 'official_name' => 'same   association', 'address' => 'y', 'president_name' => 'Q'],
            ],
        ]));

        $preview = $this->importer()->preview();

        $this->assertCount(1, $preview['duplicate_official_names']);
    }

    /** A record missing a NOT NULL field is reported, not written as an empty string. */
    public function test_a_record_missing_a_required_field_is_reported_and_blocks_the_import(): void
    {
        file_put_contents($this->path, json_encode([
            'import_approved' => true,
            'records' => [
                ['source_record_key' => 'k1', 'official_name' => 'No President', 'address' => 'x', 'president_name' => ''],
            ],
        ]));

        $preview = $this->importer()->preview();
        $this->assertCount(1, $preview['rejected_records']);
        $this->assertStringContainsString('president_name', $preview['rejected_records'][0]['reason']);

        $this->expectException(\RuntimeException::class);
        $this->importer()->import();
    }

    public function test_a_synthetic_owner_sized_dataset_remains_unapproved(): void
    {
        $this->writeSource(count: 21);
        $preview = $this->importer()->preview();

        $this->assertSame(21, $preview['source_record_count']);
        $this->assertSame(21, $preview['importable_count']);
        $this->assertSame([], $preview['duplicate_source_keys']);
        $this->assertSame([], $preview['duplicate_official_names']);
        $this->assertSame([], $preview['rejected_records']);
        $this->assertFalse($preview['import_approved_in_source'], 'the source is still unapproved');
    }
}