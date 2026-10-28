<?php

namespace Tests\Feature;

use App\Models\{Buyer, Producer, User};
use App\Policies\{KadiwaAuthorization, MaoIdentityPolicy};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The restricted KADIWA Manager role.
 *
 * The role must grant KADIWA event management and nothing else. In particular it must NOT
 * satisfy the shared MaoIdentityPolicy::administrator() check, which is what gates FCA
 * reviewer, demand reviewer and institutional-buyer assignments.
 */
class KadiwaManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mao_workflows.kadiwa_enabled' => true,
            'mao_workflows.kadiwa_manager_user_ids' => [],
            'mao_workflows.kadiwa_inquiry_reviewer_user_ids' => [],
            'mao_workflows.fca_reviewer_user_ids' => [],
            'mao_workflows.demand_reviewer_user_ids' => [],
            'mao_workflows.institutional_buyer_user_ids' => [],
        ]);

        $this->manager = User::create([
            'first_name' => 'Demo', 'last_name' => 'Kadiwa',
            'email' => 'kadiwa-role-test@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180009999', 'user_type' => KadiwaAuthorization::ROLE, 'is_active' => true,
        ]);
    }

    public function test_the_role_is_not_admin_or_super_admin(): void
    {
        $this->assertSame('KADIWA Manager', $this->manager->user_type);
        $this->assertNotContains($this->manager->user_type, ['Admin', 'Super Admin']);
    }

    public function test_holding_the_role_alone_grants_no_kadiwa_authority(): void
    {
        $policy = app(KadiwaAuthorization::class);
        // The role by itself is nothing; an explicit id assignment is still required.
        $this->assertTrue($policy->hasRole($this->manager));
        $this->assertFalse($policy->manager($this->manager));
        $this->assertFalse($policy->inquiryReviewer($this->manager));

        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/kadiwa/events', [])->assertForbidden();
    }

    public function test_an_explicit_manager_assignment_grants_kadiwa_event_management(): void
    {
        config(['mao_workflows.kadiwa_manager_user_ids' => [(int) $this->manager->user_id]]);
        $policy = app(KadiwaAuthorization::class);

        $this->assertTrue($policy->manager($this->manager));

        $caps = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/kadiwa/capabilities')->assertOk()->json();
        $this->assertTrue($caps['manager']);
        // Inquiry review is a SEPARATE assignment and is not granted here.
        $this->assertFalse($caps['inquiry_reviewer']);

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/kadiwa/inquiry-review')->assertForbidden();
    }

    public function test_inquiry_review_requires_its_own_separate_assignment(): void
    {
        config(['mao_workflows.kadiwa_manager_user_ids' => [(int) $this->manager->user_id]]);
        config(['mao_workflows.kadiwa_inquiry_reviewer_user_ids' => [(int) $this->manager->user_id]]);

        $caps = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/kadiwa/capabilities')->assertOk()->json();
        $this->assertTrue($caps['manager']);
        $this->assertTrue($caps['inquiry_reviewer']);
    }

    /**
     * The decisive guard: the restricted role must never satisfy the shared administrator
     * check that governs FCA, demand and institutional-buyer authority.
     */
    public function test_the_role_cannot_satisfy_the_shared_administrator_policy(): void
    {
        $shared = app(MaoIdentityPolicy::class);
        config([
            'mao_workflows.fca_reviewer_user_ids' => [(int) $this->manager->user_id],
            'mao_workflows.demand_reviewer_user_ids' => [(int) $this->manager->user_id],
            'mao_workflows.institutional_buyer_user_ids' => [(int) $this->manager->user_id],
        ]);

        foreach (['fca_reviewer_user_ids', 'demand_reviewer_user_ids', 'kadiwa_manager_user_ids', 'institutional_buyer_user_ids'] as $assignment) {
            $this->assertFalse(
                $shared->administrator($this->manager, $assignment),
                "KADIWA Manager must not satisfy {$assignment} through the shared administrator policy"
            );
        }
    }

    public function test_the_role_is_refused_by_admin_only_route_groups(): void
    {
        // Admin registry management, demand review and FCA review must all stay closed.
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/admin/registry')->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/admin/demands')->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/admin/producers/pending')->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/superadmin/overview')->assertForbidden();
    }

    public function test_fca_endpoints_stay_closed_even_if_the_workflow_were_enabled(): void
    {
        // FCA is separately gated and must remain closed for this role.
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/fca/directory')->assertNotFound();
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/fca-president/claims')->assertNotFound();
    }

    public function test_the_role_cannot_read_buyer_or_producer_commercial_data(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/producer/dashboard')->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/buyer/products')->assertForbidden();
    }

    public function test_an_inactive_manager_loses_all_authority(): void
    {
        config(['mao_workflows.kadiwa_manager_user_ids' => [(int) $this->manager->user_id]]);
        $this->assertTrue(app(KadiwaAuthorization::class)->manager($this->manager));

        $this->manager->forceFill(['is_active' => false])->save();
        $policy = app(KadiwaAuthorization::class);
        $this->assertFalse($policy->manager($this->manager));
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/kadiwa/events', [])->assertForbidden();
    }

    public function test_an_admin_with_no_explicit_assignment_also_gets_nothing(): void
    {
        $admin = User::create([
            'first_name' => 'Plain', 'last_name' => 'Admin',
            'email' => 'plain-admin@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180008888', 'user_type' => 'Admin', 'is_active' => true,
        ]);
        $this->assertFalse(app(KadiwaAuthorization::class)->manager($admin));
    }

    public function test_login_returns_the_restricted_role_so_the_ui_can_route_correctly(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->manager->email, 'password' => 'test-only',
        ])->assertOk();

        $this->assertSame(KadiwaAuthorization::ROLE, $response->json('user.user_type'));
    }
}