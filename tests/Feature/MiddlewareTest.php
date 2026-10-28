<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mao_workflows.fca_applications_enabled' => true]);
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
    }

    public function test_config_is_set_in_middleware(): void
    {
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));

        $fca = new \App\Models\FcaRegistry();
        $fca->forceFill([
            'official_name' => 'Test Association',
            'address' => 'Test Address',
            'president_name' => 'Test President',
            'directory_verification_status' => 'verified',
        ])->save();

        $user = \App\Models\User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
            'contact_number' => '09123456789',
            'user_type' => 'Farmer',
            'is_active' => true,
        ]);

        $producer = \App\Models\Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => 'Verified',
        ]);

        $fca = new \App\Models\FcaRegistry();
        $fca->forceFill([
            'official_name' => 'Test FCA',
            'address' => 'Test Address',
            'president_name' => 'Test President',
            'directory_verification_status' => 'verified',
        ])->save();

        $response = $this->actingAs($user)->postJson('/api/fca/directory/' . $fca->id . '/applications', [
            'evidence_receipt' => 'test-receipt',
            'authorization_statement' => 'Test authorization',
        ]);

        $this->assertNotEquals(403, $response->status(), 'Should not be 403: ' . $response->getContent());
    }
}