<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Producer;
use App\Models\FcaRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class DebugExactTest extends TestCase
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
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertFalse(config('mao_workflows.fca_applications_enabled'));

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Storage::fake('fca_evidence');
        config(['filesystems.disks.fca_evidence.root' => Storage::disk('fca_evidence')->path(''),
            'filesystems.disks.fca_evidence.driver' => 'local',
            'filesystems.disks.fca_evidence.serve' => false,
            'filesystems.disks.fca_evidence.visibility' => 'private']);

        $this->owner = $this->account('Farmer');
        $this->admin = $this->account('Admin');
        $this->producer = Producer::create(['user_id' => $this->owner->user_id, 'verification_status' => 'Verified']);
        $this->fca = new FcaRegistry();
        $this->fca->forceFill(['official_name' => 'Synthetic Test Association', 'address' => 'Synthetic location',
            'president_name' => 'Synthetic president', 'directory_verification_status' => 'verified'])->save();

        echo "Config before enable: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;

        config(['mao_workflows.fca_applications_enabled' => true,
            'mao_workflows.fca_inspection_persistence_enabled' => true,
            'mao_workflows.fca_evidence_access_enabled' => true,
            'fca_evidence.inspection_adapter_id' => 'synthetic.document',
            'fca_evidence.max_inspection_attempts' => 5,
            'fca_evidence.receipt_ttl_hours' => 24,
            'mao_workflows.fca_reviewer_user_ids' => [(int) $this->admin->user_id]]);

        echo "Config after enable: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;

        app('router')->prefix('api')->group(base_path('routes/prepared/mao_fca.php'));

        $routes = new \Illuminate\Routing\RouteCollection();
        $all = app('router')->getRoutes();
        foreach ($all as $route) if (str_contains($route->getActionName(), \App\Http\Controllers\FcaApplicationController::class)) $routes->add($route);
        foreach ($all as $route) if (!str_contains($route->getActionName(), \App\Http\Controllers\FcaApplicationController::class)) $routes->add($route);
        app('router')->setRoutes($routes);
    }

    private function account(string $role): User
    {
        return User::create(['first_name' => 'Synthetic', 'last_name' => 'Fixture',
            'email' => Str::uuid() . '@example.test', 'password_hash' => bcrypt('test-only'),
            'contact_number' => self::uniqueMobile(), 'user_type' => $role, 'is_active' => true]);
    }

    private static function uniqueMobile(): string
    {
        static $seq = 0;
        return sprintf('0918%07d', ++$seq);
    }

    private function receipt(): string
    {
        echo "In receipt() - Config: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;
        $receipt = $this->actingAs($this->owner)->postJson('/api/fca/evidence', [
            'evidence' => $this->document(),
        ]);
        echo "Receipt response status: " . $receipt->status() . PHP_EOL;
        echo "Receipt response: " . $receipt->getContent() . PHP_EOL;
        $receipt->assertCreated()->assertJsonPath('status', 'quarantined');
        return $receipt->json('receipt');
    }

    private function document(): UploadedFile
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach (['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>'] as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 4\n0000000000 65535 f \n";
        foreach ($offsets as $offset) $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        $pdf .= "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return UploadedFile::fake()->createWithContent('synthetic-proof.pdf', $pdf);
    }

    private function applicationInput(?string $receipt = null): array
    {
        return ['representative_position' => 'Synthetic delegate', 'authorization_statement' => 'Synthetic claim for review',
            'evidence_receipt' => $receipt ?? $this->receipt(), 'submission_key' => (string) Str::uuid()];
    }

    public function test_exact_failing_test(): void
    {
        echo "In test method - Config: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;
        $before = $this->producer->fresh()->getAttributes();
        $input = $this->applicationInput();
        echo "After applicationInput - Config: " . (config('mao_workflows.fca_applications_enabled') ? 'true' : 'false') . PHP_EOL;

        $response = $this->actingAs($this->owner)->postJson('/api/fca/directory/' . $this->fca->id . '/applications', $input);
        echo "Response status: " . $response->status() . PHP_EOL;
        echo "Response content: " . $response->getContent() . PHP_EOL;

        $this->assertNotEquals(503, $response->status(), 'Got 503: ' . $response->getContent());
    }
}