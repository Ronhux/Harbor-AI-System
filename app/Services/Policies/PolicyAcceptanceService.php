<?php

namespace App\Services\Policies;

use App\Models\PolicyAcceptance;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Central authority for active policy versions, acceptance validation,
 * pending-acceptance checks, and durable acceptance record persistence.
 */
class PolicyAcceptanceService
{
    /**
     * Active policies keyed by config key, with their server-controlled
     * type/version/title/path metadata.
     *
     * @return array<string, array<string, string>>
     */
    public function activePolicies(): array
    {
        $policies = [];
        foreach (config('policies', []) as $key => $policy) {
            if (!is_array($policy)) {
                continue;
            }
            $policies[(string) $key] = [
                'type' => (string) ($policy['type'] ?? $key),
                'title' => (string) ($policy['title'] ?? ''),
                'version' => (string) ($policy['version'] ?? ''),
                'path' => (string) ($policy['path'] ?? '/'),
            ];
        }

        return $policies;
    }

    /**
     * @return list<string>
     */
    public function activeVersions(): array
    {
        return array_values(array_map(
            static fn (array $policy): string => $policy['version'],
            $this->activePolicies()
        ));
    }

    /**
     * Validation rules for affirmative acceptance fields shared by public
     * registration and the authenticated acceptance endpoint.
     *
     * @return array<string, mixed>
     */
    public function acceptanceRules(): array
    {
        return [
            'terms_accepted' => ['required', 'accepted'],
            'privacy_acknowledged' => ['required', 'accepted'],
            'terms_version' => ['required', 'string', Rule::in($this->activeVersions())],
            'privacy_policy_version' => ['required', 'string', Rule::in($this->activeVersions())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function acceptanceMessages(): array
    {
        $termsVersion = (string) config('policies.terms.version', '');
        $privacyVersion = (string) config('policies.privacy_policy.version', '');

        return [
            'terms_accepted.required' => 'You must agree to the HarborAI Terms and Conditions to create an account.',
            'terms_accepted.accepted' => 'You must agree to the HarborAI Terms and Conditions to create an account.',
            'privacy_acknowledged.required' => 'You must acknowledge that you have read the HarborAI Privacy Policy.',
            'privacy_acknowledged.accepted' => 'You must acknowledge that you have read the HarborAI Privacy Policy.',
            'terms_version.in' => 'The HarborAI Terms and Conditions have been updated. Please review the current version and accept it again.',
            'terms_version.required' => 'The Terms and Conditions version is required. Please review and accept the current terms.',
            'privacy_policy_version.in' => 'The HarborAI Privacy Policy has been updated. Please review the current version and acknowledge it again.',
            'privacy_policy_version.required' => 'The Privacy Policy version is required. Please review and acknowledge the current policy.',
            'terms_version.*' => 'The Terms and Conditions version could not be verified against the active version (expected ' . $termsVersion . ').',
            'privacy_policy_version.*' => 'The Privacy Policy version could not be verified against the active version (expected ' . $privacyVersion . ').',
        ];
    }

    /**
     * Validate an acceptance payload; returns formatted error bags, or null
     * when the payload is valid. Never trusts arbitrary client versions or
     * client timestamps as acceptance evidence.
     *
     * @return array<string, list<string>>|null
     */
    public function validateAcceptance(array $data): ?array
    {
        $validator = Validator::make($data, $this->acceptanceRules(), $this->acceptanceMessages());

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        $active = $this->activePolicies();
        $errors = [];

        if (($data['terms_version'] ?? null) !== ($active['terms']['version'] ?? null)) {
            $errors['terms_version'][] = 'The HarborAI Terms and Conditions have been updated. Please review the current version and accept it again.';
        }

        if (($data['privacy_policy_version'] ?? null) !== ($active['privacy_policy']['version'] ?? null)) {
            $errors['privacy_policy_version'][] = 'The HarborAI Privacy Policy has been updated. Please review the current version and acknowledge it again.';
        }

        return $errors === [] ? null : $errors;
    }

    /**
     * Active policies the user has not yet affirmatively accepted.
     *
     * @return list<array<string, string>>
     */
    public function pendingFor(User $user): array
    {
        $pending = [];

        foreach ($this->activePolicies() as $policy) {
            $accepted = PolicyAcceptance::query()
                ->where('user_id', $user->user_id)
                ->where('policy_type', $policy['type'])
                ->where('policy_version', $policy['version'])
                ->exists();

            if (!$accepted) {
                $pending[] = $policy;
            }
        }

        return $pending;
    }

    public function hasAcceptedAllActive(User $user): bool
    {
        return $this->pendingFor($user) === [];
    }

    /**
     * Persist versioned acceptance records for the account. Must be called
     * inside the account-creation transaction so a persistence failure rolls
     * back account creation. Repeated calls are idempotent and retain the
     * original server-generated timestamp.
     */
    public function recordActiveAcceptances(User $user): void
    {
        foreach ($this->activePolicies() as $policy) {
            PolicyAcceptance::query()->firstOrCreate([
                'user_id' => $user->user_id,
                'policy_type' => $policy['type'],
                'policy_version' => $policy['version'],
            ]);
        }
    }
}
