<?php

namespace Tests\Feature;

use App\Models\PolicyAcceptance;
use App\Models\Producer;
use App\Models\RegisteredProducer;
use App\Models\User;
use App\Support\PhilippineMobile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Focused coverage for public registration and sign-in with an optional email,
 * a required mobile number, and affirmative Terms/Privacy acceptance.
 */
class RegistrationMobileAndAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private int $registrySequence = 0;

    private function registry(array $overrides = []): RegisteredProducer
    {
        $sequence = ++$this->registrySequence;

        return RegisteredProducer::create(array_merge([
            'rsbsa_number' => sprintf('02-15-05-%03d-%06d', $sequence, $sequence),
            'first_name' => 'Acceptance',
            'middle_name' => 'Test',
            'last_name' => 'Farmer',
            'sex' => 'Male',
            'barangay' => 'Centro ' . $sequence,
            'municipality' => 'Aparri',
            'province' => 'Cagayan',
            'primary_livelihood' => 'Synthetic Corn',
            'producer_type' => 'Farmer',
        ], $overrides));
    }

    private function producerPayload(array $overrides = []): array
    {
        $registry = $this->registry();

        return array_merge([
            'first_name' => $registry->first_name,
            'middle_name' => $registry->middle_name,
            'last_name' => $registry->last_name,
            'sex' => $registry->sex,
            'barangay' => $registry->barangay,
            'rsbsa_number' => $registry->rsbsa_number,
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'password' => 'secret123',
        ], $this->acceptancePayload(), $overrides);
    }

    private function acceptancePayload(array $overrides = []): array
    {
        return array_merge([
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => (string) config('policies.terms.version'),
            'privacy_policy_version' => (string) config('policies.privacy_policy.version'),
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Optional email / required mobile
    // -----------------------------------------------------------------

    public function test_farmer_email_is_optional_and_the_mobile_number_is_required(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000001',
        ]))->assertCreated();

        $user = User::query()->whereNull('email')->firstOrFail();
        $this->assertSame('09170000001', $user->contact_number);
        $this->assertTrue(Schema::hasColumn('users', 'email'));

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => 'not-a-number',
        ]))->assertUnprocessable()->assertJsonValidationErrors('contact_number');

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '  ',
        ]))->assertUnprocessable()->assertJsonValidationErrors('contact_number');
    }

    public function test_mobile_numbers_are_stored_in_canonical_local_form(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '+63 917 000 0002',
        ]))->assertCreated();

        $this->assertDatabaseHas('users', [
            'contact_number' => '09170000002',
            'login_number' => '09170000002',
        ]);

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '639170000003',
        ]))->assertCreated();

        $this->assertDatabaseHas('users', [
            'contact_number' => '09170000003',
            'login_number' => '09170000003',
        ]);

        $this->assertSame('09170000002', PhilippineMobile::normalize('+639170000002'));
        $this->assertNull(PhilippineMobile::normalize('0917123'));
        $this->assertNull(PhilippineMobile::normalize('+1 202 555 0143'));
    }

    public function test_a_mobile_number_already_used_by_another_account_is_rejected_in_every_format(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000004',
        ]))->assertCreated();

        foreach (['+639170000004', '639170000004', '0917 000 0004', '0917-000-0004'] as $variant) {
            $this->postJson('/api/register', $this->producerPayload([
                'contact_number' => $variant,
                'email' => 'variant-' . substr(md5($variant), 0, 8) . '@example.test',
            ]))->assertUnprocessable()
                ->assertJsonValidationErrors('contact_number');

            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_the_database_rejects_a_duplicate_canonical_mobile_number_even_when_the_pre_check_is_bypassed(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000005',
        ]))->assertCreated();

        $existing = User::query()->firstOrFail();

        // Simulate the concurrent-registration race the pre-check cannot see:
        // a second account is written directly, bypassing the PHP check.
        $this->assertTrue(Schema::hasColumn('users', 'login_number'));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        DB::table('users')->insert([
            'email' => 'race-loser@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Race',
            'last_name' => 'Loser',
            'contact_number' => '09170000005',
            'login_number' => '09170000005',
            'user_type' => 'Farmer',
            'is_active' => true,
        ]);
    }

    public function test_a_registration_that_loses_the_duplicate_race_returns_a_field_error_not_a_server_error(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000006',
        ]))->assertCreated();

        // Force the pre-check to miss by clearing the canonical column the
        // controller consults, then submit a different representation of the
        // same number.
        DB::table('users')->update(['login_number' => null]);

        $registry = $this->registry();

        $response = $this->postJson('/api/register', array_merge([
            'first_name' => $registry->first_name,
            'middle_name' => $registry->middle_name,
            'last_name' => $registry->last_name,
            'sex' => $registry->sex,
            'barangay' => $registry->barangay,
            'rsbsa_number' => $registry->rsbsa_number,
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'password' => 'secret123',
            'contact_number' => '+639170000006',
            'email' => 'race-loser@example.test',
        ], $this->acceptancePayload()));

        $response->assertUnprocessable()->assertJsonValidationErrors('contact_number');
        $this->assertDatabaseCount('users', 1);
    }

    // -----------------------------------------------------------------
    // Acknowledgment enforcement at registration
    // -----------------------------------------------------------------

    public function test_missing_acknowledgments_are_rejected_and_no_account_is_created(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000007',
            'terms_accepted' => null,
            'privacy_acknowledged' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['terms_accepted', 'privacy_acknowledged']);

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000008',
            'terms_accepted' => null,
            'privacy_acknowledged' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['terms_accepted', 'privacy_acknowledged']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('policy_acceptances', 0);
    }

    public function test_false_acknowledgments_are_rejected_independently(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000009',
            'terms_accepted' => false,
            'privacy_acknowledged' => true,
        ]))->assertUnprocessable()->assertJsonValidationErrors('terms_accepted');

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000010',
            'terms_accepted' => true,
            'privacy_acknowledged' => false,
        ]))->assertUnprocessable()->assertJsonValidationErrors('privacy_acknowledged');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_outdated_policy_versions_are_rejected_even_with_true_acknowledgments(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000011',
            'terms_version' => '2020-01-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('terms_version');

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000012',
            'privacy_policy_version' => '2020-01-01',
        ]))->assertUnprocessable()->assertJsonValidationErrors('privacy_policy_version');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_successful_registration_records_versioned_acceptance_rows_for_the_new_account(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000013',
        ]))->assertCreated();

        $user = User::query()->firstOrFail();

        foreach (config('policies') as $policy) {
            $this->assertDatabaseHas('policy_acceptances', [
                'user_id' => $user->user_id,
                'policy_type' => $policy['type'],
                'policy_version' => $policy['version'],
            ]);
        }

        $row = PolicyAcceptance::query()->where('user_id', $user->user_id)->firstOrFail();
        $this->assertTrue($row->accepted_at->isToday());
    }

    public function test_account_creation_rolls_back_when_acceptance_persistence_fails(): void
    {
        config()->set('policies', [
            'terms' => [
                'type' => 'terms',
                'title' => 'Terms',
                'version' => 'test-terms',
                'path' => '/terms',
            ],
        ]);

        // Break acceptance persistence after the account row is written.
        PolicyAcceptance::creating(fn () => throw new \RuntimeException('policy store unavailable'));

        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000014',
            'terms_version' => 'test-terms',
            'privacy_policy_version' => 'test-terms',
        ]))->assertStatus(500);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('producers', 0);
        $this->assertDatabaseCount('policy_acceptances', 0);
    }

    // -----------------------------------------------------------------
    // Sign-in with a mobile number or an email address
    // -----------------------------------------------------------------

    public function test_a_producer_can_sign_in_with_a_mobile_number_in_any_format(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000015',
            'email' => 'mobile-login@example.test',
        ]))->assertCreated();

        Producer::query()->whereHas('user',fn($q)=>$q->where('contact_number','09170000015'))->update(['verification_status'=>'Verified']);
        foreach (['09170000015', '+639170000015', '639170000015', '0917 000 0015'] as $identifier) {
            $response = $this->postJson('/api/login', [
                'identifier' => $identifier,
                'password' => 'secret123',
            ])->assertOk();

            $this->assertNotEmpty($response->json('token'));
            $this->assertSame('mobile-login@example.test', $response->json('user.email'));
        }
    }

    public function test_an_account_can_sign_in_with_its_email_address(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000016',
            'email' => 'email-login@example.test',
        ]))->assertCreated();

        Producer::query()->whereHas('user',fn($q)=>$q->where('email','email-login@example.test'))->update(['verification_status'=>'Verified']);
        $this->postJson('/api/login', [
            'identifier' => 'email-login@example.test',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('user.email', 'email-login@example.test')
            ->assertJsonPath('user.user_type', 'Farmer');
    }

    public function test_a_producer_without_an_email_can_still_sign_in_with_a_mobile_number(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000017',
            'email' => null,
        ]))->assertCreated();

        $this->assertDatabaseHas('users', ['contact_number' => '09170000017', 'email' => null]);

        Producer::query()->whereHas('user',fn($q)=>$q->where('contact_number','09170000017'))->update(['verification_status'=>'Verified']);
        $this->postJson('/api/login', [
            'identifier' => '09170000017',
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('user.email', null);

        // An empty email identifier is not treated as a match.
        $this->postJson('/api/login', [
            'identifier' => '09170000017',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_a_correct_mobile_number_with_a_wrong_password_does_not_authenticate(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000018',
        ]))->assertCreated();

        $this->postJson('/api/login', [
            'identifier' => '+639170000018',
            'password' => 'not-the-password',
        ])->assertStatus(401)->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_the_producer_profile_and_listings_are_reachable_after_mobile_sign_in(): void
    {
        $this->postJson('/api/register', $this->producerPayload([
            'contact_number' => '09170000019',
        ]))->assertCreated();

        // Registration creates a Pending producer. Protected Producer
        // surfaces need a separate Admin approval, so approve first.
        // actingAs() is used deliberately: logging in over HTTP would leave
        // a session cookie that the shared auth guard then resolves for the
        // following producer request, authenticating it as the Admin.
        $admin = User::create([
            'email' => 'approver@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Approving',
            'last_name' => 'Admin',
            'contact_number' => '09170000088',
            'user_type' => 'Admin',
            'is_active' => true,
        ]);
        app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($admin);

        $producerId = Producer::query()->value('producer_id');
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producerId . '/verification', [
                'verification_status' => 'Verified',
            ])->assertOk();

        $this->app['auth']->forgetGuards();

        $token = $this->postJson('/api/login', [
            'identifier' => '09170000019',
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/producer/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/producer/listings')->assertOk();
        $this->withToken($token)->getJson('/api/producer/profile')->assertOk()
            ->assertJsonPath('data.name', 'Acceptance Farmer')
            ->assertJsonPath('data.contact_number', '09170000019');

        $this->assertDatabaseHas('producers', [
            'user_id' => User::query()->firstOrFail()->user_id,
            'verification_status' => 'Verified',
        ]);
    }

    public function test_a_contact_number_owned_by_another_account_cannot_be_taken_over_on_a_profile_save(): void
    {
        $owner = $this->buyerAccount('owner@example.test', '09170000020');
        $other = $this->buyerAccount('other@example.test', '09170000021');

        $token = $this->postJson('/api/login', [
            'identifier' => 'other@example.test',
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->withToken($token)->putJson('/api/buyer/profile', [
            'organization_name' => 'Other Org',
            'contact_person' => 'Other Person',
            'email' => 'other@example.test',
            'contact_number' => '+639170000020',
        ])->assertUnprocessable()->assertJsonValidationErrors('contact_number');

        $this->assertSame('09170000021', $other->fresh()->contact_number);
        $this->assertSame('09170000020', $owner->fresh()->contact_number);
    }

    private function buyerAccount(string $email, string $mobile): User
    {
        $user = User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Number',
            'last_name' => 'Holder',
            'contact_number' => $mobile,
            'user_type' => 'Buyer',
            'is_active' => true,
        ]);

        \App\Models\Buyer::create([
            'user_id' => $user->user_id,
            'organization_name' => 'Org ' . $email,
            'buyer_type' => 'Institution',
            'contact_person' => 'Person',
            'verification_status' => 'Verified',
        ]);

        app(\App\Services\Policies\PolicyAcceptanceService::class)->recordActiveAcceptances($user);

        return $user;
    }

    public function test_an_account_keeps_its_own_mobile_number_when_saving_the_profile_again(): void
    {
        $user = User::create([
            'email' => 'keeper@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Keeper',
            'last_name' => 'Number',
            'contact_number' => '09170000022',
            'user_type' => 'Farmer',
        ]);
        Producer::create([
            'user_id' => $user->user_id,
            'rsbsa_number' => '02-15-05-777-000777',
            'location' => 'Aparri',
            'primary_product_type' => 'Corn',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $this->actingAs($user)
            ->putJson('/api/producer/profile', [
                'email' => 'keeper@example.test',
                'contact_number' => '09170000022',
            ])->assertOk();

        $this->assertSame('09170000022', $user->fresh()->contact_number);
    }
}