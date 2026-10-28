<?php

namespace Tests\Feature;

use App\Models\FcaEvidence;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\Backup\ArchiveCipher;
use App\Services\Backup\BackupManager;
use App\Services\Backup\DatabaseSnapshot;
use App\Services\Backup\UploadCoverage;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Backup tests require isolated SQLite memory.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->root = storage_path('app/private/backup-test-'.Str::uuid());
        mkdir($this->root, 0700, true);
        mkdir($this->root.'/fixtures', 0700);
        file_put_contents($this->root.'/fixtures/product.txt', 'synthetic uploaded product');
        config(['backup.enabled' => true, 'backup.storage_permissions_approved' => true, 'backup.root' => $this->root.'/archives', 'backup.key' => base64_encode(random_bytes(32)), 'backup.file_roots' => ['uploads' => $this->root.'/fixtures'], 'backup.min_free_bytes' => 0, 'backup.secondary_root' => null]);
    }

    protected function tearDown(): void
    {
        // The generated absolute target is checked before recursive fixture cleanup.
        if (isset($this->root) && dirname($this->root) === storage_path('app/private') && str_starts_with(basename($this->root), 'backup-test-')) {
            File::deleteDirectory($this->root);
        }
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }

    private function user(string $role = 'Super Admin', bool $active = true): User
    {
        return User::create(['first_name' => 'Synthetic', 'last_name' => 'Backup', 'email' => Str::uuid().'@example.test', 'user_type' => $role, 'password_hash' => bcrypt('test-password'), 'is_active' => $active]);
    }

    public function test_snapshot_encrypts_verifies_and_restores_isolated_database_and_files(): void
    {
        $user = $this->user();
        $manager = app(BackupManager::class);
        $record = $manager->create($user->user_id);
        $this->assertSame('completed', $record['status']);
        $this->assertSame('passed', $record['verification_status']);
        $archive = $this->root.'/archives/'.$record['id'].'.hbk';
        $this->assertStringNotContainsString('synthetic uploaded product', file_get_contents($archive));
        $this->assertStringNotContainsString($user->email, file_get_contents($archive));
        $drilled = $manager->drill($record['id'], $user->user_id);
        $this->assertSame('database and file checks passed', $drilled['drill']['status']);
        $this->assertSame(1, $drilled['drill']['files_verified']);
        $this->assertSame(1, $drilled['drill']['database']['row_counts']['users']);
        $this->assertSame('not performed', $drilled['drill']['application_qa']);
        $this->assertDatabaseHas('users', ['user_id' => $user->user_id, 'email' => $user->email]);
        $this->assertSame([], glob($this->root.'/archives/work-*'));
    }

    public function test_downloaded_archive_can_be_imported_and_drilled_without_the_original_journal(): void
    {
        $this->user();
        $manager = app(BackupManager::class);
        $record = $manager->create();
        $downloaded = $this->root.'/downloaded.hbk';
        copy($this->root.'/archives/'.$record['id'].'.hbk', $downloaded);
        config(['backup.root' => $this->root.'/replacement-archives']);
        $imported = $manager->import($downloaded);
        $this->assertSame($record['id'], $imported['id']);
        $this->assertSame('passed', $imported['verification_status']);
        $this->assertSame('database and file checks passed', $manager->drill($imported['id'])['drill']['status']);
        $this->expectException(\RuntimeException::class);
        $manager->import($downloaded);
    }

    public function test_tampering_fails_integrity_and_notifies_only_active_super_admin(): void
    {
        $super = $this->user();
        $this->user('Admin');
        $this->user('Super Admin', false);
        $manager = app(BackupManager::class);
        $record = $manager->create();
        file_put_contents($this->root.'/archives/'.$record['id'].'.hbk', 'tampered', FILE_APPEND);
        try {
            $manager->verify($record['id']);
            $this->fail('Tampering must fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('Backup integrity verification failed.', $e->getMessage());
        }
        $this->assertSame('failed', $manager->history()[0]['verification_status']);
        $this->assertDatabaseCount('transaction_notifications', 1);
        $this->assertDatabaseHas('transaction_notifications', ['recipient_user_id' => $super->user_id]);
    }

    public function test_cipher_rejects_wrong_key_truncation_and_record_reordering(): void
    {
        $cipher = app(ArchiveCipher::class);
        $source = $this->root.'/source';
        file_put_contents($source, str_repeat('S', 2200000));
        $encrypted = $this->root.'/encrypted';
        $cipher->encrypt($source, $encrypted);
        $bytes = file_get_contents($encrypted);
        $cipher->decrypt($encrypted, $this->root.'/valid-decrypted');
        $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $this->root.'/valid-decrypted'));
        $headerLength = strlen("HARBOR-BACKUP-1\n") + 8;
        $recordLength = 1048576 + 20;
        $reordered = substr($bytes, 0, $headerLength).substr($bytes, $headerLength + $recordLength, $recordLength).substr($bytes, $headerLength, $recordLength).substr($bytes, $headerLength + 2 * $recordLength);
        foreach ([$reordered, substr($bytes, 0, -1), $bytes.'extra', substr_replace($bytes, 'x', 70, 1)] as $i => $corrupt) {
            $bad = $this->root.'/bad-'.$i;
            file_put_contents($bad, $corrupt);
            try {
                $cipher->decrypt($bad, $this->root.'/decrypted-'.$i);
                $this->fail('Corrupt archive accepted');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        config(['backup.key' => base64_encode(random_bytes(32))]);
        $this->expectException(\RuntimeException::class);
        $cipher->decrypt($encrypted, $this->root.'/wrong-key');
    }

    public function test_retention_preserves_last_verified_point_and_unrelated_files(): void
    {
        $manager = app(BackupManager::class);
        $old = $manager->create();
        $this->travel(1)->seconds();
        $new = $manager->create();
        foreach ([$old, $new] as $row) {
            $file = $this->root.'/archives/'.$row['id'].'.json';
            $data = json_decode(file_get_contents($file), true);
            $data['started_at'] = now()->subDays($row['id'] === $old['id'] ? 30 : 29)->toIso8601String();
            file_put_contents($file, json_encode($data));
        }
        file_put_contents($this->root.'/archives/unrelated.txt', 'preserve');
        $this->assertSame(1, $manager->retain());
        $this->assertFileDoesNotExist($this->root.'/archives/'.$old['id'].'.hbk');
        $this->assertFileExists($this->root.'/archives/'.$new['id'].'.hbk');
        $this->assertFileExists($this->root.'/archives/unrelated.txt');
    }

    public function test_snapshot_barrier_rejects_mutations_without_writing_and_releases_after_snapshot(): void
    {
        $manager = app(BackupManager::class);
        $lock = $manager->snapshotLock(true);
        try {
            $this->postJson('/api/register', [])->assertStatus(503)->assertHeader('Retry-After', '10');
            $this->assertDatabaseCount('users', 0);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->postJson('/api/register', [])->assertUnprocessable();
    }

    public function test_lock_prevents_overlapping_operations(): void
    {
        $manager = app(BackupManager::class);
        $lock = fopen($manager->root().'/operation.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $manager->create();
            $this->fail('Concurrent operation accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Another backup operation', $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_api_denies_other_roles_and_requires_password_before_download_or_restore(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->getJson('/api/superadmin/backups')->assertUnauthorized();
        foreach (['Admin', 'Buyer', 'Farmer'] as $role) {
            $this->actingAs($this->user($role), 'sanctum')->getJson('/api/superadmin/backups')->assertForbidden();
            $this->postJson('/api/superadmin/backups')->assertForbidden();
        }
        $this->actingAs($this->user('Super Admin', false), 'sanctum')->getJson('/api/superadmin/backups')->assertForbidden();
        $super = $this->user();
        $this->actingAs($super, 'sanctum')->getJson('/api/superadmin/backups')->assertOk()->assertJsonPath('verified_points', 0);
        $id = (string) Str::uuid();
        $this->postJson('/api/superadmin/backups/'.$id.'/download', ['password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/superadmin/backups/'.$id.'/drill', [])->assertUnprocessable();
    }

    public function test_encrypted_download_requires_reauthentication_and_is_audited(): void
    {
        $super = $this->user();
        $record = app(BackupManager::class)->create();
        $this->actingAs($super, 'sanctum')->postJson('/api/superadmin/backups/'.$record['id'].'/download', ['password' => 'test-password'])->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('encrypted_download', file_get_contents($this->root.'/archives/audit.jsonl'));
    }

    public function test_backup_failure_is_recorded_and_private_workspaces_are_removed(): void
    {
        $super = $this->user();
        $this->mock(DatabaseSnapshot::class, function ($mock) {
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('private database error'));
        });
        $manager = app(BackupManager::class);
        try {
            $manager->create();
            $this->fail('Failure reported as success');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('private database error', $e->getMessage());
        }
        $this->assertSame('failed', $manager->history()[0]['status']);
        $this->assertSame([], glob($this->root.'/archives/work-*'));
        $this->assertDatabaseHas('transaction_notifications', ['recipient_user_id' => $super->user_id]);
    }

    public function test_monitor_records_real_heartbeat_and_deduplicates_stale_point_notifications(): void
    {
        $this->user();
        $manager = app(BackupManager::class);
        $this->assertSame('not verified / heartbeat stale', $manager->health()['scheduler_status']);
        $manager->heartbeat();
        $manager->heartbeat();
        $this->assertSame('heartbeat observed', $manager->health()['scheduler_status']);
        $this->assertDatabaseCount('transaction_notifications', 1);
        $this->travel(11)->minutes();
        $this->assertSame('not verified / heartbeat stale', $manager->health()['scheduler_status']);
    }

    public function test_public_storage_root_and_unconfigured_recovery_are_rejected(): void
    {
        $manager = app(BackupManager::class);
        $record = $manager->create();
        config(['backup.recovery.enabled' => false]);
        $adapter = app(DatabaseSnapshot::class);
        try {
            $adapter->restoreIsolated($this->root, ['driver' => 'mysql']);
            $this->fail('Unconfigured restore accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        config(['backup.root' => public_path('unsafe-backups')]);
        $this->expectException(\RuntimeException::class);
        $manager->root();
    }

    public function test_invalid_or_reused_keys_are_rejected_before_database_staging(): void
    {
        $this->mock(DatabaseSnapshot::class, function ($mock) {
            $mock->shouldNotReceive('create');
        });
        foreach (['', substr((string) config('app.key'), 7)] as $key) {
            config(['backup.key' => $key]);
            $this->assertFalse(app(BackupManager::class)->health()['key_configured']);
            try {
                app(BackupManager::class)->create();
                $this->fail('Invalid key accepted');
            } catch (\RuntimeException) {
                $this->assertSame([], app(BackupManager::class)->history());
                $this->assertSame([], glob($this->root.'/archives/work-*'));
            }
        }
    }

    public function test_recovery_grants_refuse_wildcards_global_privileges_roles_and_delegation(): void
    {
        $adapter = app(DatabaseSnapshot::class);
        $schema = chr(96).'harborai'.chr(92).'_recovery'.chr(92).'_drill'.chr(96);
        $adapter->assertRecoveryGrants(['GRANT USAGE ON *.* TO user', 'GRANT ALL PRIVILEGES ON '.$schema.'.* TO user']);
        $this->addToAssertionCount(1);
        foreach ([
            [], ['GRANT ALL PRIVILEGES ON *.* TO user'],
            ['GRANT ALL PRIVILEGES ON '.chr(96).'harborai_recovery_drill'.chr(96).'.* TO user'],
            ['GRANT ALL PRIVILEGES ON '.$schema.'.* TO user WITH GRANT OPTION'],
            ['GRANT USAGE ON *.* TO user WITH GRANT OPTION'],
            ['GRANT recovery_role TO user'],
            ['GRANT SELECT ON '.chr(96).'other'.chr(96).'.* TO user'],
        ] as $grants) {
            try {
                $adapter->assertRecoveryGrants($grants);
                $this->fail('Unsafe grants accepted');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_sqlite_drill_refuses_an_incomplete_table_inventory(): void
    {
        $adapter = app(DatabaseSnapshot::class);
        $snapshot = $adapter->create($this->root);
        $snapshot['tables'] = ['missing_table'];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Restored table inventory mismatch.');
        $adapter->restoreIsolated($this->root, $snapshot);
    }

    public function test_latest_drill_is_selected_by_drill_time_not_backup_age(): void
    {
        $manager = app(BackupManager::class);
        $older = $manager->create();
        $this->travel(2)->seconds();
        $newer = $manager->create();
        $manager->drill($newer['id']);
        $this->travel(2)->seconds();
        $expected = $manager->drill($older['id'])['drill'];
        $this->assertSame($expected, $manager->health()['latest_drill']);
    }

    public function test_backup_routes_limit_repeated_password_attempts(): void
    {
        $this->actingAs($this->user(), 'sanctum');
        $url = '/api/superadmin/backups/'.Str::uuid().'/download';
        for ($i = 0; $i < 3; $i++) {
            $this->postJson($url, ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson($url, ['password' => 'wrong'])->assertStatus(429);
        $this->assertSame([], app(BackupManager::class)->history());
    }

    public function test_upload_coverage_rejects_missing_and_unsafe_paths(): void
    {
        $coverage = app(UploadCoverage::class);
        $reflection = new \ReflectionMethod($coverage, 'requireEntry');
        $metadata = ['sha256' => hash('sha256', 'fixture'), 'bytes' => 7];
        $this->assertSame($metadata, $reflection->invoke($coverage, ['files/uploads/product_images/a.jpg' => $metadata], 'uploads', 'product_images/a.jpg'));
        foreach (['missing.jpg', '../secret', '/absolute', 'C:/secret', 'folder'.chr(92).'alias'] as $path) {
            try {
                $reflection->invoke($coverage, [], 'uploads', $path);
                $this->fail('Missing or unsafe upload accepted');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_backup_fails_if_a_linked_product_image_is_missing_even_when_upload_root_exists(): void
    {
        $user = $this->user('Farmer');
        $producer = Producer::create(['user_id' => $user->user_id, 'rsbsa_number' => 'SYNTHETIC-COVERAGE', 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        $listing = ProductListing::create(['producer_id' => $producer->producer_id, 'product_name' => 'Synthetic Rice', 'product_category' => 'Grains', 'current_price_per_unit' => 10, 'unit_of_measure' => 'kg', 'quantity_available' => 1, 'status' => 'Active']);
        $listing->images()->create(['path' => 'missing.jpg', 'is_primary' => true]);
        $manager = app(BackupManager::class);
        try {
            $manager->create();
            $this->fail('Incomplete backup reported as successful');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $manager->history()[0]['status']);
            $this->assertSame([], glob($this->root.'/archives/*.hbk'));
        }
        file_put_contents($this->root.'/fixtures/missing.jpg', 'synthetic image');
        $record = $manager->create();
        $this->assertSame('passed', $record['verification_status']);
        $this->assertSame(1, $manager->drill($record['id'])['drill']['upload_references']['product_images']);
    }

    public function test_backup_fails_if_fca_file_does_not_match_the_database_hash(): void
    {
        $user = $this->user('Farmer');
        $producer = Producer::create(['user_id' => $user->user_id, 'rsbsa_number' => 'SYNTHETIC-EVIDENCE', 'verification_status' => 'Verified', 'producer_type' => 'Farmer']);
        mkdir($this->root.'/evidence', 0700);
        file_put_contents($this->root.'/evidence/document', 'changed');
        config(['backup.file_roots.fca-evidence' => $this->root.'/evidence']);
        FcaEvidence::unguarded(fn () => FcaEvidence::create(['id' => (string) Str::uuid(), 'uploaded_by' => $user->user_id, 'producer_id' => $producer->producer_id, 'storage_key' => 'document', 'mime' => 'application/pdf', 'bytes' => 7, 'sha256' => hash('sha256', 'initial')]));
        $manager = app(BackupManager::class);
        try {
            $manager->create();
            $this->fail('Changed evidence accepted');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $manager->history()[0]['status']);
            $this->assertSame([], glob($this->root.'/archives/*.hbk'));
        }
        file_put_contents($this->root.'/evidence/document', 'initial');
        $record = $manager->create();
        $this->assertSame(1, $manager->drill($record['id'])['drill']['upload_references']['fca_evidence']);
    }

    public function test_authentic_archive_without_database_payload_fails_verification(): void
    {
        $manager = app(BackupManager::class);
        $record = $manager->create();
        $archive = $this->root.'/archives/'.$record['id'].'.hbk';
        $zipPath = $this->root.'/altered.zip';
        app(ArchiveCipher::class)->decrypt($archive, $zipPath);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        unset($manifest['entries']['database.sqlite']);
        $zip->deleteName('database.sqlite');
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();
        app(ArchiveCipher::class)->encrypt($zipPath, $this->root.'/altered.hbk');
        file_put_contents($archive, file_get_contents($this->root.'/altered.hbk'));
        $journal = $this->root.'/archives/'.$record['id'].'.json';
        $record['sha256'] = hash_file('sha256', $archive);
        $record['bytes'] = filesize($archive);
        file_put_contents($journal, json_encode($record));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backup integrity verification failed.');
        $manager->verify($record['id']);
    }

    public function test_terminal_parent_path_cannot_escape_private_backup_root(): void
    {
        config(['backup.root' => storage_path('app/private/..')]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backup root must be below private application storage.');
        app(BackupManager::class)->root();
    }
}
