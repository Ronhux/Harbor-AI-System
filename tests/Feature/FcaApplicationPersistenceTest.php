<?php

namespace Tests\Feature;

use App\Http\Controllers\FcaApplicationController;
use App\Models\FcaEvidence;
use App\Models\FcaRegistry;
use App\Models\FcaRepresentative;
use App\Models\FcaReviewEvent;
use App\Models\Producer;
use App\Models\User;
use App\Services\Mao\FcaEvidenceScanner;
use App\Services\Mao\FcaEvidenceStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\VerifiesTestDatabase;

class FcaApplicationPersistenceTest extends TestCase
{
    use RefreshDatabase, VerifiesTestDatabase {
        beginDatabaseTransaction as private beginSqliteTestTransaction;
    }

    public function beginDatabaseTransaction(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->beginSqliteTestTransaction();
            return;
        }
        // Real workflow transactions must own their READ COMMITTED boundary.
        // Clear fixtures only on the explicitly authorized disposable schema.
        $this->verifyTestDatabaseTargetPostBoot();
        $connection = \Illuminate\Support\Facades\DB::connection();
        $tables = $connection->select("SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                if ($table->name === 'migrations') continue;
                if (!preg_match('/^[a-z_]+$/', $table->name)) throw new \LogicException('Unexpected table identifier');
                $connection->table($table->name)->delete();
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private User $owner;
    private User $admin;
    private Producer $producer;
    private FcaRegistry $fca;

    protected function setUp(): void
    {
        // Fail-closed database target verification BEFORE RefreshDatabase runs migrations.
        $this->verifyTestDatabaseTargetPreBoot();

        parent::setUp();

        // Post-boot verification of resolved configuration.
        $this->verifyTestDatabaseTargetPostBoot();

        $this->assertFalse(config('mao_workflows.fca_applications_enabled'));
        // Base FCA schema is now created by migrations in database/migrations/ (2026_10_09_000001, 2).
        // Additive migrations for evidence/inspection are also applied by 2026_10_09_000002.
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        if (config('database.default') === 'mysql') {
            // MariaDB fixtures satisfy the real private-staging/storage rules.
            $temporary = realpath(sys_get_temp_dir());
            if (!$temporary || !str_starts_with(basename($temporary), 'harborai-fca-regression-')) {
                throw new \LogicException('Dedicated regression temporary directory required.');
            }
            $root = $temporary.'/evidence-'.Str::uuid();
            mkdir($root, 0700);
            Storage::set('fca_evidence', Storage::build(['driver'=>'local', 'root'=>$root, 'serve'=>false, 'visibility'=>'private']));
            config(['fca_evidence.upload_tmp_dir'=>$temporary]);
        } else {
            Storage::fake('fca_evidence');
        }
        config([
            'filesystems.disks.fca_evidence.driver' => 'local',
            'filesystems.disks.fca_evidence.root' => Storage::disk('fca_evidence')->path(''),
            'filesystems.disks.fca_evidence.serve' => false,
            'filesystems.disks.fca_evidence.visibility' => 'private',
        ]);
        $this->app->instance(FcaEvidenceScanner::class, new class extends FcaEvidenceScanner {
            public function inspect(string $bytes, string $mime): string { return 'clean'; }
        });
        $malware = new class implements \App\Services\Mao\Inspection\MalwareProvider {
            public function inspect(string $bytes, string $mime): string { return 'clean'; }
        };
        $pdf = new class implements \App\Services\Mao\Inspection\PdfInspector {
            public function inspect(string $bytes): string { return 'clean'; }
        };
        $this->app->instance(\App\Services\Mao\Inspection\DocumentInspector::class, new \App\Services\Mao\Inspection\DocumentInspector($malware, $pdf));
        $this->owner = $this->account('Farmer');
        $this->admin = $this->account('Admin');
        $this->producer = Producer::create(['user_id' => $this->owner->user_id, 'verification_status' => 'Verified']);
        $this->fca = new FcaRegistry();
        $this->fca->forceFill(['official_name' => 'Synthetic Test Association', 'address' => 'Synthetic location',
            'president_name' => 'Synthetic president', 'directory_verification_status' => 'verified'])->save();
        config(['mao_workflows.fca_applications_enabled' => true,
            'mao_workflows.fca_inspection_persistence_enabled' => true,
            'mao_workflows.fca_evidence_access_enabled' => true,
            'fca_president.workflow_enabled' => true,
            'fca_evidence.inspection_adapter_id' => 'synthetic.document',
            'fca_evidence.max_inspection_attempts' => 5,
            'fca_evidence.receipt_ttl_hours' => 24, // Synthetic test policy only.
            'mao_workflows.fca_reviewer_user_ids' => [(int) $this->admin->user_id]]);
        app('router')->prefix('api')->group(base_path('routes/prepared/mao_fca.php'));
        // Runtime test registration happens after the SPA catch-all. Put only the
        // prepared test routes first, as API registration would at approved rollout.
        $routes = new \Illuminate\Routing\RouteCollection();
        $all = app('router')->getRoutes();
        foreach ($all as $route) if (str_contains($route->getActionName(), FcaApplicationController::class)) $routes->add($route);
        foreach ($all as $route) if (!str_contains($route->getActionName(), FcaApplicationController::class)) $routes->add($route);
        app('router')->setRoutes($routes);
    }

    protected function tearDown(): void
    {
        // Reset FCA feature gates to default (disabled) to prevent test pollution.
        config(['mao_workflows.fca_applications_enabled' => false,
            'mao_workflows.fca_inspection_persistence_enabled' => false,
            'mao_workflows.fca_evidence_access_enabled' => false,
            'fca_president.workflow_enabled' => false]);
        parent::tearDown();
    }

    private function account(string $role): User
    {
        return User::create(['first_name' => 'Synthetic', 'last_name' => 'Fixture',
            'email' => Str::uuid() . '@example.test', 'password_hash' => bcrypt('test-only'),
            'contact_number' => self::uniqueMobile(), 'user_type' => $role, 'is_active' => true]);
    }

    private static function uniqueMobile(): string
    {
        static $seq = 0;
        return sprintf('0918%07d', ++$seq);
    }

    private function receipt(): string
    {
        $receipt = $this->actingAs($this->owner)->postJson('/api/fca/evidence', [
            'evidence' => $this->document(),
        ]);
        if (getenv('FCA_DIAGNOSTICS') === '1') fwrite(STDOUT, 'UPLOAD '. $receipt->status().' '.$receipt->getContent().PHP_EOL);
        $receipt = $receipt->assertCreated()->assertJsonPath('status', 'quarantined')->json('receipt');
        $this->inspectReceipt($receipt);
        return $receipt;
    }

    protected function inspectReceipt(string $receipt): \App\Models\FcaInspectionAttempt
    {
        $file = FcaEvidence::findOrFail($receipt);
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        $attempt = $ledger->begin($this->admin, $receipt, $file->evidence_version, $file->sha256, (string) Str::uuid());
        $completed = $ledger->complete($this->admin, $attempt->getKey());
        if (getenv('FCA_DIAGNOSTICS') === '1') {
            fwrite(STDOUT, 'INSPECTION '.json_encode(['adapter'=>config('fca_evidence.inspection_adapter_id'), 'attempt_status'=>$completed->status, 'checks'=>$completed->check_results, 'evidence_status'=>$file->fresh()->status, 'inspection_state'=>$file->fresh()->inspection_state, 'successful'=>$file->fresh()->hasSuccessfulInspection()]).PHP_EOL);
        }
        return $completed;
    }

    private function document(): UploadedFile
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach (['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>'] as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 4\n0000000000 65535 f \n";
        foreach ($offsets as $offset) $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        $pdf .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return UploadedFile::fake()->createWithContent('synthetic-proof.pdf', $pdf);
    }

    private function applicationInput(?string $receipt = null): array
    {
        return ['representative_kind' => 'delegated', 'representative_position' => 'Synthetic delegate', 'authorization_statement' => 'Synthetic claim for review',
            'evidence_receipt' => $receipt ?? $this->receipt(), 'submission_key' => (string) Str::uuid()];
    }

    private function application(): FcaRepresentative
    {
        $input = $this->applicationInput();
        $id = $this->actingAs($this->owner)->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input)
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        return FcaRepresentative::findOrFail($id);
    }

