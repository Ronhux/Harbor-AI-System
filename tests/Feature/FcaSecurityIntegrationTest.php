<?php

namespace Tests\Feature;

use App\Models\FcaEvidence;
use App\Models\User;
use App\Policies\FcaWorkflowPolicy;
use App\Services\Mao\FcaEvidenceGovernance;
use App\Services\Mao\FcaEvidenceStorage;
use App\Services\Mao\Inspection\DocumentInspector;
use App\Services\Mao\Inspection\InspectionLifecycle;
use App\Services\Mao\Inspection\InspectionResult;
use App\Services\Mao\Inspection\MalwareProvider;
use App\Services\Mao\Inspection\PdfInspector;
use DomainException;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\VerifiesTestDatabase;

class FcaSecurityIntegrationTest extends TestCase
{
    use VerifiesTestDatabase;

    protected function setUp(): void
    {
        $this->verifyTestDatabaseTargetPreBoot();
        parent::setUp();
        $this->verifyTestDatabaseTargetPostBoot();
    }

    private function pdf(): string { return "%PDF-1.4\n<< /Type /Catalog >>\n%%EOF"; }
    private function malware(string $state = 'clean'): MalwareProvider
    {
        return new class($state) implements MalwareProvider {
            public function __construct(private string $state) {}
            public function inspect(string $bytes, string $mime): string { return $this->state; }
        };
    }
    private function parser(string $state = 'clean'): PdfInspector
    {
        return new class($state) implements PdfInspector {
            public function __construct(private string $state) {}
            public function inspect(string $bytes): string { return $this->state; }
        };
    }

    public function test_missing_providers_quarantine_and_cannot_be_used(): void
    {
        $result = (new DocumentInspector())->inspect($this->pdf(), 'application/pdf');
        $this->assertSame('quarantined', $result->status);
        $this->assertFalse($result->usable());
        $this->assertArrayNotHasKey('sha256', $result->safeSummary());
    }

    public function test_provider_failure_and_invalid_contract_never_mean_clean(): void
    {
        $provider = new class implements MalwareProvider {
            public function inspect(string $bytes, string $mime): string { throw new \RuntimeException('SECRET provider output'); }
        };
        $result = (new DocumentInspector($provider, $this->parser()))->inspect($this->pdf(), 'application/pdf');
        $this->assertSame('error', $result->status);
        $this->assertStringNotContainsString('SECRET', json_encode($result->safeSummary()));
        $result = (new DocumentInspector($this->malware('unexpected'), $this->parser()))->inspect($this->pdf(), 'application/pdf');
        $this->assertSame('error', $result->status);
    }

    public function test_all_required_checks_must_pass_and_integrity_cannot_be_bypassed(): void
    {
        $inspector = new DocumentInspector($this->malware(), $this->parser());
        $result = $inspector->inspect($this->pdf(), 'application/pdf', hash('sha256', $this->pdf()));
        $this->assertTrue($result->usable());
        $this->assertSame('rejected', $inspector->inspect($this->pdf(), 'application/pdf', str_repeat('0', 64))->status);
        $this->assertFalse((new InspectionResult('clean', ['malware' => 'clean'], 'fake'))->usable());
        $this->assertFalse((new DocumentInspector($this->malware()))->inspect($this->pdf(), 'application/pdf')->usable());
    }

    public function test_invalid_pdf_active_content_and_mime_mismatch_are_rejected(): void
    {
        $inspector = new DocumentInspector($this->malware(), $this->parser());
        foreach (["%PDF-1.4\ntruncated", "%PDF-1.4\n/JavaScript\n%%EOF", 'not a PDF'] as $bytes) {
            $this->assertSame('rejected', $inspector->inspect($bytes, 'application/pdf')->status);
        }
        $this->assertSame('rejected', $inspector->inspect($this->pdf(), 'image/png')->status);
        $this->assertSame('rejected', (new DocumentInspector($this->malware(), $this->parser('rejected')))->inspect($this->pdf(), 'application/pdf')->status);
    }

