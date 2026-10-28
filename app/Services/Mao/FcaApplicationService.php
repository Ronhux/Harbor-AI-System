<?php

namespace App\Services\Mao;

use App\Models\FcaEvidence;
use App\Models\FcaRegistry;
use App\Models\FcaRepresentative;
use App\Models\FcaReviewEvent;
use App\Models\Producer;
use App\Models\User;
use App\Policies\FcaWorkflowPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class FcaApplicationService
{
    public function __construct(
        private FcaWorkflowPolicy $policy,
        private WorkflowDraftPreparation $drafts,
        private FcaEvidenceStorage $files,
        private FcaEvidenceScanner $scanner,
    ) {}

    public function ready(): void
    {
        $this->policy->enabled();
        foreach (['fca_registry', 'fca_representatives', 'fca_evidence', 'fca_review_events'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'FCA dependencies are not available.');
        }
        abort_unless(Schema::hasColumns('fca_representatives', ['submitted_details', 'submission_key', 'row_version']), 503, 'FCA application schema is incomplete.');
    }

    public function applicant(User $actor): Producer
    {
        $this->ready();
        $producer = Producer::where('user_id', $actor->getKey())->first();
        abort_unless($producer && $this->policy->applicant($actor, $producer), 403);
        return $producer;
    }

    /**
     * PUBLIC association lookup used by the registration page.
     *
     * Returns the minimum a prospective applicant needs to identify an association: its
     * identifier, official name and address. Nothing else is exposed.
     *
     * Deliberately excluded:
     *   president_name                a listed name is not a verified identity, and repeating
     *                                it to anonymous visitors would republish unverified
     *                                personal data
     *   contact_number                private contact information (also model-hidden)
     *   directory_verification_status  administrative review state
     *   procurement_eligibility_status administrative decision
     *   source_record_key, registry_source  internal provenance keys
     *   claims, evidence, tokens      never read at all
     *
     * The full administrative view stays behind authentication in searchAssociations().
     *
     * @return array<int, array<string, mixed>>
     */
    public function publicAssociationSearch(?string $term = null, int $limit = 15): array
    {
        $query = FcaRegistry::query()
            ->select(['id', 'official_name', 'address'])
            ->orderBy('official_name');

        $term = is_string($term) ? trim($term) : '';
        if ($term !== '') {
            // Escape LIKE wildcards so a literal % or _ cannot widen the match.
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('official_name', 'like', $like)
                    ->orWhere('address', 'like', $like);
            });
        }

        return $query->limit(max(1, min(25, $limit)))->get()->map(fn (FcaRegistry $fca) => [
            'id' => (int) $fca->getKey(),
            'official_name' => $fca->official_name,
            'address' => $fca->address,
        ])->all();
    }

    /**
     * Searchable association lookup for the registration autocomplete and the registry page.
     *
     * This is deliberately NOT the same as directory(). directory() returns only associations
     * whose directory_verification_status is already "verified", which is the eligibility path
     * for an actual submission. Every imported source record is "pending" because nothing has
     * been independently confirmed, so a search that hid them would leave an applicant with an
     * empty list and no explanation.
     *
     * Each row therefore carries its real status so the interface can label it. A listed
     * president is a name in a directory, not a verified identity, and no such claim is made.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchAssociations(User $actor, ?string $term = null, int $limit = 25): array
    {
        // Administrative view: it exposes the listed president and each real verification
        // status, so it is restricted to an assigned reviewer. Without this check any signed-in
        // producer could read it, because a valid token is not the same as FCA authority.
        abort_unless($this->policy->registryViewer($actor), 403, 'Administrator access is required to view the FCA registry.');

        $query = FcaRegistry::query()
            ->select(['id', 'source_record_key', 'official_name', 'address', 'president_name',
                'directory_verification_status', 'procurement_eligibility_status', 'registry_source'])
            ->orderBy('official_name');

        $term = is_string($term) ? trim($term) : '';
        if ($term !== '') {
            // Partial match on either the association name or its address.
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('official_name', 'like', $like)
                   ->orWhere('address', 'like', $like);
            });
        }

        return $query->limit(max(1, min(50, $limit)))->get()->map(fn (FcaRegistry $fca) => [
            'id' => (int) $fca->getKey(),
            'source_record_key' => $fca->source_record_key,
            'official_name' => $fca->official_name,
            'address' => $fca->address,
            'president_name' => $fca->president_name,
            'directory_verification_status' => $fca->directory_verification_status,
            'procurement_eligibility_status' => $fca->procurement_eligibility_status,
            'registry_source' => $fca->registry_source,
            // Explicit so a UI cannot present a listed president as a verified identity.
            'president_identity_verified' => false,
            'is_verified_directory_entry' => $fca->directory_verification_status === 'verified',
        ])->all();
    }

    public function directory(User $actor)
    {
        $this->applicant($actor);
        return FcaRegistry::where('directory_verification_status', 'verified')
            ->select(['id', 'official_name', 'address'])->orderBy('official_name')->paginate(20);
    }

    public function upload(User $actor, UploadedFile $file): FcaEvidence
    {
        $producer = $this->applicant($actor);
        $ttl = config('fca_evidence.receipt_ttl_hours');
        abort_unless(is_int($ttl) && $ttl > 0, 503, 'An approved evidence receipt policy is required.');
        Validator::make(['evidence' => $file], ['evidence' => 'required|file|max:5120|mimetypes:application/pdf,image/png,image/jpeg'])->validate();
        try {
            $this->files->assertUploadTemporary($file);
        } catch (Throwable) {
            abort(503, 'Private upload staging is not configured.');
        }
        $bytes = file_get_contents($file->getRealPath(), false, null, 0, 5 * 1024 * 1024 + 1);
        // Inspect the same bounded byte buffer that is scanned, hashed and stored.
        $mime = is_string($bytes) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : null;
        $this->validateContent($bytes, $mime);
        try {
            $scan = $this->scanner->inspect($bytes, $mime);
        } catch (Throwable) {
            $scan = 'quarantined';
        }
        if (!in_array($scan, ['clean', 'quarantined'], true)) {
            throw ValidationException::withMessages(['evidence' => 'The document was rejected by content inspection.']);
        }
        $id = (string) Str::uuid();
        $key = 'evidence/' . $id . '.bin';
        $created = false;
        try {
            return PreparedWorkflowTransaction::run(function () use ($actor, $producer, $bytes, $mime, $scan, $id, $key, $ttl, &$created) {
                MaoLockPlan::acquire(['actor'=>$actor->getKey(),'producer'=>$producer->getKey()]);
                $currentProducer = Producer::whereKey($producer->getKey())->lockForUpdate()->firstOrFail();
                $currentActor = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($this->policy->applicant($currentActor, $currentProducer), 403);
                // Expired/quarantined uploads still consume this quota until reviewed retention cleanup exists.
                abort_if(FcaEvidence::where('uploaded_by', $actor->getKey())->whereNull('application_id')->count() >= 5,
                    429, 'Unattached document limit reached. Contact an authorized reviewer.');
                $evidence = new FcaEvidence();
                $this->persist($evidence, [
                    'id' => $id, 'uploaded_by' => $actor->getKey(), 'producer_id' => $producer->getKey(),
                    'storage_key' => $key, 'mime' => $mime, 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
                    // Preliminary scanning never substitutes for durable worker history.
                    'status' => 'quarantined', 'expires_at' => now()->addHours($ttl),
                ]);
                $this->files->write($key, $bytes, $created);
                $this->audit($actor, null, 'evidence_uploaded', null, 'quarantined');
                return $evidence;
            });
        } catch (Throwable $failure) {
            // Commit ambiguity: preserve a file unless absence of its row is proven.
            if ($created) {
                try {
                    if (!FcaEvidence::whereKey($id)->exists()) $this->files->removeUncommitted($key);
                } catch (Throwable) {
                    Log::warning('FCA uncommitted evidence retained for restricted reconciliation.');
                }
            }
            if ($failure instanceof HttpExceptionInterface) throw $failure;
            Log::error('FCA upload failed', ['exception' => get_class($failure)]);
            abort(503, 'Evidence upload did not complete. No application was submitted.');
        }
    }

    public function submit(User $actor, int $fcaId, array $input, ?\App\Models\FcaPresidentClaim $phoneClaim = null): FcaRepresentative
    {
        $producer = $this->applicant($actor);
        $kind = null;
        if (config('fca_president.workflow_enabled') === true) {
            $kind = $input['representative_kind'] ?? null;
            abort_unless(in_array($kind, ['president', 'delegated'], true), 422);
            if ($kind === 'president') abort_unless($phoneClaim && (int)$phoneClaim->applicant_user_id === (int)$actor->getKey()
                && (int)$phoneClaim->fca_id === $fcaId && app(\App\Services\Mao\President\PresidentVerification::class)->usable($phoneClaim), 422);
            unset($input['representative_kind']);
        }
        Validator::make($input, ['submission_key' => 'required|uuid'])->validate();
        $evidenceReceipt = $input['evidence_receipt'] ?? null;
        return PreparedWorkflowTransaction::run(function () use ($actor, $producer, $fcaId, $input, $kind, $phoneClaim, $evidenceReceipt) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'producer'=>$producer->getKey(),'fca'=>$fcaId,'fca_pair'=>true,'evidence'=>$evidenceReceipt,'evidence_optional'=>true]);
            $lockedProducer = Producer::whereKey($producer->getKey())->lockForUpdate()->firstOrFail();
            $currentActor = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->applicant($currentActor, $lockedProducer), 403);
            $fca = FcaRegistry::whereKey($fcaId)->lockForUpdate()->first();
            abort_unless($fca && $fca->directory_verification_status === 'verified', 422, 'Select a verified directory entry.');
            $draft = $this->drafts->fcaApplication($currentActor, $lockedProducer, $fca, $input);
            $existing = FcaRepresentative::where('producer_id', $producer->getKey())->where('fca_id', $fcaId)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->submission_key === $input['submission_key']
                    && $existing->submitted_details === $draft['submitted_details']
                    && ($kind === null || ($existing->verification_kind === $kind
                        && $existing->president_claim_id === $phoneClaim?->id)), 409, 'An application already exists.');
                return $existing;
            }
            abort_if(FcaRepresentative::where('submission_key', $input['submission_key'])->exists(), 409, 'Submission key is already used.');
            // Evidence is optional for preliminary intake: no identity document is required
            // at registration time. A reviewer may later request evidence before authorizing.
            $evidence = null;
            if ($evidenceReceipt) {
                $evidence = FcaEvidence::whereKey($evidenceReceipt)->lockForUpdate()->first();
                abort_unless($evidence && (int) $evidence->uploaded_by === (int) $actor->getKey()
                    && (int) $evidence->producer_id === (int) $producer->getKey()
                    && $evidence->status === 'clean' && $evidence->application_id === null
                    && $evidence->expires_at->isFuture(), 422, 'A clean, unexpired document owned by this applicant is required.');
                $this->verifyFile($evidence);
                abort_unless($evidence->hasSuccessfulInspection(), 422, 'Current successful inspection history is required.');
            }
            $application = new FcaRepresentative();
            $this->persist($application, $draft['proposed_attributes'] + [
                'submitted_details' => $draft['submitted_details'], 'submission_key' => $input['submission_key'],
                'row_version' => 0, 'authorization_evidence_reference' => $evidence?->getKey(),
            ]);
            if ($kind !== null) $this->persist($application, ['verification_kind' => $kind, 'president_claim_id' => $phoneClaim?->id]);
            if ($evidence) {
                $this->persist($evidence, ['application_id' => $application->getKey(), 'status' => 'attached']);
            }
            $this->audit($actor, $application, 'application_submitted', null, 'pending');
            return $application;
        });
    }

    /**
     * PUBLIC preliminary FCA intake — no account, no identity document.
     *
     * Creates a pending FcaRepresentative without a linked producer or user. The
     * record carries only the applicant's claimed details and a reference to the
     * directory entry they selected. Status is always 'pending' — no verification,
     * no authorization, no procurement authority.
     */
    public function preliminarySubmit(array $input): FcaRepresentative
    {
        $this->ready();
        // Preliminary intake accepts any directory record; verification of the
        // association and the applicant's authority happens at review time.
        $fca = FcaRegistry::whereKey($input['fca_id'])->firstOrFail();
        $existing = FcaRepresentative::where('submission_key', $input['submission_key'])->first();
            if ($existing) return $this->preliminaryReplay($existing, $input);

        return PreparedWorkflowTransaction::run(function () use ($input, $fca) {
            MaoLockPlan::acquire(['actor'=>null,'producer'=>null,'fca'=>$fca->getKey(),'evidence'=>null,'evidence_optional'=>true]);
            $lockedFca = FcaRegistry::whereKey($fca->getKey())->lockForUpdate()->firstOrFail();
            $existing = FcaRepresentative::where('submission_key', $input['submission_key'])->first();
            if ($existing) return $this->preliminaryReplay($existing, $input);

            $submittedDetails = [
                'representative_position' => $input['representative_position'],
                'authorization_statement' => $input['authorization_statement'],
                'evidence_receipt' => null,
                'intake_type' => 'preliminary',
                'submitted_at' => now()->toISOString(),
            ];

            $application = new FcaRepresentative();
            $this->persist($application, [
                'fca_id' => (int) $lockedFca->getKey(),
                'producer_id' => null,
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_details' => $submittedDetails,
                'submission_key' => $input['submission_key'],
                'row_version' => 0,
                'authorization_evidence_reference' => null,
            ]);
            // Anonymous preliminary intake has no authenticated actor to attribute the
            // submission event to. The FcaRepresentative record itself records the
            // submission; review history (FcaReviewEvent) is created when a reviewer
            // authorizes, rejects or revokes.
            foreach (User::whereIn('user_id', config('mao_workflows.fca_reviewer_user_ids', []))
                ->whereIn('user_type', ['Admin', 'Super Admin'])->where('is_active', true)->pluck('user_id') as $reviewerId) {
                \App\Models\TransactionNotification::create(['recipient_user_id' => $reviewerId,
                    'title' => 'FCA preliminary application received',
                    'body' => 'Application #' . $application->getKey() . ' is pending separate identity and representative-authority review. Open the FCA review queue.']);
            }
            return $application;
        });
    }

    private function preliminaryReplay(FcaRepresentative $existing, array $input): FcaRepresentative
    {
        $details = $existing->submitted_details ?? [];
        abort_unless(($details['intake_type'] ?? '') === 'preliminary'
            && (int) $existing->fca_id === (int) $input['fca_id']
            && ($details['representative_position'] ?? '') === $input['representative_position']
            && ($details['authorization_statement'] ?? '') === $input['authorization_statement'],
            409, 'Submission key is already used.');
        return $existing;
    }

    public function show(User $actor, int $id): FcaRepresentative
    {
        $this->ready();
        $application = FcaRepresentative::with('producer')->findOrFail($id);
        abort_unless($this->policy->view($actor, $application), 403);
        return $application;
    }

    public function pending(User $actor)
    {
        $this->ready();
        abort_unless($this->policy->reviewer($actor), 403);
        return FcaRepresentative::where('status', 'pending')->orderBy('id')->paginate(20);
    }

    public function review(User $actor, int $id, array $input): FcaRepresentative
    {
        $this->ready();
        abort_unless($this->policy->reviewer($actor), 403);
        Validator::make($input, [
            'decision_key' => 'required|uuid', 'expected_version' => 'required|integer|min:0',
            'decision' => 'required|in:authorize,reject,revoke', 'review_reason' => 'required|string|max:2000',
            'status' => 'prohibited', 'reviewed_by' => 'prohibited', 'reviewed_at' => 'prohibited',
            'evidence_receipt' => 'prohibited',
        ])->validate();
        return PreparedWorkflowTransaction::run(function () use ($actor, $id, $input) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'representative'=>$id]);
            $subject = FcaRepresentative::findOrFail($id);
            if ($subject->producer_id === null) return $this->reviewPreliminary($actor, $subject, $input);
            // Consistent order with submissions: producer, users, directory, application, evidence.
            $producer = Producer::whereKey($subject->producer_id)->lockForUpdate()->firstOrFail();
            $reviewer = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->policy->reviewer($reviewer), 403);
            $applicant = User::whereKey($producer->user_id)->lockForUpdate()->firstOrFail();
            $fca = FcaRegistry::whereKey($subject->fca_id)->lockForUpdate()->firstOrFail();
            $application = FcaRepresentative::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $application->producer_id === (int) $producer->getKey()
                && (int) $application->fca_id === (int) $fca->getKey(), 409, 'Application identity changed.');
            abort_if((int) $reviewer->getKey() === (int) $applicant->getKey(), 403, 'Self-review is not allowed.');
            $hash = hash('sha256', json_encode([
                'decision' => $input['decision'] ?? null, 'review_reason' => $input['review_reason'] ?? null,
                'expires_at' => $input['expires_at'] ?? null,
            ], JSON_THROW_ON_ERROR));
            $previousDecision = FcaReviewEvent::where('decision_key', $input['decision_key'])->first();
            if ($previousDecision) {
                abort_unless((int) $previousDecision->application_id === $id
                    && (int) $previousDecision->actor_user_id === (int) $actor->getKey()
                    && hash_equals($previousDecision->decision_hash, $hash), 409, 'Decision key conflict.');
                return $application;
            }
            abort_unless($application->row_version === (int) $input['expected_version'], 409, 'Application changed; review its current state.');
            // Never accept a reviewer-supplied evidence receipt in place of the submitted file.
            Validator::make($input, ['evidence_receipt' => 'prohibited'])->validate();
            $evidence = FcaEvidence::whereKey($application->authorization_evidence_reference)
                ->where('application_id', $id)->lockForUpdate()->first();
            if (($input['decision'] ?? null) === 'authorize') {
                if ($application->president_claim_id) {
                    abort_unless(config('fca_president.workflow_enabled') === true, 503);
                    $claim = \App\Models\FcaPresidentClaim::whereKey($application->president_claim_id)->lockForUpdate()->first();
                    abort_unless($claim && (int)$claim->applicant_user_id === (int)$applicant->getKey()
                        && (int)$claim->fca_id === (int)$fca->getKey()
                        && app(\App\Services\Mao\President\PresidentVerification::class)->usable($claim), 422, 'Current president verification requires review.');
                }
                abort_unless($this->policy->applicant($applicant, $producer)
                    && $fca->directory_verification_status === 'verified' && $evidence
                    && $evidence->status === 'attached'
                    && (int) $evidence->uploaded_by === (int) $applicant->getKey()
                    && (int) $evidence->producer_id === (int) $producer->getKey()
                    && $application->authorization_evidence_reference === $evidence->getKey(), 422, 'Authorization evidence or applicant eligibility is unavailable.');
                $this->verifyFile($evidence);
                abort_unless($evidence->hasSuccessfulInspection(), 422, 'Current successful inspection history is required.');
            }
            $draftInput = $input;
            if (($input['decision'] ?? null) === 'authorize') $draftInput['evidence_receipt'] = $evidence->getKey();
            $draft = $this->drafts->fcaReview($reviewer, $applicant, $producer, $fca, $application->getAttributes(), $draftInput);
            $old = $application->status;
            $changes = [
                'status' => $draft['proposed_attributes']['status'], 'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(), 'row_version' => $application->row_version + 1,
            ];
            if ($changes['status'] === 'authorized') $changes['expires_at'] = $input['expires_at'] ?? null;
            if ($changes['status'] === 'revoked') $changes['revoked_at'] = now();
            $this->persist($application, $changes);
            $this->audit($reviewer, $application, 'application_' . $application->status, $old, $application->status, [
                'review_reason' => $draft['submitted_details']['review_reason'],
                'decision_key' => $input['decision_key'], 'decision_hash' => $hash,
            ]);
            // No producer, user, registry, RSBSA or procurement field is updated.
            return $application;
        });
    }

    /** Preliminary intake can be rejected, but cannot grant representation without identity linkage. */
    private function reviewPreliminary(User $actor, FcaRepresentative $subject, array $input): FcaRepresentative
    {
        $reviewer = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
        abort_unless($this->policy->reviewer($reviewer), 403);
        $application = FcaRepresentative::whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
        abort_unless($application->producer_id === null && ($application->submitted_details['intake_type'] ?? '') === 'preliminary', 409, 'Application identity changed.');
        abort_unless($input['decision'] === 'reject', 422, 'Preliminary intake requires separate verified identity linkage and authority review before authorization.');
        $hash = hash('sha256', json_encode(['decision' => $input['decision'], 'review_reason' => $input['review_reason'], 'expires_at' => $input['expires_at'] ?? null], JSON_THROW_ON_ERROR));
        $previous = FcaReviewEvent::where('decision_key', $input['decision_key'])->first();
        if ($previous) {
            abort_unless((int) $previous->application_id === (int) $application->getKey() && (int) $previous->actor_user_id === (int) $reviewer->getKey() && hash_equals($previous->decision_hash, $hash), 409, 'Decision key conflict.');
            return $application;
        }
        abort_unless($application->status === 'pending' && $application->row_version === (int) $input['expected_version'], 409, 'Application changed; review its current state.');
        $this->persist($application, ['status' => 'rejected', 'reviewed_by' => $reviewer->getKey(), 'reviewed_at' => now(), 'row_version' => $application->row_version + 1]);
        $this->audit($reviewer, $application, 'application_rejected', 'pending', 'rejected', ['review_reason' => $input['review_reason'], 'decision_key' => $input['decision_key'], 'decision_hash' => $hash]);
        return $application;
    }

    public function retrieve(User $actor, string $receipt): array
    {
        $this->ready();
        abort_unless(config('mao_workflows.fca_evidence_access_enabled') === true, 404);
        $evidence = FcaEvidence::findOrFail($receipt);
        $ledger = app(FcaInspectionLedger::class);
        $correlation = (string) Str::uuid();
        abort_unless($ledger->access($actor, $receipt, $correlation), 403);
        try {
            $bytes = PreparedWorkflowTransaction::run(function() use($actor,$evidence) {
                MaoLockPlan::acquire(['actor'=>$actor->getKey(),'evidence'=>$evidence->getKey()]);
                $currentFile = $evidence->fresh();
                $currentActor = $actor->fresh();
                abort_unless($currentActor && $this->policy->evidence($currentActor, $currentFile)
                    && $currentFile->evidence_version === $evidence->evidence_version
                    && hash_equals($currentFile->sha256, $evidence->sha256), 403, 'Evidence access state changed.');
                $bytes = $this->verifyFile($currentFile);
                $this->audit($currentActor, $currentFile->application, 'evidence_downloaded', null, null);
                return $bytes;
            });
        } catch (Throwable) {
            $ledger->failedRelease($actor, $evidence, $correlation);
            abort(503, 'Evidence release failed.');
        }
        return ['bytes' => $bytes, 'mime' => $evidence->mime];
    }

    private function verifyFile(FcaEvidence $evidence): string
    {
        try {
            $bytes = $this->files->read($evidence->storage_key);
            abort_unless(strlen($bytes) === $evidence->bytes && hash_equals($evidence->sha256, hash('sha256', $bytes)), 503, 'Evidence integrity check failed.');
            return $bytes;
        } catch (Throwable) {
            abort(503, 'Evidence is unavailable or failed integrity verification.');
        }
    }

    private function validateContent(string|false $bytes, ?string $mime): void
    {
        $valid = is_string($bytes) && strlen($bytes) > 0 && strlen($bytes) <= 5 * 1024 * 1024;
        if ($valid && $mime === 'application/pdf') {
            $valid = preg_match('/\A%PDF-1\.[0-7]/', $bytes) && str_ends_with(rtrim($bytes), '%%EOF')
                && !preg_match('/\/(?:JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|RichMedia|XFA|Encrypt)\b/i', $bytes);
        } elseif ($valid && in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $dimensions = @getimagesizefromstring($bytes);
            $valid = $dimensions && ($dimensions['mime'] ?? null) === $mime
                && $dimensions[0] <= 5000 && $dimensions[1] <= 5000 && $dimensions[0] * $dimensions[1] <= 8000000;
            if ($valid && function_exists('imagecreatefromstring')) {
                $image = @imagecreatefromstring($bytes);
                $valid = $image !== false;
                if ($image) imagedestroy($image);
            } else {
                $valid = false; // No approved decoder: reject rather than trust an image header.
            }
        } else {
            $valid = false;
        }
        if (!$valid) throw ValidationException::withMessages(['evidence' => 'Upload a valid PDF, PNG or JPEG up to 5 MB.']);
        // PDF structure checks are conservative, not malware certification: scanner quarantine is mandatory by default.
    }

    private function audit(?User $actor, ?FcaRepresentative $application, string $event, ?string $from, ?string $to, array $extra = []): void
    {
        $this->persist(new FcaReviewEvent(), [
            'actor_user_id' => $actor?->getKey() ?? 0, 'application_id' => $application?->getKey(),
            'event_type' => $event, 'previous_status' => $from, 'new_status' => $to,
        ] + $extra);
    }

    private function persist(Model $record, array $attributes): void
    {
        abort_unless($record->forceFill($attributes)->save(), 503, 'FCA record or audit could not be persisted.');
    }
}
