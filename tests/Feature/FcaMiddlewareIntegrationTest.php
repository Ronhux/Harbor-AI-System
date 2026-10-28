<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FcaMiddlewareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fca_route_with_enabled_gate(): void
    {
        config(['mao_workflows.fca_applications_enabled' => true]);
        $this->assertTrue(config('mao_workflows.fca_applications_enabled'));

        // Re-register FCA routes with current config
        app('router')->prefix('api')->group(base_path('routes/prepared/mao_fca.php'));

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
            'contact_number' => '09181234567',
            'user_type' => 'Farmer',
            'is_active' => true,
        ]);

        $producer = \App\Models\Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => 'Verified',
        ]);

        $input = [
            'evidence_receipt' => 'test-receipt',
            'authorization_statement' => 'Test authorization',
        ];

        $response = $this->actingAs($user)->postJson('/api/fca/directory/' . $fca->id . '/applications', $input);

        // Should not be 503
        $this->assertNotEquals(503, $response->status(), 'Got 503: ' . $response->getContent());
    }
}