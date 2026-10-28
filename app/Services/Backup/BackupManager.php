<?php

namespace App\Services\Backup;

use App\Models\FcaEvidence;
use App\Models\ProductListingImage;
use App\Models\TransactionNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Private filesystem journal avoids schema changes. All archive operations share one lock. */
class BackupManager
{
    public function __construct(private ArchiveCipher $cipher, private DatabaseSnapshot $database) {}

    public function root(): string
    {
        $root = rtrim((string) config('backup.root'), '/\\');
        $normal = static fn ($p) => strtolower(str_replace('\\', '/', $p));
        $private = $normal(storage_path('app/private'));
        if (! str_starts_with($normal($root), $private.'/') || preg_match('~(^|/)\.\.?(/|$)~', $normal($root))) {
            throw new RuntimeException('Backup root must be below private application storage.');
        }
        for ($p = $root; dirname($p) !== $p; $p = dirname($p)) {
            if (is_link($p)) {
                throw new RuntimeException('Backup storage aliases are forbidden.');
            }
        }
        if (! is_dir($root) && ! mkdir($root, 0700, true)) {
            throw new RuntimeException('Private backup storage unavailable.');
        }
        if (! is_writable($root)) {
            throw new RuntimeException('Private backup storage is not writable.');
        }

        return $root;
    }

    public function snapshotLock(bool $exclusive)
    {
        $handle = fopen($this->root().'/snapshot.lock', 'c');
        if (! $handle || ! flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Application writes or snapshot staging are in progress. Retry shortly.');
        }

        return $handle;
    }

