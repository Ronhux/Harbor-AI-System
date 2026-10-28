<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Producer;
use App\Models\FcaRegistry;

class DebugFcaEndpointTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private User $owner;
    private User $admin;
    private Producer $producer;
    private FcaRegistry $fca;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:'
            || is_file(dirname(__DIR__, 2) . '/bootstrap/cache/config.php')) {
            throw new \LogicException('FCA tests require uncached SQLite memory configuration.');
        }
        parent::setUp();

        config(['mao_workflows.fca_applications_enabled' => true,
            'mao_workflows.fca_inspection_persistence_enabled' => true,
            'mao_workflows.fca_evidence_access_enabled' => true,
            'fca_evidence.inspection_adapter_id' => 'synthetic.document',
            'fca_evidence.max_inspection_attempts' => 5,
            'fca_evidence.receipt_ttl_hours' => 24,
            'mao_workflows.fca_reviewer_user_ids' => [1]]);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        app('router')->prefix('api')->group(base_path('routes/prepared/mao_fca.php'));

        $this->owner = User::create(['first_name' => 'Synthetic', 'last_name' => 'Fixture',
            'email' => 'test1@example.test', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09181234567', 'user_type' => 'Farmer', 'is_active' => true]);
        $this->admin = User::create(['first_name' => 'Synthetic', 'last_name' => 'Fixture',
            'email' => 'test2@example.test', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09181234568', 'user_type' => 'Admin', 'is_active' => true]);
        $this->producer = Producer::create(['user_id' => $this->owner->user_id, 'verification_status' => 'Verified']);
        $this->fca = new FcaRegistry();
        $this->fca->forceFill(['official_name' => 'Synthetic Test Association', 'address' => 'Synthetic location',
            'president_name' => 'Synthetic president', 'directory_verification_status' => 'verified'])->save();
    }

    public function test_fca_application_endpoint(): void
    {
        $input = [
            'representative_position' => 'Synthetic delegate',
            'authorization_statement' => 'Synthetic claim for review',
            'evidence_receipt' => 'test-receipt',
            'submission_key' => 'test-key',
        ];

        echo "Config: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;

        $response = $this->actingAs($this->owner)->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input);

        echo "Status: " . $response->status() . PHP_EOL;
        echo "Content: " . $response->getContent() . PHP_EOL;

        $this->assertNotEquals(503, $response->status(), 'Got 503: ' . $response->getContent());
    }
}