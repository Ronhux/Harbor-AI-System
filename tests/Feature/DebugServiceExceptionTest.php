<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Producer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class DebugServiceExceptionTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private User $owner;
    private User $admin;
    private Producer $producer;

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
        Storage::fake('fca_evidence');
        config(['filesystems.disks.fca_evidence.root' => Storage::disk('fca_evidence')->path(''),
            'filesystems.disks.fca_evidence.driver' => 'local',
            'filesystems.disks.fca_evidence.serve' => false,
            'filesystems.disks.fca_evidence.visibility' => 'private']);

        $this->owner = $this->account('Farmer');
        $this->admin = $this->account('Admin');
        $this->producer = Producer::create(['user_id' => $this->owner->user_id, 'verification_status' => 'Verified']);
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

    public function test_service_upload_directly(): void
    {
        $service = app(\App\Services\Mao\FcaApplicationService::class);
        $file = $this->document();

        try {
            $result = $service->upload($this->owner, $file);
            $this->assertInstanceOf(\App\Models\FcaEvidence::class, $result);
            $this->assertSame('quarantined', $result->status);
            $this->assertDatabaseHas('fca_evidence', ['id' => $result->id, 'uploaded_by' => $this->owner->user_id]);
        } catch (\Throwable $e) {
            echo "Exception caught: " . get_class($e) . PHP_EOL;
            echo "Message: " . $e->getMessage() . PHP_EOL;
            echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
            echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
            throw $e;
        }
    }
}