    private function locked(callable $operation): mixed
    {
        $handle = fopen($this->root().'/operation.lock', 'c');
        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            } throw new RuntimeException('Another backup operation is running. Try again later.');
        }
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function id(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new RuntimeException('Invalid backup identifier.');
        }

        return strtolower($id);
    }

    private function record(string $id, array $record): void
    {
        $path = $this->root().'/'.$this->id($id).'.json';
        $temp = $path.'.'.Str::uuid().'.tmp';
        if (file_put_contents($temp, json_encode($record, JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Cannot persist backup journal.');
        }
        chmod($temp, 0600);
        if (! rename($temp, $path)) {
            throw new RuntimeException('Cannot publish backup journal.');
        }
    }

    private function get(string $id): array
    {
        $path = $this->root().'/'.$this->id($id).'.json';
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Backup record unavailable.');
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function history(): array
    {
        $rows = [];
        foreach (glob($this->root().'/*.json') ?: [] as $file) {
            if (! Str::isUuid(basename($file, '.json')) || is_link($file)) {
                continue;
            }
            $rows[] = $this->get(basename($file, '.json'));
        }
        usort($rows, fn ($a, $b) => strcmp($b['started_at'], $a['started_at']));

        return $rows;
    }

    public function health(): array
    {
        $rows = $this->history();
        $success = collect($rows)->first(fn ($r) => $r['status'] === 'completed' && empty($r['deleted_at']));
        $verified = collect($rows)->filter(fn ($r) => ! empty($r['verified_at']) && ($r['verification_status'] ?? '') === 'passed' && empty($r['deleted_at']) && $this->artifactPresent($r))->values()->all();
        $lastVerified = $verified[0] ?? null;
        $heartbeat = $this->root().'/scheduler-heartbeat';
        $heartbeatAt = is_file($heartbeat) ? trim(file_get_contents($heartbeat)) : null;
        $schedulerHealthy = $heartbeatAt && now()->diffInMinutes(Carbon::parse($heartbeatAt), true) < 10;
        $next = now('Asia/Manila')->setTimeFromTimeString(config('backup.time'));
        if ($next->isPast()) {
            $next->addDay();
        }
        $bytes = 0;
        foreach (glob($this->root().'/*.hbk') ?: [] as $file) {
            if (! is_link($file)) {
                $bytes += filesize($file);
            }
        }

        try {
            $this->cipher->validateKey();
            $keyConfigured = true;
        } catch (RuntimeException) {
            $keyConfigured = false;
        }

        return [
            'enabled' => (bool) config('backup.enabled'),
            'storage_permissions_approved' => (bool) config('backup.storage_permissions_approved'),
            'key_configured' => $keyConfigured,
            'latest_success' => $success['completed_at'] ?? null,
            'latest_attempt' => $rows[0] ?? null,
            'next_scheduled' => config('backup.enabled') ? $next->toIso8601String() : null,
            'schedule' => config('backup.time').' Asia/Manila daily',
            'scheduler_status' => $schedulerHealthy ? 'heartbeat observed' : 'not verified / heartbeat stale',
            'scheduler_heartbeat' => $heartbeatAt,
            'verified_points' => count($verified),
            'rpo_met' => $lastVerified ? now()->diffInHours(Carbon::parse($lastVerified['started_at']), true) <= config('backup.rpo_hours') : false,
            'storage_bytes' => $bytes, 'free_bytes' => disk_free_space($this->root()),
            'local_status' => 'private storage writable; deployment ACL review required',
            'secondary_status' => config('backup.secondary_root') ? 'configured; see per-backup copy outcome' : 'Not Configured',
            'latest_drill' => collect($rows)->pluck('drill')->filter()->sortByDesc('at')->first(),
            'history' => array_slice($rows, 0, 100),
            'audit' => $this->audits(),
            'isolated_recovery_configured' => (bool) config('backup.recovery.enabled'),
            'retention_days' => max(1, (int) config('backup.retention_days')),
            'targets' => ['rpo_hours' => 24, 'rto_hours' => 8, 'status' => 'proposed; operational compliance unproven'],
        ];
    }

    public function create(?int $actor = null, string $trigger = 'manual'): array
    {
        return $this->locked(function () use ($actor, $trigger) {
            if (! config('backup.enabled')) {
                throw new RuntimeException('Backups are disabled until configured.');
            }
            if (! config('backup.storage_permissions_approved')) {
                throw new RuntimeException('Private storage permissions require operator verification before backups can run.');
            }
            $this->cipher->validateKey();
            if (disk_free_space($this->root()) < config('backup.min_free_bytes')) {
                $this->alert('Backup storage is below the configured free-space threshold.');
                throw new RuntimeException('Insufficient backup storage.');
            }
            $id = (string) Str::uuid();
            $work = $this->workspace();
            $record = ['id' => $id, 'trigger' => $trigger, 'actor_id' => $actor, 'started_at' => now()->toIso8601String(), 'status' => 'running', 'verification_status' => 'not run', 'secondary_status' => 'Not Configured'];
            $this->record($id, $record);
            $this->audit('backup_started', $id, $actor);
            try {
                $snapshotLock = $this->snapshotLock(true);
                $snapshot = $this->database->create($work);
                $zip = new ZipArchive;
                if ($zip->open($work.'/archive.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                    throw new RuntimeException('Cannot create backup container.');
                }
                $manifest = ['format' => 1, 'id' => $id, 'created_at' => $record['started_at'], 'application' => 'Harbor AI', 'laravel' => app()->version(), 'source_hashes' => ['composer.lock' => hash_file('sha256', base_path('composer.lock')), 'package-lock.json' => hash_file('sha256', base_path('package-lock.json'))], 'database' => $snapshot, 'entries' => [], 'file_roots' => [], 'consistency' => 'Database snapshot and staged file copies under a cooperating API write barrier. External/CLI writers must be stopped by the operator.'];
                try {
                    $this->addFile($zip, $work.'/'.$snapshot['entry'], $snapshot['entry'], $manifest);
                    $roots = config('backup.file_roots', []);
                    if (array_key_exists('fca-evidence', $roots) && ! $roots['fca-evidence']) {
                        $roots['fca-evidence'] = config('filesystems.disks.fca_evidence.root');
                    }
                    if (FcaEvidence::query()->exists() && empty($roots['fca-evidence'])) {
                        throw new RuntimeException('Existing FCA evidence has no configured recoverable file root.');
                    }
                    foreach ($roots as $label => $root) {
                        if (! preg_match('/^[a-z0-9-]+$/', $label)) {
                            throw new RuntimeException('Invalid backup file-root label.');
                        }
                        if (! $root || ! is_dir($root)) {
                            if ($label === 'fca-evidence' && FcaEvidence::query()->exists()) {
                                throw new RuntimeException('FCA evidence coverage is unavailable.');
                            }
                            if ($label === 'uploads' && ProductListingImage::query()->exists()) {
                                throw new RuntimeException('Product image coverage is unavailable.');
                            }
                            $manifest['file_roots'][$label] = 'not present';

                            continue;
                        }
                        $real = realpath($root);
                        for ($ancestor = $root; dirname($ancestor) !== $ancestor; $ancestor = dirname($ancestor)) {
                            if (is_link($ancestor)) {
                                throw new RuntimeException('Upload root aliases cannot be archived.');
                            }
                        }
                        if (! $real || is_link($root) || str_starts_with(strtolower(str_replace('\\', '/', $real)), strtolower(str_replace('\\', '/', $this->root())))) {
                            throw new RuntimeException('Unsafe upload root.');
                        }
                        $manifest['file_roots'][$label] = 'included';
                        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS));
                        foreach ($iterator as $file) {
                            if ($file->isLink()) {
                                throw new RuntimeException('Upload aliases cannot be archived.');
                            }
                            if (! $file->isFile()) {
                                continue;
                            }
                            $entry = 'files/'.$label.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($real) + 1));
                            if (preg_match('~(^|/)(\.env[^/]*|mysql-client\.cnf|source-checkpoints)(/|$)~i', $entry)) {
                                throw new RuntimeException('Secret or checkpoint file found in upload coverage.');
                            }
                            // Stage immutable copies; a change during copy fails instead of claiming consistency.
                            $staged = $work.'/staged/'.count($manifest['entries']);
                            if (! is_dir(dirname($staged))) {
                                mkdir(dirname($staged), 0700, true);
                            }
                            $before = hash_file('sha256', $file->getPathname());
                            if (! copy($file->getPathname(), $staged) || $before !== hash_file('sha256', $staged) || $before !== hash_file('sha256', $file->getPathname())) {
                                throw new RuntimeException('An uploaded file changed during backup. Retry during a quiet period.');
                            }
                            chmod($staged, 0600);
                            $this->addFile($zip, $staged, $entry, $manifest);
                        }
                    }
                    $manifest['upload_references'] = app(UploadCoverage::class)->verify($manifest['entries']);
                    if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR))) {
                        throw new RuntimeException('Cannot write backup manifest.');
                    }
                } finally {
                    if (! $zip->close()) {
                        throw new RuntimeException('Cannot finalize backup container.');
                    }
                }
                flock($snapshotLock, LOCK_UN);
                fclose($snapshotLock);
                $snapshotLock = null;
                chmod($work.'/archive.zip', 0600);
                if (filesize($work.'/archive.zip') > config('backup.max_archive_bytes')) {
                    throw new RuntimeException('Backup size limit exceeded.');
                }
                $pending = $this->root().'/'.$id.'.hbk.partial';
                $this->cipher->encrypt($work.'/archive.zip', $pending);
                if (! rename($pending, $this->root().'/'.$id.'.hbk')) {
                    throw new RuntimeException('Cannot publish encrypted backup.');
                }
                $record['sha256'] = hash_file('sha256', $this->root().'/'.$id.'.hbk');
                $record['bytes'] = filesize($this->root().'/'.$id.'.hbk');
                $record['coverage'] = $manifest['file_roots'];
                $record['consistency'] = $manifest['consistency'];
                $record['status'] = 'completed';
                $record['completed_at'] = now()->toIso8601String();
                $this->record($id, $record);
                $this->verifyUnlocked($id, $actor);
                $record = $this->get($id);
                if (config('backup.secondary_root')) {
                    try {
                        $this->secondaryCopy($id, $record['sha256']);
                        $record['secondary_status'] = 'encrypted copy hash verified';
                    } catch (Throwable) {
                        $record['secondary_status'] = 'failed';
                        $this->alert('Secondary backup copy failed.');
                    }
                    $this->record($id, $record);
                }
                $this->audit('backup_completed', $id, $actor);

                return $record;
            } catch (Throwable $e) {
                $record['status'] = 'failed';
                $record['failed_at'] = now()->toIso8601String();
                $record['error'] = 'Backup failed; review private configuration, storage and tool availability.';
                $this->record($id, $record);
                $this->audit('backup_failed', $id, $actor);
                $this->alert('A Harbor AI backup failed. Review Backup & Disaster Recovery.');
                throw new RuntimeException($record['error'], 0, $e);
            } finally {
                if (isset($snapshotLock) && is_resource($snapshotLock)) {
                    flock($snapshotLock, LOCK_UN);
                    fclose($snapshotLock);
                }
                if (isset($pending) && is_file($pending)) {
                    unlink($pending);
                }
                $this->cleanup($work);
            }
        });
    }

    private function addFile(ZipArchive $zip, string $path, string $entry, array &$manifest): void
    {
        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException('Backup file unavailable.');
        }
        $manifest['entries'][$entry] = ['sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)];
        if (! $zip->addFile($path, $entry)) {
            throw new RuntimeException('Cannot archive application file.');
        }
    }

    public function verify(string $id, ?int $actor = null): array
    {
        return $this->locked(fn () => $this->verifyUnlocked($id, $actor));
    }

    private function verifyUnlocked(string $id, ?int $actor): array
    {
        $record = $this->get($id);
        $work = $this->workspace();
        try {
            $this->unpack($id, $record, $work);
            $record['verification_status'] = 'passed';
            $record['verified_at'] = now()->toIso8601String();
            $this->record($id, $record);
            $this->audit('integrity_passed', $id, $actor);

            return $record;
        } catch (Throwable $e) {
            $record['verification_status'] = 'failed';
            $record['verification_failed_at'] = now()->toIso8601String();
            $this->record($id, $record);
            $this->audit('integrity_failed', $id, $actor);
            $this->alert('Backup integrity verification failed.');
            throw new RuntimeException('Backup integrity verification failed.', 0, $e);
        } finally {
            $this->cleanup($work);
        }
    }

    private function unpack(string $id, array $record, string $work): array
    {
        $archive = $this->root().'/'.$this->id($id).'.hbk';
        if (! empty($record['deleted_at']) || ! is_file($archive) || is_link($archive) || ! hash_equals($record['sha256'] ?? '', hash_file('sha256', $archive))) {
            throw new RuntimeException('Backup archive hash mismatch.');
        }
        $this->cipher->decrypt($archive, $work.'/archive.zip');
        $zip = new ZipArchive;
        if ($zip->open($work.'/archive.zip', ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Backup container invalid.');
        }
        try {
            $stat = $zip->statName('manifest.json');
            if (! $stat || $stat['size'] > 16 * 1024 * 1024) {
                throw new RuntimeException('Backup manifest invalid.');
            }
            $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['id'] ?? '') !== $id || ($manifest['format'] ?? null) !== 1 || count($manifest['entries'] ?? []) + 1 !== $zip->numFiles) {
                throw new RuntimeException('Backup manifest mismatch.');
            }
            $database = $manifest['database'] ?? [];
            $databaseEntry = match ($database['driver'] ?? null) {
                'mysql' => 'database.sql',
                'sqlite' => 'database.sqlite',
                default => null,
            };
            if (! $databaseEntry || ($database['entry'] ?? null) !== $databaseEntry
                || ! isset($manifest['entries'][$databaseEntry]) || ! is_array($database['tables'] ?? null)) {
                throw new RuntimeException('Backup database manifest is incomplete.');
            }
            $total = 0;
            foreach ($manifest['entries'] as $entry => $metadata) {
                if (! preg_match('~^(database\.(sql|sqlite)|files/[a-z0-9-]+/.+)$~D', $entry) || str_contains($entry, '..') || str_contains($entry, '\\') || str_contains($entry, ':')) {
                    throw new RuntimeException('Unsafe archive entry.');
                }
                if (! is_array($metadata) || ! is_int($metadata['bytes'] ?? null) || $metadata['bytes'] < 0
                    || ! is_string($metadata['sha256'] ?? null) || ! preg_match('/^[0-9a-f]{64}$/D', $metadata['sha256'])) {
                    throw new RuntimeException('Invalid archive entry metadata.');
                }
                $stat = $zip->statName($entry);
                $total += $metadata['bytes'];
                if (! $stat || $stat['size'] !== $metadata['bytes'] || $total > config('backup.max_archive_bytes')) {
                    throw new RuntimeException('Archive entry size mismatch.');
                }
                $destination = $work.'/restored/'.$entry;
                if (! is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0700, true);
                }
                $in = $zip->getStream($entry);
                $out = fopen($destination, 'xb');
                if (! $in || ! $out) {
                    throw new RuntimeException('Cannot extract private backup entry.');
                }
                chmod($destination, 0600);
                try {
                    stream_copy_to_stream($in, $out, $metadata['bytes'] + 1);
                } finally {
                    fclose($in);
                    fclose($out);
                }
                if (filesize($destination) !== $metadata['bytes'] || ! hash_equals($metadata['sha256'], hash_file('sha256', $destination))) {
                    throw new RuntimeException('Backup entry integrity mismatch.');
                }
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    public function drill(string $id, ?int $actor = null): array
    {
        return $this->locked(function () use ($id, $actor) {
            $record = $this->get($id);
            $work = $this->workspace();
            $start = microtime(true);
            try {
                $manifest = $this->unpack($id, $record, $work);
                $result = $this->database->restoreIsolated($work.'/restored', $manifest['database']);
                $record['drill'] = ['status' => 'database and file checks passed', 'at' => now()->toIso8601String(), 'seconds' => round(microtime(true) - $start, 3), 'recovery_point_age_hours' => now()->diffInHours(Carbon::parse($record['started_at']), true), 'files_verified' => count($manifest['entries']) - 1, 'database' => $result, 'upload_references' => $manifest['upload_references'] ?? ['status' => 'not recorded by this archive version'], 'source_lockfiles_match' => collect($manifest['source_hashes'] ?? [])->mapWithKeys(fn ($hash, $file) => [$file => in_array($file, ['composer.lock', 'package-lock.json'], true) && is_file(base_path($file)) && hash_equals($hash, hash_file('sha256', base_path($file)))])->all(), 'application_qa' => 'not performed'];
                $this->audit('isolated_drill_passed', $id, $actor);
            } catch (Throwable $e) {
                $record['drill'] = ['status' => 'failed', 'at' => now()->toIso8601String(), 'seconds' => round(microtime(true) - $start, 3)];
                $this->record($id, $record);
                $this->audit('isolated_drill_failed', $id, $actor);
                throw new RuntimeException('Isolated recovery drill failed. Confirm a separate empty database and private tooling configuration.', 0, $e);
            } finally {
                $this->cleanup($work);
            }
            $this->record($id, $record);

            return $record;
        });
    }

    /** Recover a downloaded encrypted artifact even if the original journal was lost. CLI only. */
    public function import(string $source): array
    {
        return $this->locked(function () use ($source) {
            if (! is_file($source) || is_link($source) || ! str_ends_with(strtolower($source), '.hbk') || filesize($source) > config('backup.max_archive_bytes') + 1048576) {
                throw new RuntimeException('A local encrypted Harbor backup file is required.');
            }
            $work = $this->workspace();
            try {
                $this->cipher->decrypt($source, $work.'/archive.zip');
                $zip = new ZipArchive;
                if ($zip->open($work.'/archive.zip', ZipArchive::CHECKCONS) !== true) {
                    throw new RuntimeException('Backup container invalid.');
                }
                try {
                    $stat = $zip->statName('manifest.json');
                    if (! $stat || $stat['size'] > 16 * 1024 * 1024) {
                        throw new RuntimeException('Backup manifest invalid.');
                    }
                    $manifest = json_decode($zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
                } finally {
                    $zip->close();
                }
                $id = $this->id($manifest['id'] ?? '');
                if (($manifest['format'] ?? null) !== 1 || empty($manifest['created_at'])) {
                    throw new RuntimeException('Backup manifest invalid.');
                }
                $started = Carbon::parse($manifest['created_at'])->toIso8601String();
                $target = $this->root().'/'.$id.'.hbk';
                if (file_exists($target) || file_exists($this->root().'/'.$id.'.json')) {
                    throw new RuntimeException('Backup identifier already exists; existing data will not be replaced.');
                }
                $in = fopen($source, 'rb');
                $out = fopen($target, 'xb');
                if (! $in || ! $out) {
                    throw new RuntimeException('Cannot import encrypted archive.');
                }
                chmod($target, 0600);
                try {
                    stream_copy_to_stream($in, $out);
                } finally {
                    fclose($in);
                    fclose($out);
                }
                $record = ['id' => $id, 'trigger' => 'encrypted-import', 'actor_id' => null, 'started_at' => $started, 'completed_at' => $started, 'imported_at' => now()->toIso8601String(), 'status' => 'completed', 'verification_status' => 'not run', 'sha256' => hash_file('sha256', $target), 'bytes' => filesize($target), 'secondary_status' => 'Not Configured'];
                $this->record($id, $record);
                $this->audit('encrypted_archive_imported', $id, null);

                return $this->verifyUnlocked($id, null);
            } finally {
                $this->cleanup($work);
            }
        });
    }

    public function download(string $id, ?int $actor): string
    {
        return $this->locked(function () use ($id, $actor) {
            $record = $this->get($id);
            $archive = $this->root().'/'.$this->id($id).'.hbk';
            if (! empty($record['deleted_at']) || ! is_file($archive) || is_link($archive) || ! hash_equals($record['sha256'] ?? '', hash_file('sha256', $archive))) {
                throw new RuntimeException('Encrypted archive unavailable or invalid.');
            }
            // A private hard link pins the inode against concurrent retention until response completion.
            $pinned = $this->root().'/download-'.Str::uuid().'.hbk';
            if (! link($archive, $pinned)) {
                throw new RuntimeException('Cannot prepare private download.');
            }
            $this->audit('encrypted_download', $id, $actor);

            return $pinned;
        });
    }

    public function retain(): int
    {
        return $this->locked(function () {
            $rows = $this->history();
            $protected = null;
            foreach ($rows as $candidate) {
                if (($candidate['verification_status'] ?? '') !== 'passed' || ! empty($candidate['deleted_at'])) {
                    continue;
                }
                try {
                    $this->verifyUnlocked($candidate['id'], null);
                    $protected = $candidate;
                    break;
                } catch (RuntimeException) {
                    continue;
                }
            }
            if (! $protected) {
                return 0;
            } // Retain all points when no authentic recovery point can be protected.
            $deleted = 0;
            foreach ($rows as $row) {
                if (! empty($row['deleted_at']) || $row['status'] !== 'completed' || $row['id'] === ($protected['id'] ?? null) || Carbon::parse($row['started_at'])->greaterThan(now()->subDays(max(1, (int) config('backup.retention_days'))))) {
                    continue;
                }
                $file = $this->root().'/'.$this->id($row['id']).'.hbk';
                if (! is_file($file) || is_link($file)) {
                    continue;
                }
                if (! unlink($file)) {
                    throw new RuntimeException('Backup retention deletion failed.');
                }
                $row['deleted_at'] = now()->toIso8601String();
                $this->record($row['id'], $row);
                $this->audit('retention_deleted', $row['id'], null);
                $deleted++;
            }

            return $deleted;
        });
    }

    public function heartbeat(): void
    {
        file_put_contents($this->root().'/scheduler-heartbeat', now()->toIso8601String(), LOCK_EX);
        if (! config('backup.enabled')) {
            return;
        }
        $health = $this->health();
        if (! $health['rpo_met']) {
            $this->alert('No integrity-verified recovery point exists within the proposed 24-hour window.');
        }
        if ($health['free_bytes'] < config('backup.min_free_bytes')) {
            $this->alert('Backup storage is below the configured free-space threshold.');
        }
    }

    private function artifactPresent(array $row): bool
    {
        $path = $this->root().'/'.$this->id($row['id']).'.hbk';

        return is_file($path) && ! is_link($path) && filesize($path) === ($row['bytes'] ?? -1);
    }

    private function secondaryCopy(string $id, string $hash): void
    {
        $root = rtrim((string) config('backup.secondary_root'), '/\\');
        $real = realpath($root);
        $normal = static fn ($p) => strtolower(str_replace('\\', '/', $p));
        if (! $real || is_link($root) || ! is_writable($real) || str_starts_with($normal($real), $normal(base_path()).'/')) {
            throw new RuntimeException('Secondary storage must be an existing private location outside the application.');
        }
        $target = $real.'/'.$this->id($id).'.hbk';
        if (file_exists($target)) {
            throw new RuntimeException('Secondary backup already exists.');
        }
        $in = fopen($this->root().'/'.$id.'.hbk', 'rb');
        $out = fopen($target, 'xb');
        if (! $in || ! $out) {
            throw new RuntimeException('Secondary storage unavailable.');
        }
        chmod($target, 0600);
        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }
        if (! hash_equals($hash, hash_file('sha256', $target))) {
            throw new RuntimeException('Secondary copy hash mismatch.');
        }
    }

    private function workspace(): string
    {
        $work = $this->root().'/work-'.Str::uuid();
        if (! mkdir($work, 0700)) {
            throw new RuntimeException('Private temporary storage unavailable.');
        }

        return $work;
    }

    private function cleanup(string $work): void
    {
        if (dirname($work) !== $this->root() || ! preg_match('/^work-[0-9a-f-]{36}$/', basename($work)) || is_link($work)) {
            throw new RuntimeException('Refusing unsafe temporary cleanup.');
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($work, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            if ($file->isLink() || $file->isFile()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }
        rmdir($work);
    }

    public function audits(): array
    {
        $path = $this->root().'/audit.jsonl';
        if (! is_file($path) || is_link($path)) {
            return [];
        }
        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new RuntimeException('Backup audit history unavailable.');
        }
        try {
            $size = filesize($path);
            $offset = max(0, $size - 131072);
            fseek($stream, $offset);
            if ($offset > 0) {
                fgets($stream);
            }
            $lines = explode("\n", stream_get_contents($stream));
            $events = [];
            foreach (array_slice(array_filter($lines), -100) as $line) {
                $event = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
                $events[] = array_intersect_key($event, array_flip(['at', 'action', 'backup_id', 'actor_id']));
            }

            return array_reverse($events);
        } finally {
            fclose($stream);
        }
    }

    private function audit(string $action, string $id, ?int $actor): void
    {
        $event = json_encode(['at' => now()->toIso8601String(), 'action' => $action, 'backup_id' => $id, 'actor_id' => $actor], JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($this->root().'/audit.jsonl', $event, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Backup audit persistence failed.');
        }
        chmod($this->root().'/audit.jsonl', 0600);
    }

    private function alert(string $message): void
    {
        // One operational message of each kind per day. No archive contents or paths.
        $marker = $this->root().'/notice-'.hash('sha256', $message).'-'.now('Asia/Manila')->format('Y-m-d');
        if (is_file($marker)) {
            return;
        }
        try {
            foreach (User::where('user_type', 'Super Admin')->where('is_active', true)->pluck('user_id') as $id) {
                TransactionNotification::create(['recipient_user_id' => $id, 'title' => 'Backup & Disaster Recovery', 'body' => $message]);
            }
            file_put_contents($marker, 'sent', LOCK_EX);
        } catch (Throwable) {
            Log::warning('Backup operational notification could not be delivered.');
        }
    }
}
