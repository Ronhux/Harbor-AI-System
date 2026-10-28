<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\RegisteredProducer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerProfileRegistryMatchTest extends TestCase
{
    use RefreshDatabase;

    private int $syntheticSequence = 0;

    /**
     * Synthetic user factory.
     *
     * `User::booted()` mirrors `contact_number` into `login_number`, and the
     * `users_login_number_unique` index rejects a second user sharing the same
     * number. Defaults are unique per call, drawn from a range this suite does
     * not otherwise use, so they cannot collide with an explicit number passed
     * through $values.
     */
    private function user(string $email, string $type, array $values = []): User
    {
        return User::create(array_merge([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'contact_number' => '0917999' . str_pad((string) ++$this->syntheticSequence, 4, '0', STR_PAD_LEFT),
            'user_type' => $type,
        ], $values));
    }

    /**
     * Active policy acceptance fields required by public registration.
     */
    private function policyPayload(): array
    {
        return [
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => (string) config('policies.terms.version'),
            'privacy_policy_version' => (string) config('policies.privacy_policy.version'),
        ];
    }

    public function test_registration_matches_registry_details_but_requires_admin_approval(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-001-000100',
            'first_name' => 'Synthetic',
            'middle_name' => 'Test',
            'last_name' => 'Farmer',
            'sex' => 'Male',
            'barangay' => 'Test Barangay',
            'municipality' => 'Test Municipality',
            'province' => 'Test Province',
            'primary_livelihood' => 'Synthetic Corn',
            'producer_type' => 'Farmer',
        ]);

        $response = $this->postJson('/api/register', array_merge([
            'first_name' => 'Synthetic',
            'middle_name' => 'Test',
            'last_name' => 'Farmer',
            'contact_number' => '09170000001',
            'email' => 'synthetic-registry-match@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => '  021505001000100  ',
            'sex' => 'Male',
            'barangay' => 'Test Barangay',
        ], $this->policyPayload()))->assertCreated()
            ->assertJsonPath('verification_status', 'Pending')
            ->assertJsonPath('token', null)
            ->assertJsonPath('registry_match', true)
            ->assertJsonPath('registry.rsbsa_number', '02-15-05-001-000100')
            ->assertJsonPath('registry.full_name', 'Synthetic Test Farmer')
            ->assertJsonMissingPath('registry.mobile_number')
            ->assertJsonMissingPath('registry.farm_location');

        $user = User::where('email', 'synthetic-registry-match@example.test')->firstOrFail();
        $producer = Producer::where('user_id', $user->user_id)->firstOrFail();
        $this->assertNotNull($user->getAttribute(User::accountCreatedAtColumn()));
        $this->assertSame('Farmer', $user->user_type);
        $this->assertSame('Test Municipality', $producer->location);
        $this->assertSame('Synthetic Corn', $producer->primary_product_type);
        $this->assertSame('Farmer', $producer->producer_type);
        $this->assertSame('Pending', $producer->verification_status);
        $this->assertSame(
            RegisteredProducer::where('rsbsa_number', '02-15-05-001-000100')->value('registry_id'),
            $producer->registered_producer_id
        );
        $this->assertTrue($response->json('registry_match'));
        $this->assertDatabaseHas('registered_producers', ['rsbsa_number' => '02-15-05-001-000100']);
        $this->assertSame('Producer account created successfully. Your registry information matched; administrator approval is pending.', $response->json('message'));
        $this->assertTrue((bool) $user->is_active);
    }

    public function test_registration_without_a_registry_match_is_rejected_before_account_creation(): void
    {
        $this->postJson('/api/register', array_merge([
            'first_name' => 'Synthetic',
            'middle_name' => 'Unmatched',
            'last_name' => 'Fisherfolk',
            'contact_number' => '09170000003',
            'email' => 'synthetic-unmatched-fisherfolk@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => '02-15-05-042-999999',
            'sex' => 'Female',
            'barangay' => 'Test Barangay',
        ], $this->policyPayload()))->assertStatus(404)
            ->assertJsonPath('status', 'registry_not_found');

        $this->assertDatabaseMissing('users', ['email' => 'synthetic-unmatched-fisherfolk@example.test']);
    }

    public function test_duplicate_email_and_invalid_producer_type_are_validation_errors(): void
    {
        $this->user('duplicate-registration@example.test', 'Farmer');
        $payload = array_merge([
            'first_name' => 'Synthetic',
            'last_name' => 'Applicant',
            'contact_number' => '09170000004',
            'email' => 'duplicate-registration@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => 'RSBSA-VALIDATION-TEST',
        ], $this->policyPayload());

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $payload['email'] = 'invalid-producer-type@example.test';
        $payload['producer_type'] = 'Institutional Buyer';
        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('producer_type');
    }

    public function test_registry_management_lists_records_and_only_links_farmer_or_fisherfolk_accounts(): void
    {
        $registry = RegisteredProducer::create([
            'rsbsa_number' => 'RSBSA-TEST-REGISTRY-SCOPE',
            'full_name' => 'Registry Farmer',
            'municipality' => 'Test Municipality',
            'producer_type' => 'Farmer',
        ]);
        $admin = $this->user('registry-scope-admin@example.test', 'Admin');
        $farmer = $this->user('registry-scope-farmer@example.test', 'Farmer', [
            'first_name' => 'Registry',
            'last_name' => 'Farmer',
        ]);
        $verifiedProducer = Producer::create([
            'user_id' => $farmer->user_id,
            'rsbsa_number' => $registry->rsbsa_number,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $excludedProducerIds = [];
        foreach (['Buyer', 'Admin', 'Super Admin'] as $type) {
            $excludedUser = $this->user(
                'registry-scope-excluded-' . strtolower(str_replace(' ', '-', $type)) . '@example.test',
                $type,
                ['first_name' => 'Excluded', 'last_name' => $type]
            );
            $excludedProducer = Producer::create([
                'user_id' => $excludedUser->user_id,
                'rsbsa_number' => $registry->rsbsa_number,
                'verification_status' => 'Pending',
                'producer_type' => 'Farmer',
            ]);
            $excludedProducerIds[] = $excludedProducer->producer_id;
        }

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/registry/search?name=Registry')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.registry_id', $registry->registry_id)
            ->assertJsonCount(1, 'data.0.producer_accounts')
            ->assertJsonPath('data.0.producer_accounts.0.producer_id', $verifiedProducer->producer_id)
            ->assertJsonPath('data.0.producer_accounts.0.verification_status', 'Verified')
            ->assertJsonPath('data.0.producer_accounts.0.link_status', 'Linked')
            ->assertJsonPath('data.0.producer_accounts.0.registry_match.valid_match', true)
            ->assertJsonPath('data.0.producer_accounts.0.account_status', 'Active');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/producers/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        foreach ($excludedProducerIds as $producerId) {
            $this->actingAs($admin, 'sanctum')
                ->patchJson('/api/admin/producers/' . $producerId . '/verification', [
                    'verification_status' => 'Verified',
                ])
                ->assertNotFound();
        }
    }

    public function test_registry_number_with_nonmatching_name_is_flagged_for_admin_review(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => 'RSBSA-TEST-200',
            'full_name' => 'Registry Person',
            'municipality' => 'Test Municipality',
        ]);
        $admin = $this->user('registry-review-admin@example.test', 'Admin');
        $applicant = $this->user('registry-review-applicant@example.test', 'Farmer', [
            'first_name' => 'Different',
            'last_name' => 'Person',
        ]);
        $producer = Producer::create([
            'user_id' => $applicant->user_id,
            'rsbsa_number' => 'RSBSA-TEST-200',
            'verification_status' => 'Pending',
            'producer_type' => 'Farmer',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/producers/pending')
            ->assertOk()
            ->assertJsonPath('data.0.producer_id', $producer->producer_id)
            ->assertJsonPath('data.0.registry_match.status', 'REGISTRY_MATCH_NAME_MISMATCH')
            ->assertJsonPath('data.0.registry_match.name_match', false)
            ->assertJsonPath('data.0.registry_match.registry_name', 'Registry Person');
    }

    public function test_producer_profile_updates_contact_details_only_and_keeps_registry_fields_authoritative(): void
    {
        $registry = RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-001-000101',
            'first_name' => 'Profile',
            'last_name' => 'Farmer',
            'sex' => 'Female',
            'barangay' => 'Backiling',
            'farm_location' => 'Backiling, Aparri, Cagayan',
            'area_ha' => '2.50',
        ]);
        $producerUser = $this->user('producer-profile@example.test', 'Farmer', [
            'first_name' => 'Profile',
            'last_name' => 'Farmer',
        ]);
        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'registered_producer_id' => $registry->registry_id,
            'rsbsa_number' => '02-15-05-001-000101',
            'location' => 'Official Municipality',
            'primary_product_type' => 'Official Livelihood',
            'producer_type' => 'Farmer',
            'verification_status' => 'Verified',
        ]);

        $this->actingAs($producerUser, 'sanctum')
            ->getJson('/api/producer/profile')
            ->assertOk()
            ->assertJsonPath('data.rsbsa_number', '02-15-05-001-000101')
            ->assertJsonPath('data.location', 'Official Municipality')
            ->assertJsonPath('data.registry.rsbsa_number', '02-15-05-001-000101')
            ->assertJsonPath('data.registry.full_name', 'Profile Farmer')
            ->assertJsonPath('data.registry.farm_location', 'Backiling, Aparri, Cagayan')
            ->assertJsonPath('data.registry.area_ha', 2.5);

        $this->actingAs($producerUser, 'sanctum')
            ->putJson('/api/producer/profile', [
                'email' => 'producer-profile-updated@example.test',
                'contact_number' => '09170000002',
                'rsbsa_number' => 'CHANGED',
                'location' => 'Changed Municipality',
                'primary_product_type' => 'Changed Livelihood',
                'producer_type' => 'Fisherfolk',
                'verification_status' => 'Verified',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'producer-profile-updated@example.test')
            ->assertJsonPath('data.contact_number', '09170000002')
            ->assertJsonPath('data.rsbsa_number', '02-15-05-001-000101')
            ->assertJsonPath('data.location', 'Official Municipality')
            ->assertJsonPath('data.primary_product_type', 'Official Livelihood')
            ->assertJsonPath('data.producer_type', 'Farmer');

        $this->assertSame('02-15-05-001-000101', $producer->fresh()->rsbsa_number);
        $this->assertSame('Official Municipality', $producer->fresh()->location);
        $this->assertSame('Official Livelihood', $producer->fresh()->primary_product_type);
        $this->assertSame('Farmer', $producer->fresh()->producer_type);
    }

    public function test_admin_can_suspend_and_reactivate_linked_producer_account(): void
    {
        $admin = $this->user('producer-account-status-admin@example.test', 'Admin');
        $producerUser = $this->user('producer-account-status@example.test', 'Farmer');
        $producer = Producer::create([
            'user_id' => $producerUser->user_id,
            'rsbsa_number' => '02-15-05-001-000501',
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producer->producer_id . '/account-status', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('account_status', 'Suspended');

        $this->assertFalse((bool) $producerUser->fresh()->is_active);
        $this->postJson('/api/login', [
            'email' => 'producer-account-status@example.test',
            'password' => 'secret123',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/producers/' . $producer->producer_id . '/account-status', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('account_status', 'Active');

        $this->assertTrue((bool) $producerUser->fresh()->is_active);
        $this->postJson('/api/login', [
            'email' => 'producer-account-status@example.test',
            'password' => 'secret123',
        ])->assertOk();
    }

    public function test_registry_verification_accepts_whitespace_variations_and_rejects_wrong_identity(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-999-000001',
            'first_name' => 'Huxel',
            'middle_name' => 'Rohn',
            'last_name' => 'SyntheticMiddle003',
            'extension_name' => 'SyntheticFamily004',
            'sex' => 'Male',
            'barangay' => 'Centro 8',
            'municipality' => 'Aparri',
            'producer_type' => 'Farmer',
        ]);

        $this->postJson('/api/registry/verify-producer', [
            'rsbsa_number' => '  02-15-05-999-000001  ',
            'first_name' => 'HUXEL',
            'middle_name' => '  Rohn ',
            'last_name' => 'SyntheticMiddle003',
            'extension_name' => 'SyntheticFamily004',
            'sex' => 'male',
            'barangay' => 'Centro 8',
        ])->assertOk()
            ->assertJsonPath('status', 'registry_verified');

        $this->postJson('/api/registry/verify-producer', [
            'rsbsa_number' => '02-15-05-999-000001',
            'first_name' => 'Huxel',
            'middle_name' => 'Rohn',
            'last_name' => 'SyntheticMiddle003',
            'extension_name' => 'SyntheticFamily004',
            'sex' => 'Female',
            'barangay' => 'Centro 8',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'registry_mismatch');
    }

    public function test_registry_search_finds_synthetic_aparri_references(): void
    {
        $records = [
            ['02-15-05-999-000001', 'SyntheticGiven002', 'SyntheticMiddle003', 'SyntheticFamily004', 'Centro 8'],
            ['02-15-05-999-000006', 'SyntheticGiven007', 'SyntheticMiddle008', 'SyntheticFamily009', 'Dodan'],
            ['02-15-05-999-000011', 'SyntheticGiven012', 'SyntheticMiddle013', 'SyntheticFamily014', 'Toran'],
        ];

        foreach ($records as [$rsbsaNumber, $firstName, $middleName, $lastName, $barangay]) {
            RegisteredProducer::create([
                'rsbsa_number' => $rsbsaNumber,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'barangay' => $barangay,
                'municipality' => 'Aparri',
            ]);
        }

        $admin = $this->user('synthetic-registry-search@example.test', 'Admin');

        foreach ($records as [$rsbsaNumber, $firstName]) {
            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/registry/search?name=' . urlencode($rsbsaNumber))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.rsbsa_number', $rsbsaNumber)
                ->assertJsonPath('data.0.first_name', $firstName);
        }
    }

    public function test_known_synthetic_aparri_records_verify_create_active_accounts_and_can_log_in(): void
    {
        (new \Database\Seeders\RegisteredProducerSeedDataSeeder())->run();

        $records = [
            ['02-15-05-999-000001', 'SyntheticGiven002', 'SyntheticMiddle003', 'SyntheticFamily004', 'Male', 'Centro 8'],
            ['02-15-05-999-000006', 'SyntheticGiven007', 'SyntheticMiddle008', 'SyntheticFamily009', 'Male', 'Dodan'],
            ['02-15-05-999-000011', 'SyntheticGiven012', 'SyntheticMiddle013', 'SyntheticFamily014', 'Female', 'Toran'],
        ];

        $registryBefore = RegisteredProducer::query()->orderBy('registry_id')->get()->toArray();

        foreach ($records as $index => [$rsbsa, $firstName, $middleName, $lastName, $sex, $barangay]) {
            $identity = [
                'rsbsa_number' => $rsbsa,
                'first_name' => mb_strtoupper($firstName),
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'extension_name' => '',
                'sex' => mb_strtolower($sex),
                'barangay' => $barangay,
            ];

            $this->postJson('/api/registry/verify-producer', $identity)
                ->assertOk()
                ->assertJsonPath('status', 'registry_verified')
                ->assertJsonPath('registry.rsbsa_number', $rsbsa)
                ->assertJsonMissingPath('registry.mobile_number')
                ->assertJsonMissingPath('registry.farm_location');

            $email = 'synthetic-aparri-' . $index . '@example.test';
            $this->postJson('/api/register', array_merge($identity, [
                'contact_number' => '091712345' . str_pad((string) (60 + $index), 2, '0', STR_PAD_LEFT),
                'email' => $email,
                'password' => 'secret123',
                'user_type' => 'producer',
                'producer_type' => 'Farmer',
            ], $this->policyPayload()))->assertCreated()
                ->assertJsonPath('verification_status', 'Pending')
                ->assertJsonPath('user.account_status', 'Active');

            $user = User::where('email', $email)->firstOrFail();
            $producer = Producer::where('user_id', $user->user_id)->firstOrFail();
            $this->assertTrue((bool) $user->is_active);
            $this->assertSame('Pending', $producer->verification_status);
            $this->assertSame(
                RegisteredProducer::where('rsbsa_number', $rsbsa)->value('registry_id'),
                $producer->registered_producer_id
            );

            $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'secret123',
            ])->assertForbidden()
                ->assertJsonPath('registration_status', 'Pending')->assertJsonMissingPath('token');
        }

        $this->assertSame(50, RegisteredProducer::count());
        $this->assertSame($registryBefore, RegisteredProducer::query()->orderBy('registry_id')->get()->toArray());
    }

    public function test_registry_identity_mismatches_and_registration_api_bypass_create_no_account(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-999-000011',
            'first_name' => 'SyntheticGiven012',
            'middle_name' => 'SyntheticMiddle013',
            'last_name' => 'SyntheticFamily014',
            'extension_name' => 'Jr.',
            'sex' => 'Female',
            'barangay' => 'Toran',
        ]);

        $identity = [
            'rsbsa_number' => '02-15-05-999-000011',
            'first_name' => 'SyntheticGiven012',
            'middle_name' => 'SyntheticMiddle013',
            'last_name' => 'SyntheticFamily014',
            'extension_name' => 'Jr',
            'sex' => 'Female',
            'barangay' => 'Toran',
        ];
        $this->postJson('/api/registry/verify-producer', $identity)
            ->assertOk()
            ->assertJsonPath('status', 'registry_verified');

        foreach ([
            ['last_name' => 'Cervantez'],
            ['middle_name' => ''],
            ['barangay' => 'Centro 8'],
            ['sex' => 'Male'],
            ['extension_name' => 'null'],
        ] as $changes) {
            $this->postJson('/api/registry/verify-producer', array_merge($identity, $changes))
                ->assertUnprocessable()
                ->assertJsonPath('status', 'registry_mismatch')
                ->assertJsonMissingPath('registry_match_details');
        }

        $this->postJson('/api/register', array_merge($identity, [
            'last_name' => 'Cervantez',
            'contact_number' => '09170000099',
            'email' => 'registry-bypass@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
        ], $this->policyPayload()))->assertUnprocessable()
            ->assertJsonPath('status', 'registry_mismatch');

        $this->assertDatabaseMissing('users', ['email' => 'registry-bypass@example.test']);
        $this->assertSame(0, Producer::count());
    }

    public function test_duplicate_email_and_duplicate_registry_are_checked_without_creating_partial_accounts(): void
    {
        (new \Database\Seeders\RegisteredProducerSeedDataSeeder())->run();
        $registeredUser = $this->user('already-linked@example.test', 'Farmer');
        $registry = RegisteredProducer::where('rsbsa_number', '02-15-05-999-000001')->firstOrFail();
        Producer::create([
            'user_id' => $registeredUser->user_id,
            'registered_producer_id' => $registry->registry_id,
            'rsbsa_number' => $registry->rsbsa_number,
            'verification_status' => 'Verified',
            'producer_type' => 'Farmer',
        ]);

        $payload = array_merge([
            'rsbsa_number' => '02-15-05-999-000001',
            'first_name' => 'SyntheticGiven002',
            'middle_name' => 'SyntheticMiddle003',
            'last_name' => 'SyntheticFamily004',
            'extension_name' => '',
            'sex' => 'Male',
            'barangay' => 'Centro 8',
            'contact_number' => '09170000050',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
        ], $this->policyPayload());

        $this->postJson('/api/register', array_merge($payload, [
            'email' => 'already-linked@example.test',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/register', array_merge($payload, [
            'email' => 'second-registry-account@example.test',
        ]))->assertStatus(409)
            ->assertJsonPath('status', 'duplicate_registry_link')
            ->assertJsonMissing(['email' => 'already-linked@example.test']);

        $this->assertDatabaseMissing('users', ['email' => 'second-registry-account@example.test']);
        $this->assertSame(1, Producer::where('registered_producer_id', $registry->registry_id)->count());
    }

    public function test_exact_registry_match_creates_active_producer_account_and_blocks_duplicate_registry_link(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-999-000006',
            'first_name' => 'SyntheticGiven007',
            'middle_name' => 'SyntheticMiddle008',
            'last_name' => 'SyntheticFamily009',
            'sex' => 'Male',
            'barangay' => 'Dodan',
            'municipality' => 'Aparri',
            'producer_type' => 'Farmer',
        ]);

        $this->postJson('/api/register', array_merge([
            'first_name' => 'SyntheticGiven007',
            'middle_name' => 'SyntheticMiddle008',
            'last_name' => 'SyntheticFamily009',
            'contact_number' => '09170000011',
            'email' => 'SyntheticGiven007-registry-pass@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => '02-15-05-999-000006',
            'sex' => 'Male',
            'barangay' => 'Dodan',
        ], $this->policyPayload()))->assertCreated()
            ->assertJsonPath('verification_status', 'Pending')
            ->assertJsonPath('registry_match', true);

        $user = User::where('email', 'SyntheticGiven007-registry-pass@example.test')->firstOrFail();
        $producer = Producer::where('user_id', $user->user_id)->firstOrFail();
        $this->assertSame('Pending', $producer->verification_status);
        $this->assertSame('02-15-05-999-000006', $producer->rsbsa_number);
        $this->assertNotNull($producer->registered_producer_id);

        $this->postJson('/api/register', array_merge([
            'first_name' => 'SyntheticGiven007',
            'middle_name' => 'SyntheticMiddle008',
            'last_name' => 'SyntheticFamily009',
            'contact_number' => '09170000012',
            'email' => 'SyntheticGiven007-registry-duplicate@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => '02-15-05-999-000006',
            'sex' => 'Male',
            'barangay' => 'Dodan',
        ], $this->policyPayload()))->assertStatus(409)
            ->assertJsonPath('message', 'An account is already associated with this registry record.');
    }

    public function test_final_registration_rejects_direct_api_bypass_without_verification(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-999-000011',
            'first_name' => 'SyntheticGiven012',
            'middle_name' => 'SyntheticMiddle013',
            'last_name' => 'SyntheticFamily014',
            'sex' => 'Female',
            'barangay' => 'Toran',
            'municipality' => 'Aparri',
            'producer_type' => 'Farmer',
        ]);

        $this->postJson('/api/register', array_merge([
            'first_name' => 'SyntheticGiven012',
            'middle_name' => 'SyntheticMiddle013',
            'last_name' => 'SyntheticFamily014',
            'contact_number' => '09170000013',
            'email' => 'SyntheticGiven012-bypass@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => '02-15-05-012-009999',
            'sex' => 'Female',
            'barangay' => 'Toran',
        ], $this->policyPayload()))->assertStatus(404)
            ->assertJsonPath('status', 'registry_not_found');
    }

    public function test_synthetic_registry_seeder_is_idempotent_and_preserves_existing_registry_fields(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-999-000001',
            'first_name' => 'SyntheticGiven002',
            'middle_name' => 'SyntheticMiddle003',
            'last_name' => 'SyntheticFamily004',
            'extension_name' => '',
            'sex' => 'Male',
            'house_number' => 'Preserved House',
            'zone' => 'Preserved Zone',
            'street' => 'Preserved Street',
            'barangay' => 'Centro 8',
            'municipality' => 'Aparri',
            'mobile_number' => '09000000001',
            'area_ha' => '9.99',
        ]);

        $seeder = new \Database\Seeders\RegisteredProducerSeedDataSeeder();
        $seeder->run();

        $record = RegisteredProducer::where('rsbsa_number', '02-15-05-999-000001')->firstOrFail();
        $this->assertSame('Preserved House', $record->house_number);
        $this->assertSame('Preserved Zone', $record->zone);
        $this->assertSame('Preserved Street', $record->street);
        $this->assertSame('09000000001', $record->mobile_number);
        $this->assertSame('9.99', (string) $record->area_ha);

        $firstRunCount = RegisteredProducer::count();
        $seeder->run();

        $this->assertSame($firstRunCount, RegisteredProducer::count());
    }

    /**
     * New public Producer registration is agriculture-only (owner decision).
     *
     * An explicitly self-declared out-of-scope producer type must be rejected
     * outright, not silently relabelled, and no account may be created. A
     * registry match never by itself grants approved Producer access: the new
     * account still starts Pending and needs a separate Admin approval.
     *
     * Legacy Fisherfolk accounts remain supported; this only covers registration.
     */
    public function test_new_registration_rejects_self_declared_fisherfolk_and_stays_pending(): void
    {
        RegisteredProducer::create([
            'rsbsa_number' => '02-15-05-030-000777',
            'first_name' => 'Dalisay',
            'middle_name' => 'Roxas',
            'last_name' => 'Magsaysay',
            'sex' => 'Female',
            'barangay' => 'Piat',
            'municipality' => 'Aparri',
            'producer_type' => 'Fisherfolk',
        ]);

        $this->postJson('/api/register', array_merge([
            'first_name' => 'Dalisay',
            'middle_name' => 'Roxas',
            'last_name' => 'Magsaysay',
            'contact_number' => '09170000077',
            'email' => 'out-of-scope-registration@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Fisherfolk',
            'rsbsa_number' => '02-15-05-030-000777',
            'sex' => 'Female',
            'barangay' => 'Piat',
        ], $this->policyPayload()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('producer_type');

        $this->assertDatabaseMissing('users', ['email' => 'out-of-scope-registration@example.test']);
        $this->assertDatabaseMissing('producers', ['rsbsa_number' => '02-15-05-030-000777']);

        // An in-scope Farmer registration against the same kind of matched record
        // is accepted but still lands Pending: registry match is not approval.
        $this->postJson('/api/register', array_merge([
            'first_name' => 'Dalisay',
            'middle_name' => 'Roxas',
            'last_name' => 'Magsaysay',
            'contact_number' => '09170000078',
            'email' => 'in-scope-registration@example.test',
            'password' => 'secret123',
            'user_type' => 'producer',
            'producer_type' => 'Farmer',
            'rsbsa_number' => '02-15-05-030-000777',
            'sex' => 'Female',
            'barangay' => 'Piat',
        ], $this->policyPayload()))
            ->assertCreated()
            ->assertJsonPath('verification_status', 'Pending')
            ->assertJsonPath('registry_match', true);

        $user = User::where('email', 'in-scope-registration@example.test')->firstOrFail();
        $producer = Producer::where('user_id', $user->user_id)->firstOrFail();
        $this->assertSame('Pending', $producer->verification_status);
        $this->assertSame('Farmer', $producer->producer_type);
    }
}