    private function decision(array $override = []): array
    {
        return array_replace(['decision' => 'authorize', 'review_reason' => 'Synthetic reviewed authorization',
            'decision_key' => (string) Str::uuid(), 'expected_version' => 0], $override);
    }

    public function test_disabled_workflow_stops_before_upload_and_authentication(): void
    {
        config(['mao_workflows.fca_applications_enabled' => false]);
        $this->postJson('/api/fca/evidence', [])->assertNotFound();
        $this->assertDatabaseCount('fca_evidence', 0);
        Storage::disk('fca_evidence')->assertDirectoryEmpty('');
    }

    public function test_enabled_workflow_requires_authentication_and_an_approved_producer(): void
    {
        $this->postJson('/api/fca/evidence', [])->assertUnauthorized();
        $this->actingAs($this->admin)->postJson('/api/fca/evidence', [])->assertForbidden();
        $this->producer->update(['verification_status' => 'Pending']);
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', [])->assertForbidden();
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    public function test_pending_submission_preserves_private_details_and_unrelated_approvals(): void
    {
        $before = $this->producer->fresh()->getAttributes();
        $input = $this->applicationInput();
        $response = $this->actingAs($this->owner)->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input);
        if (getenv('FCA_DIAGNOSTICS') === '1') fwrite(STDOUT, 'SUBMISSION '.$response->status().' '.$response->getContent().PHP_EOL);
        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $this->assertStringNotContainsString('evidence_receipt', $response->getContent());
        $this->assertStringNotContainsString('authorization_statement', $response->getContent());
        $application = FcaRepresentative::first();
        $this->assertSame($input['authorization_statement'], $application->submitted_details['authorization_statement']);
        $this->assertStringNotContainsString($input['authorization_statement'], $application->getRawOriginal('submitted_details'));
        $this->assertSame($before, $this->producer->fresh()->getAttributes());
        $this->assertSame('not_assessed', $this->fca->fresh()->procurement_eligibility_status);
        $this->assertDatabaseCount('fca_review_events', 4); // Upload, inspection start/completion, submission.
        $this->assertSame('attached', FcaEvidence::first()->status);
    }

