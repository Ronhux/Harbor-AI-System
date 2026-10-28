<?php

namespace Tests\Feature;

use App\Models\PolicyAcceptance;
use App\Models\Producer;
use App\Models\User;
use App\Services\Policies\PolicyAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Focused coverage for the policy agreement gate, versioned acceptance
 * records, first-login acceptance, idempotency, and rejection of forged
 * identity or timestamp evidence.
 */
class PolicyAcceptanceGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * These tests own their acceptance records explicitly.
     */
    protected bool $autoAcceptPolicies = false;

    private function acceptancePayload(array $overrides = []): array
    {
        return array_merge([
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => (string) config('policies.terms.version'),
            'privacy_policy_version' => (string) config('policies.privacy_policy.version'),
        ], $overrides);
    }

    private static int $rsbsaSequence = 900;

    private function producer(array $values = []): User
    {
        $user = User::create(array_merge([
            'email' => 'gate-producer-' . $this->nextMobile() . '@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Gate',
            'last_name' => 'Producer',
            'contact_number' => $this->nextMobile(),
            'user_type' => 'Farmer',
            'is_active' => true,
        ], $values));

        Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => sprintf('02-15-05-%03d-%06d', self::$rsbsaSequence, self::$rsbsaSequence++),
            'location' => 'Aparri',
            'primary_product_type' => 'Corn',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        return $user;
    }

    private function buyer(array $values = []): User
    {
        $user = User::create(array_merge([
            'email' => 'gate-buyer-' . $this->nextMobile() . '@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Gate',
            'last_name' => 'Buyer',
            'contact_number' => $this->nextMobile(),
            'user_type' => 'Buyer',
            'is_active' => true,
        ], $values));

        \App\Models\Buyer::create([
            'user_id' => $user->user_id,
            'organization_name' => 'Gate Org',
            'buyer_type' => 'Institution',
            'contact_person' => 'Gate Buyer',
            'verification_status' => 'Verified',
        ]);

        return $user;
    }

    private function admin(array $values = []): User
    {
        $user = User::create(array_merge([
            'email' => 'gate-admin-' . $this->nextMobile() . '@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Gate',
            'last_name' => 'Admin',
            'contact_number' => $this->nextMobile(),
            'user_type' => 'Admin',
            'is_active' => true,
        ], $values));

        \App\Models\Admin::create([
            'user_id' => $user->user_id,
            'role' => 'Admin',
        ]);

        return $user;
    }

    /**
     * Fresh mobile number per helper call so each helper builds a distinct
     * account under the new UNIQUE constraint.
     */
    private function nextMobile(): string
    {
        static $sequence = 200;

        return sprintf('0917%07d', $sequence++);
    }

    private function accept(User $user): void
    {
        app(PolicyAcceptanceService::class)->recordActiveAcceptances($user);
    }

    // -----------------------------------------------------------------
    // Public policy metadata
    // -----------------------------------------------------------------

    public function test_the_active_policy_versions_are_public(): void
    {
        $this->getJson('/api/policies')
            ->assertOk()
            ->assertJsonPath('policies.terms.version', config('policies.terms.version'))
            ->assertJsonPath('policies.privacy_policy.version', config('policies.privacy_policy.version'))
            ->assertJsonPath('policies.terms.path', '/terms-and-conditions')
            ->assertJsonPath('policies.privacy_policy.path', '/privacy');
    }

    // -----------------------------------------------------------------
    // Gate enforcement
    // -----------------------------------------------------------------

    public function test_a_pending_account_cannot_reach_dashboard_features_but_can_reach_the_agreement_endpoints(): void
    {
        $producer = $this->producer();

        $this->actingAs($producer)
            ->getJson('/api/producer/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('code', 'policy_acceptance_required')
            ->assertJsonCount(2, 'pending');

        $this->actingAs($producer)
            ->getJson('/api/buyer/products')
            ->assertStatus(403)
            ->assertJsonPath('code', 'policy_acceptance_required');

        $this->actingAs($producer)
            ->getJson('/api/policies/acceptance/status')
            ->assertOk()
            ->assertJsonPath('accepted', false)
            ->assertJsonCount(2, 'pending');
    }

    public function test_every_dashboard_role_is_gated_and_the_gate_never_grants_extra_permission(): void
    {
        $cases = [
            [$this->producer(), '/api/producer/dashboard'],
            [$this->buyer(), '/api/buyer/products'],
            [$this->admin(), '/api/admin/reports'],
        ];

        foreach ($cases as [$user, $path]) {
            $this->actingAs($user)
                ->getJson($path)
                ->assertStatus(403)
                ->assertJsonPath('code', 'policy_acceptance_required');
        }

        // A pending buyer still cannot perform a buyer-only action after
        // acceptance is recorded for a different account: acceptance is per
        // account, never a role grant.
        $pendingBuyer = $this->buyer(['email' => 'other-buyer@example.test', 'contact_number' => '09170000104']);
        $this->accept($this->buyer());

        $this->actingAs($pendingBuyer)
            ->getJson('/api/buyer/products')
            ->assertStatus(403)
            ->assertJsonPath('code', 'policy_acceptance_required');
    }

    public function test_the_gate_blocks_admin_operations_until_the_admin_also_accepts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/api/admin/reports')
            ->assertStatus(403);

        $this->actingAs($admin)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload())
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->actingAs($admin)->getJson('/api/admin/reports')->assertOk();
    }

    public function test_an_unauthenticated_request_is_not_treated_as_a_pending_acceptance(): void
    {
        $this->getJson('/api/producer/dashboard')->assertStatus(401);
        $this->postJson('/api/policies/acceptance', $this->acceptancePayload())->assertStatus(401);
        $this->getJson('/api/policies/acceptance/status')->assertStatus(401);
    }

    // -----------------------------------------------------------------
    // First-login acceptance
    // -----------------------------------------------------------------

    public function test_first_login_acceptance_unlocks_the_dashboard(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)->getJson('/api/buyer/products')->assertStatus(403);

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload())
            ->assertOk();

        $this->actingAs($buyer)->getJson('/api/buyer/products')->assertOk();

        $this->actingAs($buyer)
            ->getJson('/api/policies/acceptance/status')
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('pending', []);
    }

    public function test_repeated_acceptance_is_idempotent_and_keeps_the_original_timestamp(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/policies/acceptance', $this->acceptancePayload())->assertOk();

        $first = PolicyAcceptance::query()->where('user_id', $buyer->user_id)->orderBy('policy_type')->get()
            ->mapWithKeys(fn (PolicyAcceptance $row) => [$row->policy_type => $row->accepted_at->toIso8601String()]);

        $this->travel(3)->hours();

        $this->actingAs($buyer)->postJson('/api/policies/acceptance', $this->acceptancePayload())->assertOk();
        $this->actingAs($buyer)->postJson('/api/policies/acceptance', $this->acceptancePayload())->assertOk();

        $second = PolicyAcceptance::query()->where('user_id', $buyer->user_id)->orderBy('policy_type')->get()
            ->mapWithKeys(fn (PolicyAcceptance $row) => [$row->policy_type => $row->accepted_at->toIso8601String()]);

        $this->assertSame($first->all(), $second->all());
        $this->assertCount(2, PolicyAcceptance::query()->where('user_id', $buyer->user_id)->get());
    }

    public function test_missing_or_false_acknowledgments_are_rejected_on_the_acceptance_endpoint(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload(['terms_accepted' => false]))
            ->assertStatus(422)
            ->assertJsonPath('status', 'policy_update_required')
            ->assertJsonValidationErrors('terms_accepted');

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload(['privacy_acknowledged' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('privacy_acknowledged');

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted', 'privacy_acknowledged', 'terms_version', 'privacy_policy_version']);

        $this->assertDatabaseCount('policy_acceptances', 0);
        $this->actingAs($buyer)->getJson('/api/buyer/products')->assertStatus(403);
    }

    public function test_an_outdated_version_blocks_acceptance_until_the_current_version_is_accepted(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload([
                'terms_version' => '2020-01-01',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms_version');

        $this->assertDatabaseCount('policy_acceptances', 0);

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload([
                'terms_accepted' => true,
                'privacy_acknowledged' => true,
                'terms_version' => (string) config('policies.terms.version'),
                'privacy_policy_version' => (string) config('policies.privacy_policy.version'),
            ]))
            ->assertOk();

        $this->actingAs($buyer)->getJson('/api/buyer/products')->assertOk();
    }

    public function test_a_new_policy_version_re_gates_every_already_accepted_account(): void
    {
        $buyer = $this->buyer();
        $this->accept($buyer);
        $this->actingAs($buyer)->getJson('/api/buyer/products')->assertOk();

        config()->set('policies.terms.version', '2026-11-01');

        $this->actingAs($buyer)
            ->getJson('/api/policies/acceptance/status')
            ->assertOk()
            ->assertJsonPath('accepted', false);

        $this->actingAs($buyer)
            ->getJson('/api/buyer/products')
            ->assertStatus(403)
            ->assertJsonPath('code', 'policy_acceptance_required');

        // The earlier acceptance row is retained as historical evidence: the
        // version it was recorded against is never rewritten.
        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $buyer->user_id,
            'policy_type' => 'terms',
            'policy_version' => '2026-10-08',
        ]);

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload([
                'terms_version' => '2026-11-01',
            ]))
            ->assertOk();

        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $buyer->user_id,
            'policy_type' => 'terms',
            'policy_version' => '2026-11-01',
        ]);

        $this->actingAs($buyer)->getJson('/api/buyer/products')->assertOk();
    }

    // -----------------------------------------------------------------
    // Forged identity and timestamp evidence
    // -----------------------------------------------------------------

    public function test_a_forged_user_id_or_timestamp_never_creates_acceptance_for_another_account(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer(['email' => 'unrelated@example.test', 'contact_number' => '09170000105']);

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload([
                'user_id' => $other->user_id,
                'accepted_at' => '1990-01-01T00:00:00Z',
                'accepted_at_utc' => '1990-01-01T00:00:00Z',
                'policy_accepted_at' => '1990-01-01T00:00:00Z',
                'userId' => $other->user_id,
            ]))
            ->assertOk();

        $this->assertDatabaseMissing('policy_acceptances', ['user_id' => $other->user_id]);
        $this->assertDatabaseHas('policy_acceptances', ['user_id' => $buyer->user_id]);

        foreach (PolicyAcceptance::query()->where('user_id', $buyer->user_id)->get() as $row) {
            $this->assertGreaterThan(2000, (int) $row->accepted_at->year);
        }
    }

    public function test_a_forged_accepted_at_does_not_backdate_the_recorded_acceptance(): void
    {
        $buyer = $this->buyer();

        $frozen = now()->subYears(5);
        $this->travelTo($frozen);

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload([
                'accepted_at' => '1970-01-01T00:00:00Z',
            ]))
            ->assertOk();

        $rows = PolicyAcceptance::query()->where('user_id', $buyer->user_id)->get();
        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame(
                $frozen->toDateString(),
                $row->accepted_at->toDateString(),
                'Acceptance timestamps come from the server clock, not from the submitted payload.'
            );
        }
    }

    public function test_the_acceptance_endpoint_ignores_a_claimed_policy_version_that_is_not_active(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->postJson('/api/policies/acceptance', $this->acceptancePayload([
                'terms_version' => (string) config('policies.terms.version'),
                'privacy_policy_version' => (string) config('policies.terms.version'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('privacy_policy_version');

        $this->assertDatabaseCount('policy_acceptances', 0);
    }

    public function test_an_existing_acceptance_record_cannot_be_rewritten(): void
    {
        $buyer = $this->buyer();
        $this->accept($buyer);

        $row = PolicyAcceptance::query()->where('user_id', $buyer->user_id)->firstOrFail();
        $originalTime = $row->accepted_at->toIso8601String();
        $originalVersion = $row->policy_version;

        // A direct attribute assignment that tries to move the acceptance to
        // another account, another version, or another date is discarded.
        $row->user_id = 999999;
        $row->policy_version = 'forged';
        $row->accepted_at = now()->addYear();
        $this->assertFalse($row->save());

        $row->refresh();
        $this->assertSame($originalTime, $row->accepted_at->toIso8601String());
        $this->assertSame($originalVersion, $row->policy_version);
        $this->assertSame($buyer->user_id, (int) $row->user_id);

        // `accepted_at` is not mass assignable at all.
        $second = PolicyAcceptance::query()->where('user_id', $buyer->user_id)->firstOrFail();
        $second->fill(['accepted_at' => now()->subYears(10), 'policy_type' => 'forged']);
        $this->assertSame($originalTime, $second->accepted_at->toIso8601String());
        $this->assertSame($originalVersion, $second->fresh()->policy_version);
    }

    // -----------------------------------------------------------------
    // Combination with role restrictions
    // -----------------------------------------------------------------

    public function test_the_agreement_gate_does_not_bypass_role_restrictions(): void
    {
        $buyer = $this->buyer();
        $this->accept($buyer);

        $this->actingAs($buyer)
            ->getJson('/api/admin/reports')
            ->assertStatus(403);

        $producer = $this->producer();
        $this->accept($producer);

        $this->actingAs($producer)
            ->getJson('/api/admin/reports')
            ->assertStatus(403);

        $this->actingAs($producer)
            ->getJson('/api/buyer/products')
            ->assertStatus(403);

        $this->actingAs($producer)
            ->getJson('/api/producer/dashboard')
            ->assertOk();
    }
}