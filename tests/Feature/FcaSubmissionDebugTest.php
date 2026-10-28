<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FcaSubmissionDebugTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mao_workflows.fca_applications_enabled' => true,
            'mao_workflows.fca_inspection_persistence_enabled' => true,
            'mao_workflows.fca_evidence_access_enabled' => true,
            'fca_president.workflow_enabled' => true,
        ]);
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));
    }

    public function test_fca_submission(): void
    {
        $fca = new \App\Models\FcaRegistry();
        $fca->forceFill([
            'official_name' => 'Test FCA',
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

        $response->assertUnprocessable();
        $this->assertDatabaseCount('fca_representatives', 0);
        $this->assertSame('Verified', $producer->fresh()->verification_status);
    }
}