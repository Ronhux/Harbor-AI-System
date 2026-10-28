<?php

namespace App\Http\Controllers;

use App\Models\FcaEvidence;
use App\Models\FcaRepresentative;
use App\Services\Mao\FcaApplicationService;
use App\Http\Responses\PreparedMaoResponse;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/** Prepared routes only. Explicit response fields prevent evidence/identity leakage. */
class FcaApplicationController extends Controller
{
    public function __construct(private FcaApplicationService $applications) {}

    /**
     * PUBLIC, read-only association search for the registration page.
     *
     * Reachable without an account, because a prospective applicant must be able to find
     * their association before registering. Returns only the identifier, official name and
     * address. It never exposes a president name, a verification status, a contact number,
     * evidence, claims or any other administrative data, and it performs no writes.
     *
     * Errors are returned as a short, human-readable sentence so the interface can show
     * something useful instead of a framework exception.
     */
    public function publicAssociations(Request $request)
    {
        try {
            $validated = $request->validate([
                'q' => ['nullable', 'string', 'max:120'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Enter a search term of up to 120 characters.',
                'data' => [],
            ], 422);
        }

        $rows = $this->applications->publicAssociationSearch(
            $validated['q'] ?? null,
            (int) ($validated['limit'] ?? 15)
        );

        return response()->json([
            'data' => $rows,
            'count' => count($rows),
        ]);
    }

    public function directory(Request $request)
    {
        return response()->json($this->applications->directory($request->user()));
    }


    /**
     * Search associations for the registration autocomplete and the registry page.
     *
     * Read-only, and distinct from directory(): directory() returns only entries whose
     * directory_verification_status is already "verified", which is the eligibility path for a
     * real submission. Every imported source record is "pending" because nothing has been
     * independently confirmed, so each row here reports its real status instead of hiding it.
     */
    public function associations(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $rows = $this->applications->searchAssociations(
            $request->user(),
            $validated['q'] ?? null,
            (int) ($validated['limit'] ?? 25)
        );

        return response()->json([
            'data' => $rows,
            'count' => count($rows),
            'total_in_directory' => \App\Models\FcaRegistry::count(),
            'verified_count' => \App\Models\FcaRegistry::where('directory_verification_status', 'verified')->count(),
            'notice' => 'Directory entries are source-provided records awaiting independent confirmation. '
                . 'A listed president is not a verified identity and no approval is implied.',
        ]);
    }

    public function upload(Request $request)
    {
        $this->applications->applicant($request->user());
        Validator::make($request->all(), ['evidence' => 'required|file'])->validate();
        $file = $this->applications->upload($request->user(), $request->file('evidence'));
        return response()->json(['receipt' => $file->getKey(), 'status' => $file->status,
            'mime' => $file->mime, 'bytes' => $file->bytes], 201);
    }

    public function store(Request $request, int $fca)
    {
        $application = $this->databaseAction(fn () => $this->applications->submit($request->user(), $fca, $request->all()));
        return response()->json(['data' => $this->present($application)], 201);
    }

    /**
     * PUBLIC preliminary FCA intake — no account, no identity document.
     *
     * Creates a pending FCA registration record without a linked producer or user.
     * The applicant is not verified, receives no procurement authority, and is not
     * marked as a verified FCA president or representative. An authorized reviewer
     * must later evaluate the request before any authority is granted.
     */
    public function preliminary(Request $request)
    {
        $validated = $request->validate([
            'fca_id' => ['required', 'integer', 'min:1', 'exists:fca_registry,id'],
            'representative_position' => ['required', 'string', 'max:120'],
            'authorization_statement' => ['required', 'string', 'max:2000'],
            'submission_key' => ['required', 'uuid'],
        ]);

        $application = $this->databaseAction(fn () => $this->applications->preliminarySubmit($validated));
        return response()->json(['data' => $this->present($application)], 201);
    }

    public function show(Request $request, int $application)
    {
        $record = $this->applications->show($request->user(), $application);
        $details = $record->submitted_details ?? [];
        unset($details['evidence_receipt']);
        return response()->json(PreparedMaoResponse::fcaDetail($record->toArray(), $details,
            $record->reviewEvents()->orderByDesc('id')->limit(100)->get(['event_type', 'previous_status', 'new_status', 'created_at'])->reverse()->values()
                ->map(fn ($event) => $event->only(['event_type', 'previous_status', 'new_status', 'created_at']))));
    }

    public function pending(Request $request)
    {
        $page = $this->applications->pending($request->user());
        $page->setCollection($page->getCollection()->map(fn ($record) => $this->present($record)));
        return response()->json($page);
    }

    public function review(Request $request, int $application)
    {
        $record = $this->databaseAction(fn () => $this->applications->review($request->user(), $application, $request->all()));
        return response()->json(['data' => $this->present($record)]);
    }

    public function evidence(Request $request, string $receipt)
    {
        $result = $this->databaseAction(fn () => $this->applications->retrieve($request->user(), $receipt));
        return $this->download($result);
    }

    public function evidenceStatus(Request $request, string $receipt)
    {
        $this->applications->ready();$row=FcaEvidence::findOrFail($receipt);
        $policy=app(\App\Policies\FcaWorkflowPolicy::class);$actor=$request->user();
        // Metadata access is separate from clean-document download authorization.
        $owned=(int)$row->uploaded_by===(int)$actor->getKey()&&$row->producer&&$policy->applicant($actor,$row->producer);
        abort_unless($owned||($row->application_id&&$policy->reviewer($actor)),403);
        return response()->json(['receipt'=>$row->getKey(),'status'=>$row->status,'inspection_ready'=>$row->hasSuccessfulInspection(),'expired'=>$row->expires_at?->isPast()??true])->header('Cache-Control','no-store, private');
    }

    public function applicationEvidence(Request $request, int $application)
    {
        $record = $this->applications->show($request->user(), $application);
        $evidence = FcaEvidence::whereKey($record->authorization_evidence_reference)
            ->where('application_id', $record->getKey())->firstOrFail();
        $result = $this->databaseAction(fn () => $this->applications->retrieve($request->user(), $evidence->getKey()));
        return $this->download($result);
    }

    private function download(array $result)
    {
        $extension = match ($result['mime']) { 'application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', default => 'bin' };
        return response($result['bytes'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="fca-evidence.' . $extension . '"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache',
        ]);
    }

    private function present(FcaRepresentative $record): array
    {
        return PreparedMaoResponse::fca($record->only(PreparedMaoResponse::FCA_FIELDS));
    }

    private function databaseAction(callable $action)
    {
        try {
            return $action();
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Application or decision already exists. No automatic retry.');
        } catch (QueryException $e) {
            Log::warning('FCA database operation failed.', ['exception' => get_class($e), 'code' => $e->getCode()]);
            abort(503, 'The FCA operation could not complete. No automatic retry.');
        }
    }
}