    public function test_missing_or_unverified_association_is_rejected_without_consuming_evidence(): void
    {
        $input = $this->applicationInput();
        $this->postJson('/api/fca/directory/99999/applications', $input)->assertUnprocessable();
        $this->fca->forceFill(['directory_verification_status' => 'pending'])->save();
        $this->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input)->assertUnprocessable();
        $this->assertDatabaseCount('fca_representatives', 0);
        $this->assertSame('clean', FcaEvidence::first()->status);
    }

    public function test_absent_foreign_expired_or_quarantined_receipts_cannot_submit(): void
    {
        $input = $this->applicationInput((string) Str::uuid());
        $this->actingAs($this->owner)->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input)->assertUnprocessable();
        $receipt = $this->receipt();
        $file = FcaEvidence::find($receipt);
        $other = $this->account('Farmer');
        $file->forceFill(['uploaded_by' => $other->user_id])->save();
        $input = $this->applicationInput($receipt);
        $this->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input)->assertUnprocessable();
        $file->forceFill(['uploaded_by' => $this->owner->user_id, 'expires_at' => now()->subMinute()])->save();
        $this->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input)->assertUnprocessable();
        $file->forceFill(['expires_at' => now()->addDay(), 'status' => 'quarantined'])->save();
        $this->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input)->assertUnprocessable();
        $this->assertDatabaseCount('fca_representatives', 0);
    }

    public function test_invalid_content_size_and_missing_file_are_rejected(): void
    {
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', [])->assertUnprocessable();
        foreach ([UploadedFile::fake()->createWithContent('proof.png', '<?php echo "not an image";'),
            UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n/JavaScript /OpenAction\n%%EOF"),
            UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\nincomplete"),
            $this->document()->size(5121)] as $file) {
            $this->postJson('/api/fca/evidence', ['evidence' => $file])->assertUnprocessable();
        }
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    public function test_default_scanner_quarantines_and_prevents_document_download(): void
    {
        $this->app->instance(\App\Services\Mao\Inspection\DocumentInspector::class, new \App\Services\Mao\Inspection\DocumentInspector());
        $this->app->instance(FcaEvidenceScanner::class, new FcaEvidenceScanner());
        $receipt = $this->actingAs($this->owner)->postJson('/api/fca/evidence', [
            'evidence' => $this->document(),
        ])->assertCreated()->assertJsonPath('status', 'quarantined')->json('receipt');
        $this->getJson('/api/fca/evidence/' . $receipt)->assertForbidden();
        $this->assertDatabaseCount('fca_representatives', 0);
    }

    public function test_file_names_are_generated_and_downloads_are_private_attachments(): void
    {
        $receipt = $this->receipt();
        $file = FcaEvidence::find($receipt);
        $this->assertSame('evidence/' . $receipt . '.bin', $file->storage_key);
        $this->assertStringNotContainsString('synthetic-proof', json_encode($file->getAttributes()));
        $this->get('/api/fca/evidence/' . $receipt)->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $this->get('/api/fca/evidence/' . $receipt)->headers->get('Cache-Control'));
        $this->actingAs($this->admin)->getJson('/api/fca/evidence/' . $receipt)->assertForbidden();
    }

    public function test_other_producer_and_unassigned_administrators_cannot_read_documents(): void
    {
        $application = $this->application();
        $other = $this->account('Farmer');
        Producer::create(['user_id' => $other->user_id, 'verification_status' => 'Verified']);
        $this->actingAs($other)->getJson('/api/fca/applications/' . $application->id)->assertForbidden();
        $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertForbidden();
        $unassigned = $this->account('Super Admin');
        $this->actingAs($unassigned)->getJson('/api/fca/review/pending')->assertForbidden();
        $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertForbidden();
        $this->postJson('/api/fca/review/' . $application->id, $this->decision())->assertForbidden();
        $this->actingAs($this->admin)->get('/api/fca/applications/' . $application->id . '/evidence')->assertOk();
    }

    public function test_duplicate_submission_is_idempotent_but_changed_submission_conflicts(): void
    {
        $input = $this->applicationInput();
        $url = '/api/fca/directory/' . $this->fca->id . '/applications';
        $this->postJson($url, $input)->assertCreated();
        $this->postJson($url, $input)->assertCreated();
        $this->postJson($url, array_replace($input, ['authorization_statement' => 'Different claim']))->assertConflict();
        $this->assertDatabaseCount('fca_representatives', 1);
        $this->assertSame(1, FcaReviewEvent::where('event_type', 'application_submitted')->count());
    }

    public function test_review_is_idempotent_and_cannot_activate_unrelated_privileges(): void
    {
        $application = $this->application();
        $beforeUser = $this->owner->fresh()->getAttributes();
        $beforeProfile = $this->producer->fresh()->getAttributes();
        $input = $this->decision();
        $url = '/api/fca/review/' . $application->id;
        $this->actingAs($this->admin)->postJson($url, $input)->assertOk()->assertJsonPath('data.status', 'authorized');
        $this->postJson($url, $input)->assertOk();
        $this->assertSame(1, FcaReviewEvent::where('event_type', 'application_authorized')->count());
        $this->assertSame($beforeUser, $this->owner->fresh()->getAttributes());
        $this->assertSame($beforeProfile, $this->producer->fresh()->getAttributes());
        $this->assertSame('not_assessed', $this->fca->fresh()->procurement_eligibility_status);
        $this->postJson($url, array_replace($input, ['decision' => 'reject']))->assertConflict();
        $this->postJson($url, $this->decision())->assertConflict();
    }

    public function test_rejection_keeps_evidence_and_encrypted_history_and_invalid_transition_fails(): void
    {
        $application = $this->application();
        $this->actingAs($this->admin)->postJson('/api/fca/review/' . $application->id, $this->decision(['decision' => 'reject']))
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $event = FcaReviewEvent::where('event_type', 'application_rejected')->first();
        $this->assertSame('Synthetic reviewed authorization', $event->review_reason);
        $this->assertStringNotContainsString('Synthetic reviewed authorization', $event->getRawOriginal('review_reason'));
        $this->assertDatabaseCount('fca_evidence', 1);
        Storage::disk('fca_evidence')->assertExists(FcaEvidence::first()->storage_key);
        $this->postJson('/api/fca/review/' . $application->id, $this->decision(['expected_version' => 1]))->assertConflict();
    }

    public function test_stale_review_and_forged_reviewer_fields_are_rejected(): void
    {
        $application = $this->application();
        $this->actingAs($this->admin)->postJson('/api/fca/review/' . $application->id, $this->decision(['expected_version' => 9]))->assertConflict();
        $this->postJson('/api/fca/review/' . $application->id, $this->decision(['reviewed_by' => $this->owner->user_id]))->assertUnprocessable();
        $this->postJson('/api/fca/review/' . $application->id, $this->decision(['evidence_receipt' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_corrupt_or_traversal_storage_reference_cannot_be_downloaded_or_approved(): void
    {
        $application = $this->application();
        $file = FcaEvidence::first();
        Storage::disk('fca_evidence')->put($file->storage_key, 'corrupted');
        $this->actingAs($this->admin)->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertForbidden();
        $this->assertSame('integrity_failure', FcaReviewEvent::where('event_type', 'evidence_access_denied')->latest('id')->first()->reason_category);
        $this->postJson('/api/fca/review/' . $application->id, $this->decision())->assertStatus(503);
        $file->forceFill(['storage_key' => '../private-system-file'])->save();
        $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertForbidden();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_partial_write_failure_rolls_back_database_and_cleans_only_new_uncommitted_file(): void
    {
        $this->app->instance(FcaEvidenceStorage::class, new class extends FcaEvidenceStorage {
            public function write(string $key, string $bytes, bool &$created): void
            {
                parent::write($key, $bytes, $created);
                throw new RuntimeException('Synthetic write failure');
            }
        });
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        $this->assertDatabaseCount('fca_evidence', 0);
        $this->assertDatabaseCount('fca_review_events', 0);
        $this->assertSame([], Storage::disk('fca_evidence')->allFiles());
    }

    public function test_cleanup_failure_retains_orphan_for_private_reconciliation(): void
    {
        $this->app->instance(FcaEvidenceStorage::class, new class extends FcaEvidenceStorage {
            public function write(string $key, string $bytes, bool &$created): void
            {
                parent::write($key, $bytes, $created);
                throw new RuntimeException('Synthetic storage failure');
            }
            public function removeUncommitted(string $key): void { throw new RuntimeException('Synthetic cleanup failure'); }
        });
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        $this->assertDatabaseCount('fca_evidence', 0);
        $this->assertCount(1, Storage::disk('fca_evidence')->allFiles());
    }

    public function test_existing_file_is_not_overwritten_or_cleaned_up_when_uuid_collides(): void
    {
        $uuid = '4c46418a-1bb5-4f39-a988-541231e7a8ad';
        $key = 'evidence/' . $uuid . '.bin';
        Storage::disk('fca_evidence')->put($key, 'existing synthetic preservation marker');
        Str::createUuidsUsing(fn () => $uuid);
        try {
            $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        } finally {
            Str::createUuidsNormally();
        }
        $this->assertSame('existing synthetic preservation marker', Storage::disk('fca_evidence')->get($key));
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    public function test_web_accessible_storage_is_refused_without_writing_evidence(): void
    {
        Storage::set('fca_evidence', Storage::disk('public'));
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    public function test_inactive_or_unassigned_reviewer_cannot_decide_and_list_never_exposes_evidence(): void
    {
        $application = $this->application();
        $this->actingAs($this->admin)->getJson('/api/fca/review/pending')->assertOk()
            ->assertJsonMissingPath('data.0.authorization_evidence_reference')
            ->assertJsonMissingPath('data.0.submitted_details');
        $this->admin->update(['is_active' => false]);
        $this->actingAs($this->admin->fresh())->postJson('/api/fca/review/' . $application->id, $this->decision())->assertForbidden();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_legacy_nonproducer_profile_does_not_gain_application_privileges(): void
    {
        $legacy = Producer::create(['user_id' => $this->admin->user_id, 'verification_status' => 'Pending']);
        $before = $legacy->fresh()->getAttributes();
        $this->actingAs($this->admin)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertForbidden();
        $this->assertSame($before, $legacy->fresh()->getAttributes());
    }

    public function test_platform_approved_unverified_rsbsa_remains_separate_from_representation(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->string('account_review_status')->nullable();
            $table->string('rsbsa_verification_status')->nullable();
        });
        $this->producer->forceFill(['verification_status' => 'Pending', 'account_review_status' => 'active', 'rsbsa_verification_status' => 'not_verified'])->save();
        $application = $this->application();
        $this->assertSame('pending', $application->status);
        $this->assertSame('not_verified', $this->producer->fresh()->rsbsa_verification_status);
        $this->assertFalse(config('producer_access.phase2_enabled'));
    }

    public function test_pending_document_quota_prevents_unbounded_storage_without_deleting_existing_files(): void
    {
        for ($i = 0; $i < 5; $i++) $this->receipt();
        $this->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(429);
        $this->assertDatabaseCount('fca_evidence', 5);
        $this->assertCount(5, Storage::disk('fca_evidence')->allFiles());
    }

    public function test_populated_additive_schema_cannot_be_rolled_back_and_erase_evidence(): void
    {
        $application = $this->application();
        $migration = require database_path('prepared/fca/2026_10_10_000004_prepare_fca_application_evidence.php');
        try {
            $migration->down();
            $this->fail('Populated schema rollback was allowed');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Rollback refused', $exception->getMessage());
        }
        $this->assertDatabaseCount('fca_evidence', 1);
        $this->assertTrue(Schema::hasColumn('fca_representatives', 'submitted_details'));
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_authorization_rechecks_evidence_owner_even_after_attachment(): void
    {
        $application = $this->application();
        FcaEvidence::first()->forceFill(['uploaded_by' => $this->admin->user_id])->save();
        $this->actingAs($this->admin)->postJson('/api/fca/review/' . $application->id, $this->decision())->assertUnprocessable();
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertSame(0, $application->fresh()->row_version);
    }

    public function test_missing_image_decoder_rejects_images_safely(): void
    {
        if (function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('This compatibility test specifically covers a missing decoder.');
        }
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY3sAAAAASUVORK5CYII=');
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', [
            'evidence' => UploadedFile::fake()->createWithContent('proof.png', $png),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    public function test_missing_storage_root_is_not_automatically_created(): void
    {
        $missing = storage_path('framework/testing/disks/fca-not-created-' . Str::uuid());
        config(['filesystems.disks.fca_evidence.root' => $missing]);
        Storage::forgetDisk('fca_evidence');
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        $this->assertDirectoryDoesNotExist($missing);
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    public function test_scanner_failure_quarantines_but_infected_result_stores_nothing(): void
    {
        $scanner = new class extends FcaEvidenceScanner {
            public bool $outage = true;
            public function inspect(string $bytes, string $mime): string
            {
                if ($this->outage) throw new RuntimeException('Synthetic scanner outage');
                return 'infected';
            }
        };
        $this->app->instance(FcaEvidenceScanner::class, $scanner);
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])
            ->assertCreated()->assertJsonPath('status', 'quarantined');
        $scanner->outage = false;
        $this->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertUnprocessable();
        $this->assertDatabaseCount('fca_evidence', 1);
        $this->assertCount(1, Storage::disk('fca_evidence')->allFiles());
    }

    public function test_revocation_keeps_history_and_can_be_reviewed_after_applicant_inactivation(): void
    {
        $application = $this->application();
        $url = '/api/fca/review/' . $application->id;
        $this->actingAs($this->admin)->postJson($url, $this->decision())->assertOk();
        $this->owner->update(['is_active' => false]);
        $input = $this->decision(['decision' => 'revoke', 'expected_version' => 1]);
        $this->postJson($url, $input)->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->postJson($url, $input)->assertOk();
        $this->assertSame(1, FcaReviewEvent::where('event_type', 'application_revoked')->count());
        $this->assertNotNull($application->fresh()->revoked_at);
        $this->assertDatabaseCount('fca_evidence', 1);
    }

    public function test_failed_review_audit_rolls_back_decision_instead_of_authorizing_without_history(): void
    {
        $application = $this->application();
        FcaReviewEvent::creating(fn ($event) => $event->event_type === 'application_authorized' ? false : null);
        try {
            $this->actingAs($this->admin)->postJson('/api/fca/review/' . $application->id, $this->decision())->assertStatus(503);
        } finally {
            FcaReviewEvent::flushEventListeners();
        }
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertSame(0, $application->fresh()->row_version);
        $this->assertSame(0, FcaReviewEvent::where('event_type', 'application_authorized')->count());
    }

    public function test_failed_upload_audit_rolls_back_metadata_and_removes_only_new_file(): void
    {
        FcaReviewEvent::creating(fn ($event) => $event->event_type === 'evidence_uploaded' ? false : null);
        try {
            $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        } finally {
            FcaReviewEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('fca_evidence', 0);
        $this->assertDatabaseCount('fca_review_events', 0);
        $this->assertSame([], Storage::disk('fca_evidence')->allFiles());
    }

    public function test_reconciliation_reports_corruption_and_missing_files_without_changing_records(): void
    {
        $this->application();
        $file = FcaEvidence::first();
        $before = $file->getAttributes();
        $events = FcaReviewEvent::count();
        Storage::disk('fca_evidence')->put($file->storage_key, 'synthetic corruption');
        $service = app(\App\Services\Mao\FcaEvidenceGovernance::class);
        $report = $service->dryRun($this->admin);
        $this->assertContains('INTEGRITY_MISMATCH', $report['rows'][0]['codes']);
        $this->assertFalse($report['mutated']);
        $this->assertStringNotContainsString($file->storage_key, json_encode($report));
        Storage::disk('fca_evidence')->delete($file->storage_key); // Synthetic test fault only.
        $report = $service->dryRun($this->admin);
        $this->assertContains('FILE_MISSING_OR_UNSAFE', $report['rows'][0]['codes']);
        $this->assertSame($before, $file->fresh()->getAttributes());
        $this->assertSame($events, FcaReviewEvent::count());
    }

    public function test_unapproved_receipt_retention_policy_prevents_new_uploads(): void
    {
        config(['fca_evidence.receipt_ttl_hours' => null]);
        $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertStatus(503);
        $this->assertDatabaseCount('fca_evidence', 0);
    }

    private function pendingAttempt(): \App\Models\FcaInspectionAttempt
    {
        $receipt = $this->actingAs($this->owner)->postJson('/api/fca/evidence', ['evidence' => $this->document()])->assertCreated()->json('receipt');
        $file = FcaEvidence::find($receipt);
        return app(\App\Services\Mao\FcaInspectionLedger::class)->begin($this->admin, $receipt,
            $file->evidence_version, $file->sha256, (string) Str::uuid());
    }

    public function test_durable_attempts_are_idempotent_and_partial_attempts_never_allow_access(): void
    {
        $attempt = $this->pendingAttempt();
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        $same = $ledger->begin($this->admin, $attempt->evidence_id, $attempt->evidence_version, $attempt->input_sha256, $attempt->operation_key);
        $this->assertSame($attempt->id, $same->id);
        $this->assertDatabaseCount('fca_inspection_attempts', 1);
        $file = FcaEvidence::find($attempt->evidence_id);
        $this->assertFalse($file->hasSuccessfulInspection());
        $this->getJson('/api/fca/evidence/' . $file->id)->assertForbidden();
        $finished = $ledger->complete($this->admin, $attempt->id);
        $this->assertSame('successful', $finished->status);
        $this->assertTrue($file->fresh()->hasSuccessfulInspection());
        $ledger->complete($this->admin, $attempt->id);
        $this->assertSame(1, FcaReviewEvent::where('event_type', 'inspection_completed')->count());
    }

    public function test_duplicate_processing_and_stale_evidence_are_rejected(): void
    {
        $attempt = $this->pendingAttempt();
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        try {
            $ledger->begin($this->admin, $attempt->evidence_id, 1, $attempt->input_sha256, (string) Str::uuid());
            $this->fail('Concurrent attempt accepted');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        FcaEvidence::find($attempt->evidence_id)->forceFill(['evidence_version' => 2])->save();
        try { $ledger->complete($this->admin, $attempt->id); $this->fail('Stale result accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertFalse(FcaEvidence::find($attempt->evidence_id)->hasSuccessfulInspection());
    }

    public function test_unavailable_inspection_and_explicit_interruption_preserve_retry_history(): void
    {
        $attempt = $this->pendingAttempt();
        $this->app->instance(\App\Services\Mao\Inspection\DocumentInspector::class, new \App\Services\Mao\Inspection\DocumentInspector());
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        $done = $ledger->complete($this->admin, $attempt->id);
        $this->assertSame('unavailable', $done->status);
        $file = FcaEvidence::find($attempt->evidence_id);
        $this->assertFalse($file->hasSuccessfulInspection());
        $retry = $ledger->begin($this->admin, $file->id, 1, $file->sha256, (string) Str::uuid(), $attempt->id);
        $ledger->interrupt($this->admin, $retry->id);
        $this->assertSame('interrupted', $retry->fresh()->error_category);
        $this->assertSame($attempt->id, $retry->retry_of_id);
        $this->assertDatabaseCount('fca_inspection_attempts', 2);
    }

    public function test_scanner_errors_and_changed_bytes_cannot_fabricate_success(): void
    {
        $attempt = $this->pendingAttempt();
        $malware = new class implements \App\Services\Mao\Inspection\MalwareProvider {
            public function inspect(string $bytes, string $mime): string { throw new RuntimeException('SENSITIVE provider diagnostic'); }
        };
        $pdf = new class implements \App\Services\Mao\Inspection\PdfInspector {
            public function inspect(string $bytes): string { return 'clean'; }
        };
        $this->app->instance(\App\Services\Mao\Inspection\DocumentInspector::class, new \App\Services\Mao\Inspection\DocumentInspector($malware, $pdf));
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        $result = $ledger->complete($this->admin, $attempt->id);
        $this->assertSame('failed', $result->status);
        $this->assertStringNotContainsString('SENSITIVE', json_encode($result->getAttributes()));
        $file = FcaEvidence::find($attempt->evidence_id);
        $retry = $ledger->begin($this->admin, $file->id, 1, $file->sha256, (string) Str::uuid(), $attempt->id);
        Storage::disk('fca_evidence')->put($file->storage_key, 'changed synthetic bytes');
        $result = $ledger->complete($this->admin, $retry->id);
        $this->assertSame('rejected', $result->status);
        $this->assertSame('integrity_failure', $result->error_category);
    }

    public function test_inspection_audit_failure_rolls_back_success_and_keeps_quarantine(): void
    {
        $attempt = $this->pendingAttempt();
        FcaReviewEvent::creating(fn ($event) => $event->event_type === 'inspection_completed' ? false : null);
        try {
            app(\App\Services\Mao\FcaInspectionLedger::class)->complete($this->admin, $attempt->id);
            $this->fail('Unaudited result committed');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        } finally { FcaReviewEvent::flushEventListeners(); }
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('quarantined', FcaEvidence::find($attempt->evidence_id)->status);
    }

    public function test_access_audit_failure_prevents_bytes_and_rejected_application_blocks_access(): void
    {
        $application = $this->application();
        FcaReviewEvent::creating(fn ($event) => $event->event_type === 'evidence_access_allowed' ? false : null);
        try { $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertStatus(503); }
        finally { FcaReviewEvent::flushEventListeners(); }
        $this->actingAs($this->admin)->postJson('/api/fca/review/' . $application->id, $this->decision(['decision' => 'reject']))->assertOk();
        $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertForbidden();
        $this->assertSame('denied', FcaReviewEvent::where('event_type', 'evidence_access_denied')->latest('id')->first()->authorization_decision);
    }

    public function test_separate_access_flag_disables_downloads_even_with_successful_audit_history(): void
    {
        $application = $this->application();
        config(['mao_workflows.fca_evidence_access_enabled' => false]);
        $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertNotFound();
        config(['mao_workflows.fca_inspection_persistence_enabled' => false]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->inspectReceipt(FcaEvidence::first()->id);
    }

    public function test_release_failure_is_audited_and_existing_audit_is_not_release_authority(): void
    {
        $application = $this->application();
        FcaReviewEvent::creating(fn ($event) => $event->event_type === 'evidence_downloaded' ? false : null);
        try { $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertStatus(503); }
        finally { FcaReviewEvent::flushEventListeners(); }
        $this->assertSame(1, FcaReviewEvent::where('event_type', 'evidence_release_failed')->count());
        $this->actingAs($this->admin)->postJson('/api/fca/review/' . $application->id, $this->decision())->assertOk();
        $this->postJson('/api/fca/review/' . $application->id, $this->decision(['decision' => 'revoke', 'expected_version' => 1]))->assertOk();
        $this->getJson('/api/fca/applications/' . $application->id . '/evidence')->assertForbidden();
        $this->assertSame('denied', FcaReviewEvent::where('event_type', 'evidence_access_denied')->latest('id')->first()->authorization_decision);
    }

    public function test_inspection_history_blocks_populated_rollback_and_unauthorized_worker(): void
    {
        $attempt = $this->pendingAttempt();
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        try { $ledger->complete($this->owner, $attempt->id); $this->fail('Unauthorized worker allowed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $migration = require database_path('prepared/fca/2026_10_10_000005_prepare_fca_inspection_history.php');
        try { $migration->down(); $this->fail('Inspection history deleted'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('Rollback refused', $e->getMessage()); }
        $this->assertDatabaseCount('fca_inspection_attempts', 1);
    }

    public function test_missing_evidence_denial_is_preserved_and_blocks_audit_column_rollback(): void
    {
        $ledger = app(\App\Services\Mao\FcaInspectionLedger::class);
        $this->assertFalse($ledger->access($this->owner, (string) Str::uuid(), (string) Str::uuid()));
        $event = FcaReviewEvent::first();
        $this->assertNull($event->evidence_id);
        $this->assertSame('missing_evidence', $event->reason_category);
        $this->assertSame('denied', $event->authorization_decision);
        $migration = require database_path('prepared/fca/2026_10_10_000005_prepare_fca_inspection_history.php');
        try { $migration->down(); $this->fail('Denied-access history removed'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('Rollback refused', $e->getMessage()); }
        $this->assertNotNull($event->fresh()->correlation_id);
    }
}