    public function test_images_do_not_skip_actual_decoding(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY3sAAAAASUVORK5CYII=');
        $result = (new DocumentInspector($this->malware()))->inspect($png, 'image/png');
        if (!function_exists('imagecreatefromstring')) {
            $this->assertSame('quarantined', $result->checks['image_decode']);
            $this->assertFalse($result->usable());
        } else {
            $this->assertArrayHasKey('image_decode', $result->checks);
        }
    }

    public function test_completion_and_retries_require_valid_states_and_authority(): void
    {
        $lifecycle = new InspectionLifecycle();
        $result = (new DocumentInspector($this->malware(), $this->parser()))->inspect($this->pdf(), 'application/pdf');
        $this->assertSame('clean', $lifecycle->complete('pending', $result));
        $this->assertSame('pending', $lifecycle->retry('quarantined', true));
        $this->expectException(DomainException::class);
        $lifecycle->retry('error', false);
    }

    public function test_rejected_or_clean_documents_cannot_be_automatically_retried(): void
    {
        $this->expectException(DomainException::class);
        (new InspectionLifecycle())->retry('rejected', true);
    }

    public function test_retention_is_unapproved_by_default_and_never_authorizes_deletion(): void
    {
        $service = new FcaEvidenceGovernance(new FcaWorkflowPolicy(), new FcaEvidenceStorage(), new DocumentInspector());
        $plan = $service->retentionPlan('rejected', Carbon::now()->subYears(5));
        $this->assertFalse($plan['policy_approved']);
        $this->assertSame('retain', $plan['action']);
        config(['fca_evidence.retention_days.rejected' => 30]);
        $plan = $service->retentionPlan('rejected', Carbon::now()->subDays(31));
        $this->assertTrue($plan['review_due']);
        $this->assertFalse($plan['deletion_authorized']);
    }

    public function test_disabled_and_unauthorized_reconciliation_stops_before_database_access(): void
    {
        $actor = (new User())->forceFill(['user_id' => 1, 'user_type' => 'Admin', 'is_active' => true]);
        $service = new FcaEvidenceGovernance(new FcaWorkflowPolicy(), new FcaEvidenceStorage(), new DocumentInspector());
        try { $service->dryRun($actor); $this->fail('Disabled operation allowed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        config(['mao_workflows.fca_applications_enabled' => true]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->dryRun($actor);
    }

    public function test_reinspection_plans_report_corruption_without_releasing_or_modifying_evidence(): void
    {
        $actor = (new User())->forceFill(['user_id' => 1, 'user_type' => 'Admin', 'is_active' => true]);
        config(['mao_workflows.fca_applications_enabled' => true, 'mao_workflows.fca_reviewer_user_ids' => [1], 'fca_evidence.max_inspection_attempts' => 2]);
        $bytes = $this->pdf();
        $files = new class($bytes) extends FcaEvidenceStorage {
            public function __construct(private string $bytes) {}
            public function read(string $key): string { return $this->bytes; }
        };
        $file = (new FcaEvidence())->forceFill(['id' => 'synthetic', 'mime' => 'application/pdf', 'bytes' => strlen($bytes), 'sha256' => str_repeat('0', 64), 'storage_key' => 'synthetic', 'status' => 'quarantined']);
        $before = $file->getAttributes();
        $service = new FcaEvidenceGovernance(new FcaWorkflowPolicy(), $files, new DocumentInspector($this->malware(), $this->parser()));
        $report = $service->reinspectionPlan($actor, $file, 0);
        $this->assertSame('rejected', $report['inspection']['status']);
        $this->assertFalse($report['release_authorized']);
        $this->assertSame($before, $file->getAttributes());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->reinspectionPlan($actor, $file, 2);
    }

    public function test_previews_are_disabled_and_contain_no_network_or_live_file_controls(): void
    {
        $source = file_get_contents(base_path('src/app/components/mao/FcaWorkflowPreviews.tsx'));
        $this->assertStringContainsString('FCA_APPLICANT_PREVIEW_ENABLED = false', $source);
        $this->assertStringContainsString('FCA_ADMIN_PREVIEW_ENABLED = false', $source);
        $this->assertStringNotContainsString('fetch(', $source);
        $this->assertStringNotContainsString('request(', $source);
        $this->assertStringContainsString('type="file" disabled', $source);
    }
}
