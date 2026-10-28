<?php

namespace App\Services\Mao;

use App\Models\FcaEvidence;
use App\Models\FcaInspectionAttempt;
use App\Models\FcaReviewEvent;
use App\Models\Producer;
use App\Models\User;
use App\Policies\FcaWorkflowPolicy;
use App\Services\Mao\Inspection\DocumentInspector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/** Trusted worker foundations only. No routes, scanner connection or document bytes in audit output. */
final class FcaInspectionLedger
{
    public function __construct(private FcaWorkflowPolicy $policy, private FcaEvidenceStorage $files, private DocumentInspector $inspector) {}

    private function ready(): void
    {
        $this->policy->enabled();
        abort_unless(config('mao_workflows.fca_inspection_persistence_enabled') === true, 404);
        abort_unless(Schema::hasTable('fca_inspection_attempts') && Schema::hasColumn('fca_review_events', 'correlation_id'), 503, 'Inspection ledger dependencies unavailable.');
    }

    public function begin(User $actor, string $evidenceId, int $version, string $hash, string $key, ?string $retryOf = null): FcaInspectionAttempt
    {
        $this->ready();
        abort_unless($this->policy->reviewer($actor), 403);
        abort_unless(Str::isUuid($key), 422);
        $adapter = config('fca_evidence.inspection_adapter_id');
        $max = config('fca_evidence.max_inspection_attempts');
        abort_unless(is_string($adapter) && preg_match('/\A[a-zA-Z0-9_.-]{1,64}\z/', $adapter)
            && is_int($max) && $max > 0, 503, 'Approved adapter/attempt policy required.');
        return PreparedWorkflowTransaction::run(function () use ($actor, $evidenceId, $version, $hash, $key, $retryOf, $adapter, $max) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'evidence'=>$evidenceId]);
            [$current, $file] = $this->lockContext($actor, $evidenceId);
            abort_unless($this->policy->reviewer($current), 403);
            abort_unless($file->evidence_version === $version && hash_equals($file->sha256, $hash), 409, 'Stale evidence version/hash.');
            $same = FcaInspectionAttempt::where('operation_key', $key)->first();
            if ($same) {
                abort_unless($same->evidence_id === $evidenceId && $same->evidence_version === $version
                    && hash_equals($same->input_sha256, $hash) && (int) $same->initiated_by === (int) $current->getKey()
                    && $same->retry_of_id === $retryOf && $same->adapter_id === $adapter, 409, 'Inspection key conflict.');
                return $same;
            }
            $latest = $file->latest_attempt_id ? FcaInspectionAttempt::find($file->latest_attempt_id) : null;
            abort_if($latest && $latest->status === 'pending', 409, 'An inspection is already pending.');
            if ($latest) {
                abort_unless($retryOf === $latest->getKey() && in_array($latest->status, ['failed', 'unavailable'], true), 409, 'Explicit eligible retry required.');
            } else {
                abort_unless($retryOf === null, 409, 'No prior attempt for this evidence.');
            }
            abort_if(FcaInspectionAttempt::where('evidence_id', $evidenceId)->where('evidence_version', $version)->count() >= $max, 429);
            $attempt = new FcaInspectionAttempt();
            $this->save($attempt, ['id' => (string) Str::uuid(), 'evidence_id' => $evidenceId, 'evidence_version' => $version,
                'input_sha256' => $hash, 'operation_key' => $key, 'retry_of_id' => $retryOf,
                'initiated_by' => $current->getKey(), 'inspection_type' => 'document', 'adapter_id' => $adapter, 'status' => 'pending']);
            $this->save($file, ['latest_attempt_id' => $attempt->getKey(), 'inspection_state' => 'pending', 'status' => 'quarantined']);
            $this->audit($current, $file, 'inspection_started', 'denied', 'inspection_pending', $key);
            return $attempt;
        });
    }

    /** No client verdict/result argument. Inspector computes from the stored bytes. */
    public function complete(User $actor, string $attemptId): FcaInspectionAttempt
    {
        $this->ready();
        abort_unless($this->policy->reviewer($actor), 403);
        $snapshot = FcaInspectionAttempt::findOrFail($attemptId);
        $source = FcaEvidence::findOrFail($snapshot->evidence_id);
        if ($snapshot->status !== 'pending') return $this->completedReplay($actor, $source, $snapshot);
        // Expensive inspection runs OUTSIDE the transaction; validate again under locks.
        try {
            $bytes = $this->files->read($source->storage_key);
            $result = $this->inspector->inspect($bytes, $source->mime, $snapshot->input_sha256);
        } catch (Throwable) {
            $result = new \App\Services\Mao\Inspection\InspectionResult('error', ['storage' => 'error'], $snapshot->input_sha256);
        }
        return PreparedWorkflowTransaction::run(function () use ($actor, $snapshot, $result) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'attempt'=>$snapshot->getKey()]);
            [$current, $file] = $this->lockContext($actor, $snapshot->evidence_id);
            abort_unless($this->policy->reviewer($current), 403);
            $attempt = FcaInspectionAttempt::whereKey($snapshot->getKey())->lockForUpdate()->firstOrFail();
            $this->assertCurrent($file, $attempt);
            if ($attempt->status !== 'pending') return $attempt;
            $validFile = false;
            try {
                $nowBytes = $this->files->read($file->storage_key);
                $validFile = strlen($nowBytes) === $file->bytes && hash_equals($attempt->input_sha256, hash('sha256', $nowBytes))
                    && hash_equals($attempt->input_sha256, $result->sha256);
            } catch (Throwable) {}
            $state = $validFile ? $result->status : 'rejected';
            $usable = $validFile && $result->usable();
            $status = $usable ? 'successful' : match ($state) { 'rejected' => 'rejected', 'error' => 'failed', default => 'unavailable' };
            $checks = $validFile ? $result->checks : ['integrity' => 'rejected'];
            $this->save($attempt, ['status' => $status, 'check_results' => $checks,
                'error_category' => $usable ? null : ($validFile ? 'inspection_' . $state : 'integrity_failure'), 'completed_at' => now()]);
            $this->save($file, ['inspection_state' => $usable ? 'clean' : $state,
                'status' => $usable ? ($file->application_id ? 'attached' : 'clean') : 'quarantined']);
            $this->audit($current, $file, 'inspection_completed', $usable ? 'eligible' : 'denied', $status, $attempt->operation_key);
            return $attempt;
        });
    }

    public function interrupt(User $actor, string $attemptId): void
    {
        $this->ready();
        abort_unless($this->policy->reviewer($actor), 403);
        $snapshot = FcaInspectionAttempt::findOrFail($attemptId);
        PreparedWorkflowTransaction::run(function () use ($actor, $snapshot) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'attempt'=>$snapshot->getKey()]);
            [$current, $file] = $this->lockContext($actor, $snapshot->evidence_id);
            abort_unless($this->policy->reviewer($current), 403);
            $attempt = FcaInspectionAttempt::whereKey($snapshot->getKey())->lockForUpdate()->firstOrFail();
            $this->assertCurrent($file, $attempt);
            abort_unless($attempt->status === 'pending', 409);
            $this->save($attempt, ['status' => 'failed', 'error_category' => 'interrupted', 'completed_at' => now()]);
            $this->save($file, ['inspection_state' => 'error', 'status' => 'quarantined']);
            $this->audit($current, $file, 'inspection_interrupted', 'denied', 'interrupted', $attempt->operation_key);
        });
    }

    /** Auditable access gate, not a byte-release API; caller must still verify/read before responding. */
    public function access(User $actor, string $receipt, string $correlation): bool
    {
        $this->ready();
        abort_unless(config('mao_workflows.fca_evidence_access_enabled') === true, 404);
        abort_unless(Str::isUuid($correlation), 422);
        return PreparedWorkflowTransaction::run(function () use ($actor, $receipt, $correlation) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'evidence'=>$receipt,'evidence_optional'=>true]);
            $found = FcaEvidence::find($receipt);
            if (!$found) {
                $this->audit($actor, null, 'evidence_access_denied', 'denied', 'missing_evidence', $correlation);
                return false;
            }
            [$current, $file] = $this->lockContext($actor, $receipt);
            $allowed = $this->policy->evidence($current, $file);
            $reason = $allowed ? 'inspection_and_authorization_passed' : 'authorization_or_inspection_denied';
            if ($allowed) {
                try {
                    $bytes = $this->files->read($file->storage_key);
                    $allowed = strlen($bytes) === $file->bytes && hash_equals($file->sha256, hash('sha256', $bytes));
                    if (!$allowed) $reason = 'integrity_failure';
                } catch (Throwable) { $allowed = false; $reason = 'storage_unavailable'; }
            }
            $this->audit($current, $file, $allowed ? 'evidence_access_allowed' : 'evidence_access_denied', $allowed ? 'allowed' : 'denied', $reason, $correlation);
            return $allowed;
        });
    }

    public function failedRelease(User $actor, FcaEvidence $file, string $correlation): void
    {
        $this->ready();
        PreparedWorkflowTransaction::run(function() use($actor,$file,$correlation) {
            MaoLockPlan::acquire(['actor'=>$actor->getKey(),'evidence'=>$file->getKey()]);
            $this->audit($actor, $file, 'evidence_release_failed', 'denied', 'release_or_audit_failed', $correlation);
        });
    }

    private function lockContext(User $actor, string $id): array
    {
        $snapshot = FcaEvidence::findOrFail($id);
        Producer::whereKey($snapshot->producer_id)->lockForUpdate()->firstOrFail();
        $current = User::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
        if ($snapshot->application_id) \App\Models\FcaRepresentative::whereKey($snapshot->application_id)->lockForUpdate()->firstOrFail();
        $file = FcaEvidence::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($file->producer_id === $snapshot->producer_id && $file->application_id === $snapshot->application_id, 409);
        return [$current, $file];
    }

    private function assertCurrent(FcaEvidence $file, FcaInspectionAttempt $attempt): void
    {
        abort_unless($file->latest_attempt_id === $attempt->getKey() && $file->evidence_version === $attempt->evidence_version
            && hash_equals($file->sha256, $attempt->input_sha256)
            && $attempt->adapter_id === config('fca_evidence.inspection_adapter_id'), 409, 'Inspection result is stale.');
    }

    private function completedReplay(User $actor, FcaEvidence $file, FcaInspectionAttempt $attempt): FcaInspectionAttempt
    {
        $this->assertCurrent($file, $attempt);
        $current = $actor->fresh();
        abort_unless($current && $this->policy->reviewer($current), 403);
        return $attempt;
    }

    private function audit(User $actor, ?FcaEvidence $file, string $action, string $decision, string $reason, string $correlation): void
    {
        $this->save(new FcaReviewEvent(), ['actor_user_id' => $actor->getKey(), 'application_id' => $file?->application_id,
            'event_type' => $action, 'evidence_id' => $file?->getKey(), 'evidence_version' => $file?->evidence_version,
            'authorization_decision' => $decision, 'reason_category' => $reason,
            'inspection_state' => $file?->inspection_state, 'correlation_id' => $correlation]);
    }

    private function save(Model $record, array $fields): void
    {
        abort_unless($record->forceFill($fields)->save(), 503, 'Inspection or audit persistence failed.');
    }
}
