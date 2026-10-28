<?php

namespace App\Http\Middleware;

use App\Services\Backup\BackupManager;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/** Cooperating API writers cannot alter the database/uploads during snapshot staging. */
class BackupWriteBarrier
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('backup.enabled') || $request->isMethodSafe() || $request->is('api/superadmin/backups*')) {
            return $next($request);
        }
        try {
            $lock = app(BackupManager::class)->snapshotLock(false);
        } catch (RuntimeException) {
            return response()->json(['message' => 'A recovery snapshot is being staged. Please retry shortly.'], 503)->header('Retry-After', '10');
        }
        try {
            return $next($request);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
