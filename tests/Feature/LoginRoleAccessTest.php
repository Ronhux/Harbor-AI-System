<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Role and status coverage for the public sign-in endpoint.
 *
 * Every account here is synthetic and created inside the in-memory SQLite test
 * database. No real account, credential or protected database is involved.
 */
class LoginRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function mobile(): string
    {
        return '0917' . str_pad((string) ++$this->seq, 7, '0', STR_PAD_LEFT);
    }

    private function makeUser(string $type, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'email' => $type . '-' . $this->seq . '@login.test',
            'password_hash' => Hash::make('secret123'),
            'first_name' => 'Test',
            'last_name' => $type,
            'contact_number' => $this->mobile(),
            'user_type' => $type,
            'is_active' => true,
        ], $overrides));

        // A real account carries its policy acceptances from registration, so
        // protected routes are reachable once authentication succeeds.
        app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($user);

        return $user;
    }

    private function approvedProducer(string $type = 'Farmer'): array
    {
        $user = $this->makeUser($type);
        Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        return [$user, $user->contact_number];
    }

    /**
     * Logs a user in and returns the issued Sanctum token.
     *
     * The shared auth guard caches the resolved user for the lifetime of the
     * application container, which a test method shares across requests, so the
     * guard is reset before each token is handed out. A real HTTP client builds
     * a fresh container per request and never sees this.
     */
    private function tokenFor(User $user): ?string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', [
            'identifier' => $user->contact_number,
            'password' => 'secret123',
        ])->assertOk()->json('token');
    }

    // -----------------------------------------------------------------
    // 5/6. Mobile and email identifier both authenticate
    // -----------------------------------------------------------------

    public function test_approved_producer_can_sign_in_with_a_mobile_number(): void
    {
        [$user, $mobile] = $this->approvedProducer();

        $response = $this->postJson('/api/login', ['identifier' => $mobile, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.user_id', $user->user_id)
            ->assertJsonPath('user.user_type', 'Farmer');

        $this->assertNotNull($response->json('token'));

        $this->withToken($response->json('token'))->getJson('/api/producer/dashboard')->assertOk();
    }

    public function test_approved_producer_can_sign_in_with_an_email_address(): void
    {
        [$user] = $this->approvedProducer();

        $this->postJson('/api/login', ['identifier' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.user_id', $user->user_id);
    }

    public function test_legacy_fisherfolk_account_still_authenticates_and_routes_to_producer(): void
    {
        [$user, $mobile] = $this->approvedProducer('Fisherfolk');

        $response = $this->postJson('/api/login', ['identifier' => $mobile, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.user_type', 'Fisherfolk');

        $this->withToken($response->json('token'))->getJson('/api/producer/dashboard')->assertOk();
    }

    // -----------------------------------------------------------------
    // 2/3/4. Buyer, Admin and Super Admin reach their own workspaces
    // -----------------------------------------------------------------

    public function test_buyer_admin_and_super_admin_each_authenticate_to_their_own_area(): void
    {
        $buyer = $this->makeUser('Buyer');
        Buyer::create(['user_id' => $buyer->user_id, 'organization_name' => 'Test Hospital', 'contact_person' => 'Test']);

        $admin = $this->makeUser('Admin');
        $super = $this->makeUser('Super Admin');

        $buyerToken = $this->tokenFor($buyer);
        $this->withToken($buyerToken)->getJson('/api/buyer/products')->assertOk();

        $adminToken = $this->tokenFor($admin);
        $this->withToken($adminToken)->getJson('/api/admin/producers/pending')->assertOk();

        $superToken = $this->tokenFor($super);
        $this->withToken($superToken)->getJson('/api/superadmin/overview')->assertOk();
    }

    public function test_buyer_cannot_reach_admin_routes(): void
    {
        $buyer = $this->makeUser('Buyer');
        Buyer::create(['user_id' => $buyer->user_id, 'organization_name' => 'Test Hospital', 'contact_person' => 'Test']);
        $token = $this->tokenFor($buyer);

        $this->withToken($token)->getJson('/api/admin/producers/pending')->assertForbidden();
        $this->withToken($token)->getJson('/api/superadmin/overview')->assertForbidden();
    }

    // -----------------------------------------------------------------
    // 7/8. Rejected credentials stay rejected, without leaking detail
    // -----------------------------------------------------------------

    public function test_wrong_password_and_unknown_identifier_are_both_rejected_generically(): void
    {
        [$user, $mobile] = $this->approvedProducer();

        $wrongPassword = $this->postJson('/api/login', ['identifier' => $mobile, 'password' => 'not-the-password'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');

        $unknown = $this->postJson('/api/login', ['identifier' => '09179999999', 'password' => 'secret123'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');

        // Identical wording for both: the response must not reveal whether the
        // account exists.
        $this->assertSame($wrongPassword->json('message'), $unknown->json('message'));
    }

    public function test_missing_credentials_return_validation_errors(): void
    {
        $this->postJson('/api/login', ['identifier' => '', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password']);
    }

    // -----------------------------------------------------------------
    // 9/10/11. Approval state still gates protected Producer surfaces
    // -----------------------------------------------------------------

    public function test_pending_and_rejected_producers_cannot_open_protected_dashboards(): void
    {
        foreach (['Pending', 'Rejected'] as $status) {
            $user = $this->makeUser('Farmer');
            Producer::create([
                'user_id' => $user->user_id,
                'verification_status' => $status,
                'producer_type' => 'Farmer',
            ]);

            $this->postJson('/api/login', ['identifier'=>$user->contact_number,'password'=>'secret123'])->assertForbidden()->assertJsonPath('registration_status',$status)->assertJsonMissingPath('token');
            $token = $user->createToken('synthetic-legacy-token')->plainTextToken;

            $this->withToken($token)->getJson('/api/producer/dashboard')
                ->assertForbidden()
                ->assertJsonPath('verification_required', true);
        }
    }

    public function test_registry_match_alone_does_not_grant_producer_access(): void
    {
        // A registry-matched producer is created Pending and must stay gated
        // until an administrator approves them.
        $user = $this->makeUser('Farmer');
        Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => '02-15-05-011-000123',
            'verification_status' => 'Pending',
            'producer_type' => 'Farmer',
        ]);

        $this->postJson('/api/login', ['identifier'=>$user->contact_number,'password'=>'secret123'])->assertForbidden()->assertJsonPath('registration_status','Pending')->assertJsonMissingPath('token');
        $token = $user->createToken('synthetic-legacy-token')->plainTextToken;
        $this->withToken($token)->getJson('/api/producer/dashboard')->assertForbidden();
    }

    public function test_inactive_account_cannot_use_protected_functionality(): void
    {
        // Suspending an account stops it at the door: the credential check still
        // passes, but sign-in is refused and no token is ever issued.
        $user = $this->makeUser('Admin', ['is_active' => false]);

        $this->postJson('/api/login', ['identifier' => $user->contact_number, 'password' => 'secret123'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account is inactive.');
    }

    // -----------------------------------------------------------------
    // 13. Policy acceptance still works for an authenticated account
    // -----------------------------------------------------------------

    public function test_authenticated_account_can_read_policy_acceptance_status(): void
    {
        [$user, $mobile] = $this->approvedProducer();
        $token = $this->postJson('/api/login', ['identifier' => $mobile, 'password' => 'secret123'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/policies/acceptance/status')->assertOk();
    }

    // -----------------------------------------------------------------
    // 15. Response categories are distinguishable by the frontend
    // -----------------------------------------------------------------

    public function test_failures_use_distinct_status_codes_so_the_ui_can_be_specific(): void
    {
        [$user, $mobile] = $this->approvedProducer();

        $this->postJson('/api/login', ['identifier' => $mobile, 'password' => 'secret123'])->assertOk();
        $this->postJson('/api/login', ['identifier' => $mobile, 'password' => 'wrong'])->assertStatus(401);
        $this->postJson('/api/login', ['identifier' => 'not-an-identifier', 'password' => 'secret123'])
            ->assertStatus(401);
        $this->postJson('/api/login', [])->assertStatus(422);
    }
}
