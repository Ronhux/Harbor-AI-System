<?php

namespace App\Policies;

use App\Models\FcaEvidence;
use App\Models\FcaRepresentative;
use App\Models\Producer;
use App\Models\User;

final class FcaWorkflowPolicy
{
    public function enabled(): void
    {
        abort_unless(config('mao_workflows.fca_applications_enabled') === true, 404);
    }

    public function applicant(User $actor, Producer $producer): bool
    {
        return app(MaoIdentityPolicy::class)->producer($actor, $producer);
    }

    public function reviewer(User $actor): bool
    {
        return app(MaoIdentityPolicy::class)->administrator($actor, 'fca_reviewer_user_ids');
    }

    /**
     * Read-only access to the FCA association registry.
     *
     * VIEWING A REGISTRY ENTRY IS NOT A REVIEW DECISION.
     *
     * reviewer() additionally demands an explicit fca_reviewer_user_ids assignment, because a
     * reviewer may approve an application, decide an evidence question and change a
     * verification status. Those are authority acts. Merely listing the associations that
     * already exist in fca_registry - name, address, listed president, current status - is not
     * one of them, and requiring a reviewer assignment for it meant an administrator with full
     * platform access could not see the directory at all.
     *
     * This grants a strictly narrower capability: READ of the registry only. reviewer() is not
     * modified, so nothing else changes:
     *   president verification       still requires reviewer
     *   application submission       still requires the workflow gate and an applicant
     *   evidence upload              still requires the workflow gate and an applicant
     *   manual review decisions      still require reviewer
     *   verification status changes  still require reviewer
     *   directory contacts           are never returned by this path
     *
     * An inactive account is refused, exactly as elsewhere.
     */
    public function registryViewer(User $actor): bool
    {
        return $actor->is_active === true
            && (int) $actor->getKey() > 0
            && in_array($actor->user_type, ['Admin', 'Super Admin'], true);
    }

    /** Authorization evidence proves FCA authority only, never participation or procurement. */
    public function representation(User $actor, Producer $producer, FcaRepresentative $rep, FcaEvidence $evidence, string $purpose): bool
    {
        return $purpose === 'fca_authority' && $this->applicant($actor, $producer)
            && (int) $rep->producer_id === (int) $producer->getKey() && $rep->status === 'authorized'
            && $rep->reviewed_by && !$rep->revoked_at && $rep->expires_at?->isFuture()
            && $rep->fca?->directory_verification_status === 'verified'
            && $rep->authorization_evidence_reference === $evidence->getKey()
            && (int) $evidence->application_id === (int) $rep->getKey()
            && (int) $evidence->producer_id === (int) $producer->getKey()
            && (int) $evidence->uploaded_by === (int) $actor->getKey()
            && $evidence->status === 'attached' && $evidence->hasSuccessfulInspection()
            && $this->presidentProof($rep, $actor);
    }

    private function presidentProof(FcaRepresentative $rep, User $actor): bool
    {
        if (!$rep->president_claim_id) return true; // Existing/delegated authority is unchanged.
        if (config('fca_president.workflow_enabled') !== true) return false;
        $claim = \App\Models\FcaPresidentClaim::whereKey($rep->president_claim_id)->lockForUpdate()->first();
        return $claim && (int)$claim->applicant_user_id === (int)$actor->getKey()
            && (int)$claim->fca_id === (int)$rep->fca_id
            && app(\App\Services\Mao\President\PresidentVerification::class)->currentProof($claim);
    }

    public function view(User $actor, FcaRepresentative $application): bool
    {
        return $this->reviewer($actor) || ($actor->is_active === true
            && $application->producer && $this->applicant($actor, $application->producer));
    }

    public function evidence(User $actor, FcaEvidence $evidence): bool
    {
        if (config('mao_workflows.fca_evidence_access_enabled') !== true || !$evidence->hasSuccessfulInspection()) return false;
        if ($evidence->application_id && in_array($evidence->application?->status, ['rejected', 'revoked'], true)) return false;
        if (!in_array($evidence->status, ['clean', 'attached'], true)) return false;
        if ($actor->is_active !== true) return false;
        if ((int) $evidence->uploaded_by === (int) $actor->getKey()) {
            $producer = Producer::find($evidence->producer_id);
            return $producer && $this->applicant($actor, $producer);
        }

        return $this->reviewer($actor) && $evidence->application_id !== null;
    }
}
