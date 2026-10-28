<?php

namespace App\Http\Controllers;

use App\Services\Backup\BackupManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class BackupController extends Controller
{
    public function __construct(private BackupManager $backups) {}

    public function index()
    {
        return $this->safe(fn () => response()->json($this->backups->health())->header('Cache-Control', 'no-store, private'));
    }

    public function store(Request $request)
    {
        return $this->safe(fn () => response()->json($this->backups->create($request->user()->user_id), 201));
    }

    public function verify(Request $request, string $id)
    {
        return $this->safe(fn () => response()->json($this->backups->verify($id, $request->user()->user_id)));
    }

    public function drill(Request $request, string $id)
    {
        $this->reauthenticate($request);

        return $this->safe(fn () => response()->json($this->backups->drill($id, $request->user()->user_id)));
    }

    public function download(Request $request, string $id)
    {
        $this->reauthenticate($request);

        return $this->safe(fn () => response()->download($this->backups->download($id, $request->user()->user_id), 'harborai-'.$id.'.hbk', ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true));
    }

    public function instructions()
    {
        return response()->json(['rpo_hours' => 24, 'rto_hours' => 8, 'targets_proven' => false,
            'steps' => ['Verify archive integrity before recovery.', 'Use a separate empty recovery database on a separate server/port with a dedicated restricted user.', 'Run an isolated drill; database and file checks do not replace application and role-permission QA.', 'Preserve encryption keys separately from archives. Losing a key makes its archives unrecoverable.', 'Production recovery requires authorization, a maintenance window, a safety backup, a rollback plan and manual operator execution.'],
            'runbook' => 'docs/operations/BACKUP_RECOVERY.md'])->header('Cache-Control', 'no-store, private');
    }

    private function reauthenticate(Request $request): void
    {
        $data = $request->validate(['password' => 'required|string|max:1024']);
        abort_unless(Hash::check($data['password'], $request->user()->getAuthPassword()), 422, 'Reauthentication failed.');
    }

    private function safe(callable $action)
    {
        try {
            return $action();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503)->header('Cache-Control', 'no-store, private');
        }
    }
}
