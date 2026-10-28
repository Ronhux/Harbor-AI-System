<?php

namespace Tests;

use App\Models\PolicyAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Tests\Traits\VerifiesTestDatabase;

    public function createApplication()
    {
        // Validate before RefreshDatabase or DatabaseMigrations can run.
        $this->verifyTestDatabaseTargetPreBoot();
        $app = parent::createApplication();
        $this->verifyTestDatabaseTargetPostBoot();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // No automated regression test may issue a real billable HTTP request.
        \Illuminate\Support\Facades\Http::preventStrayRequests();
    }

    /**
     * When true (the default), actingAs() records policy acceptances for the
     * given account so suites that are not about the agreement gate keep
     * focusing on their own concerns. Gate-specific suites set this to false
     * and control acceptance records explicitly.
     */
    protected bool $autoAcceptPolicies = true;

    /**
     * @param  array<int, string>  $abilities
     */
    public function actingAs($user, $driver = null, $abilities = [])
    {
        if ($this->autoAcceptPolicies && $user instanceof User) {
            foreach (config('policies', []) as $policy) {
                if (!is_array($policy)) {
                    continue;
                }

                PolicyAcceptance::query()->firstOrCreate([
                    'user_id' => $user->user_id,
                    'policy_type' => (string) ($policy['type'] ?? ''),
                    'policy_version' => (string) ($policy['version'] ?? ''),
                ]);
            }
        }

        return parent::actingAs($user, $driver, $abilities);
    }
}
