<?php

namespace App\Services\Mao;

use App\Models\FcaEvidence;
use App\Models\User;
use App\Policies\FcaWorkflowPolicy;
use App\Services\Mao\Inspection\DocumentInspector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only planning. Never releases, deletes, repairs or updates evidence. */
final class FcaEvidenceGovernance
{
    public function __construct(private FcaWorkflowPolicy $policy, private FcaEvidenceStorage $files, private DocumentInspector $inspector) {}

    public function retentionPlan(string $category, ?Carbon $since): array
    {
        $days = config('fca_evidence.retention_days.' . $category);
        return ['action' => 'retain', 'policy_approved' => is_int($days) && $days > 0,
            'review_due' => $since !== null && is_int($days) && $days > 0 && $since->copy()->addDays($days)->isPast(),
            'deletion_authorized' => false];
    }

    public function dryRun(User $actor, int $limit = 100): array
    {
        $this->policy->enabled();
        abort_unless($this->policy->reviewer($actor), 403);
        abort_unless($limit >= 1 && $limit <= 100, 422);
        abort_unless(Schema::hasTable('fca_evidence') && Schema::hasTable('fca_representatives'), 503, 'Evidence dependencies are unavailable.');
        $rows = [];
        foreach (FcaEvidence::with(['application', 'uploader:user_id', 'producer:producer_id'])->orderBy('id')->limit($limit)->get() as $file) {
            $codes = [];
            if ($file->application_id && !$file->application) $codes[] = 'ORPHAN_APPLICATION_REFERENCE';
            if (!$file->uploader || !$file->producer) $codes[] = 'ORPHAN_OWNER_REFERENCE';
            if (!$file->application_id) $codes[] = 'UNATTACHED_OR_INTERRUPTED_UPLOAD';
            try {
                $bytes = $this->files->read($file->storage_key);
                if (strlen($bytes) !== $file->bytes || !hash_equals($file->sha256, hash('sha256', $bytes))) $codes[] = 'INTEGRITY_MISMATCH';
            } catch (Throwable) {
                $codes[] = 'FILE_MISSING_OR_UNSAFE';
            }
            if ($file->status === 'quarantined') $codes[] = 'INSPECTION_REQUIRED';
            $category = in_array($file->application?->status, ['rejected', 'revoked'], true)
                ? $file->application->status : ($file->status === 'quarantined' ? 'quarantined' : ($file->application_id ? 'active' : 'unattached'));
            $rows[] = ['evidence_record' => $file->getKey(), 'codes' => $codes,
                'retention' => $this->retentionPlan($category, $file->application?->reviewed_at ?? $file->created_at)];
        }
        return ['dry_run' => true, 'mutated' => false, 'limit' => $limit, 'rows' => $rows,
            'limitations' => ['Bounded database-led scan; untracked filesystem orphans require separately approved inventory.', 'No deletion or automatic inspection release.']];
    }

    public function reinspectionPlan(User $actor, FcaEvidence $file, int $priorAttempts): array
    {
        $this->policy->enabled();
        abort_unless($this->policy->reviewer($actor), 403);
        if (Schema::hasTable('fca_inspection_attempts') && $file->exists) {
            // Durable counts supersede a caller's planning hint once the ledger exists.
            $priorAttempts = \App\Models\FcaInspectionAttempt::where('evidence_id', $file->getKey())
                ->where('evidence_version', $file->evidence_version)->count();
        }
        $max = config('fca_evidence.max_inspection_attempts');
        abort_unless(is_int($max) && $max > 0 && $priorAttempts >= 0 && $priorAttempts < $max, 409, 'An approved inspection attempt policy is required.');
        try {
            $bytes = $this->files->read($file->storage_key);
            $result = $this->inspector->inspect($bytes, $file->mime, $file->sha256);
            $summary = $result->safeSummary();
            if (strlen($bytes) !== $file->bytes) $summary = ['status' => 'rejected', 'checks' => ['integrity' => 'rejected'], 'usable' => false];
        } catch (Throwable) {
            $summary = ['status' => 'error', 'checks' => ['storage' => 'error'], 'usable' => false];
        }
        return ['inspection' => $summary, 'mutated' => false, 'release_authorized' => false,
            'requires' => ['locked evidence/version check', 'persistent attempt and audit history', 'separate release approval']];
    }
}
