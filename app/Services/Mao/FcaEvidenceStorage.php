<?php

namespace App\Services\Mao;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

/** Local private storage only. Existing files are never replaced. */
class FcaEvidenceStorage
{
    public function assertUploadTemporary(\Illuminate\Http\UploadedFile $file): void
    {
        if ($file instanceof \Illuminate\Http\Testing\File && app()->environment('testing')
            && config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') return;
        $approved = config('fca_evidence.upload_tmp_dir');
        if (!is_string($approved)) throw new RuntimeException('Private upload temporary directory approval required.');
        $this->validateRoot($approved);
        $path = $file->getRealPath();
        if (!$path || is_link($path) || $this->normal(dirname($path)) !== $this->normal(realpath($approved) ?: '')) {
            throw new RuntimeException('Upload was not staged in the approved private temporary directory.');
        }
    }

    public function disk(): FilesystemAdapter
    {
        $config = config('filesystems.disks.fca_evidence', []);
        if (($config['driver'] ?? null) !== 'local' || ($config['serve'] ?? null) !== false
            || ($config['visibility'] ?? null) !== 'private' || !is_string($config['root'] ?? null)) {
            throw new RuntimeException('Private local disk configuration required.');
        }
        // Flysystem normally provisions roots on construction. Validate first so
        // a missing/public/aliased configured root is never provisioned here.
        $this->validateRoot(rtrim($config['root'], '/\\'));
        $disk = Storage::disk('fca_evidence');
        if (!$disk->getAdapter() instanceof LocalFilesystemAdapter) throw new RuntimeException('Private local storage required.');
        $root = rtrim($disk->path(''), '/\\');
        if ($this->normal($root) !== $this->normal($config['root'])) throw new RuntimeException('Private disk root mismatch.');
        $this->validateRoot($root);
        return $disk;
    }

    private function validateRoot(string $root): void
    {
        if (PHP_OS_FAMILY === 'Windows' ? !preg_match('/\A[A-Za-z]:[\\\\\/]/', $root) : !str_starts_with($root, '/')) {
            throw new RuntimeException('Absolute local evidence root required; UNC/device/stream paths are refused.');
        }
        $real = realpath($root);
        if (!$real || $this->normal($real) !== $this->normal($root)) throw new RuntimeException('Private root must exist without aliases.');
        // Synthetic Storage::fake files only; never a production escape hatch.
        $testRoot = storage_path('framework/testing/disks/fca_evidence');
        $synthetic = app()->environment('testing') && config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:'
            && $this->normal($real) === $this->normal($testRoot);
        if (!$synthetic) {
            foreach ([base_path(), public_path(), 'C:/xampp/htdocs', 'C:/xampp/mysql',
                'C:/MariaDB_Backups', 'C:/MariaDB_Diagnostics', '/var/lib/mysql'] as $forbidden) {
                $prefix = $this->normal($forbidden);
                if ($this->normal($real) === $prefix || str_starts_with($this->normal($real), $prefix . '/')) {
                    throw new RuntimeException('Evidence root must be outside web/project paths.');
                }
            }
        }
        for ($p = $root; $p && dirname($p) !== $p; $p = dirname($p)) {
            if (is_link($p)) throw new RuntimeException('Storage aliases are not allowed.');
        }
    }

    public function path(string $key): string
    {
        if (!preg_match('/\Aevidence\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.bin\z/', $key)) {
            throw new RuntimeException('Invalid generated storage key.');
        }
        $disk = $this->disk();
        $dir = $disk->path('evidence');
        if (file_exists($dir) && (is_link($dir) || !is_dir($dir) || $this->normal(realpath($dir) ?: '') !== $this->normal($dir))) {
            throw new RuntimeException('Unexpected evidence directory.');
        }
        $path = $disk->path($key);
        if (is_link($path)) throw new RuntimeException('Unexpected evidence link.');
        return $path;
    }

    public function write(string $key, string $bytes, bool &$created): void
    {
        $created = false;
        $path = $this->path($key);
        $disk = $this->disk();
        if (!is_dir(dirname($path)) && !$disk->makeDirectory('evidence')) throw new RuntimeException('Cannot create evidence directory.');
        $path = $this->path($key);
        $handle = @fopen($path, 'xb');
        if (!$handle) throw new RuntimeException('Exclusive evidence creation failed.');
        $created = true;
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if (!$written) throw new RuntimeException('Incomplete evidence write.');
                $offset += $written;
            }
            if (!fflush($handle) || !fsync($handle)) throw new RuntimeException('Evidence flush failed.');
        } finally {
            fclose($handle);
        }
        $disk->setVisibility($key, 'private');
        if ($this->read($key) !== $bytes) throw new RuntimeException('Evidence write verification failed.');
    }

    public function read(string $key): string
    {
        $path = $this->path($key);
        $size = @filesize($path);
        if ($size === false || $size <= 0 || $size > 5 * 1024 * 1024) throw new RuntimeException('Invalid evidence size.');
        $bytes = @file_get_contents($path, false, null, 0, 5 * 1024 * 1024 + 1);
        if ($bytes === false || strlen($bytes) !== $size) throw new RuntimeException('Evidence unavailable.');
        return $bytes;
    }

    public function removeUncommitted(string $key): void
    {
        $this->path($key);
        if (!$this->disk()->delete($key)) throw new RuntimeException('Uncommitted evidence cleanup failed.');
    }

    private function normal(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
