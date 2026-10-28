<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerProfileTest extends TestCase
{
    use RefreshDatabase;

    private int $syntheticSequence = 0;

    /**
     * Synthetic buyer factory.
     *
     * `User::booted()` mirrors `contact_number` into `login_number`, and the
     * `users_login_number_unique` index rejects a second account sharing the
     * same number. The default below is unique per call so a test creating more
     * than one buyer no longer collides; tests that assert on a specific number
     * still override `contact_number` through $buyerData.
     */
    private function createBuyer(string $email, array $buyerData = [], ?string $contactNumber = null): User
    {
        $user = User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Ari',
            'last_name' => 'Santos',
            'contact_number' => $contactNumber
                ?? '0917999' . str_pad((string) ++$this->syntheticSequence, 4, '0', STR_PAD_LEFT),
            'user_type' => 'Buyer',
        ]);

        Buyer::create(array_merge([
            'user_id' => $user->user_id,
            'organization_name' => 'Aparri District Hospital',
            'buyer_type' => 'Institution',
            'verification_status' => 'Verified',
            'contact_person' => 'Ari Santos',
            'shipping_address' => 'Aparri, Cagayan',
        ], $buyerData));

        return $user;
    }

    public function test_profile_returns_the_authenticated_buyers_persisted_data(): void
    {
        $buyer = $this->createBuyer('profile-owner@test.local', [], '09171234567');
        $this->createBuyer('profile-other@test.local', ['organization_name' => 'Other Hospital']);

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/buyer/profile')
            ->assertOk()
            ->assertJsonPath('data.organization_name', 'Aparri District Hospital')
            ->assertJsonPath('data.contact_person', 'Ari Santos')
            ->assertJsonPath('data.email', 'profile-owner@test.local')
            ->assertJsonPath('data.contact_number', '09171234567')
            ->assertJsonMissing(['organization_name' => 'Other Hospital']);
    }

    public function test_buyer_profile_changes_are_saved_and_returned_after_a_fresh_read(): void
    {
        $buyer = $this->createBuyer('profile-update@test.local');
        $payload = [
            'organization_name' => 'Aparri Municipal Hospital',
            'buyer_type' => 'Public Hospital',
            'contact_person' => 'Maria Santos',
            'shipping_address' => 'Centro, Aparri, Cagayan',
            'email' => 'procurement@test.local',
            'contact_number' => '09180001111',
        ];

        $this->actingAs($buyer, 'sanctum')
            ->putJson('/api/buyer/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.organization_name', $payload['organization_name']);

        $this->getJson('/api/buyer/profile')
            ->assertOk()
            ->assertJsonPath('data.organization_name', $payload['organization_name'])
            ->assertJsonPath('data.buyer_type', $payload['buyer_type'])
            ->assertJsonPath('data.contact_person', $payload['contact_person'])
            ->assertJsonPath('data.shipping_address', $payload['shipping_address'])
            ->assertJsonPath('data.email', $payload['email'])
            ->assertJsonPath('data.contact_number', $payload['contact_number']);
    }

    public function test_profile_rejects_an_email_already_owned_by_another_user(): void
    {
        $buyer = $this->createBuyer('profile-duplicate@test.local');
        $this->createBuyer('existing-owner@test.local');

        $this->actingAs($buyer, 'sanctum')
            ->putJson('/api/buyer/profile', [
                'organization_name' => 'Aparri District Hospital',
                'buyer_type' => 'Institution',
                'contact_person' => 'Ari Santos',
                'shipping_address' => 'Aparri, Cagayan',
                'email' => 'existing-owner@test.local',
                'contact_number' => '09171234567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('profile-duplicate@test.local', $buyer->fresh()->email);
    }
}
