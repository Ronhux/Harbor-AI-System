<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Buyer;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\Reports\ReportTemplateRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

/**
 * Focused coverage for the downloadable administrative report formats against
 * the supplied HarborAI report template at
 * docs/report-templates/HarborAI_Report.docx.
 *
 * These tests inspect the produced output itself - PDF page count, page
 * geometry and the text actually drawn on each page; spreadsheet cells; CSV
 * rows - rather than only the JSON preview, so template fidelity and
 * multi-page pagination are verified in what the operator downloads.
 */
class ReportTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create([
            'email' => 'template-admin@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Report',
            'last_name' => 'Administrator',
            'user_type' => 'Admin',
            'is_active' => true,
        ]);
        DB::table('users')->where('user_id', $user->user_id)->update([
            User::accountCreatedAtColumn() => Carbon::now()->toDateTimeString(),
        ]);
        Admin::create(['user_id' => $user->user_id, 'role' => 'Admin']);

        return $user->fresh();
    }

    /**
     * Enough producers that the generated document must span several pages.
     */
    private function seedProducers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $user = User::create([
                'email' => sprintf('grower%03d@example.test', $i),
                'password_hash' => bcrypt('secret123'),
                'first_name' => sprintf('Grower%03d', $i),
                'last_name' => 'Testerman',
                'contact_number' => sprintf('0918%07d', $i),
                'user_type' => 'Farmer',
                'is_active' => true,
            ]);
            DB::table('users')->where('user_id', $user->user_id)->update([
                User::accountCreatedAtColumn() => Carbon::parse('2026-09-01 08:00:00')->toDateTimeString(),
            ]);

            $producer = Producer::create([
                'user_id' => $user->user_id,
                'verification_status' => 'Verified',
                'producer_type' => 'Farmer',
                'location' => 'Aparri, Cagayan',
            ]);

            ProductListing::create([
                'producer_id' => $producer->producer_id,
                'product_name' => 'Rice',
                'category' => 'Grains',
                'price' => 55 + $i,
                'quantity' => 100 + $i,
                'status' => 'Active',
                'unit' => 'kg',
                'created_at' => Carbon::parse('2026-09-02 09:00:00'),
            ]);
        }
    }

    private function generateReport(User $admin, array $overrides = []): int
    {
        return $this->actingAs($admin)->postJson('/api/admin/reports', array_merge([
            'type' => 'producer_activity',
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-20',
            'filters' => [],
        ], $overrides))->assertCreated()->json('id');
    }

    /**
     * The full saved snapshot, not a preview page: the report API paginates
     * `rows`, and the downloads are built from the whole snapshot.
     */
    private function snapshot(User $admin, int $id): array
    {
        return $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$id}?per_page=100")
            ->json();
    }

    /**
     * Render the same HTML the download endpoint uses and report how many
     * physical pages Dompdf produced, plus the normalized text drawn on each
     * page.
     *
     * @return array{pages: int, texts: list<string>}
     */
    private function renderPages(array $snapshot): array
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml(app(ReportTemplateRenderer::class)->render($snapshot), 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        $pages = $dompdf->getCanvas()->get_page_count();
        $output = $dompdf->output(['compress' => false]);

        return [
            'pages' => (int) $pages,
            'texts' => $this->pdfPageTexts($output),
        ];
    }

    /**
     * Extract the text drawn on each PDF page. Streams are emitted
     * uncompressed so the PDF text-showing operators can be read directly.
     *
     * @return list<string>
     */
    private function pdfPageTexts(string $pdf): array
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $streams);

        $pages = [];

        foreach ($streams[1] as $stream) {
            // Only page content streams draw text; embedded font programs
            // also contain byte sequences that look like PDF operators.
            if (preg_match('/\bBT\b.*\bTd\b.*\bTJ\b|\bBT\b.*\bTd\b.*\bTj\b/s', $stream) !== 1) {
                continue;
            }

            // Both (literal) Tj and [(literal) number (literal)] TJ forms.
            preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $stream, $matches);

            $text = implode('', array_map(
                static fn (string $chunk): string => preg_replace('/\\\\([()\\\\])/', '$1', $chunk) ?? $chunk,
                $matches[1] ?? []
            ));

            // Dompdf writes two-byte glyphs; drop the padding byte.
            $text = str_replace("\0", '', $text);
            $text = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');

            if ($text !== '') {
                $pages[] = $text;
            }
        }

        return $pages;
    }

    /**
     * PDF text extraction concatenates adjacent cells and runs without a
     * separating space, so multi-word template labels are compared with all
     * whitespace removed.
     */
    private function squash(?string $text): string
    {
        // No /u modifier: extracted PDF text can contain byte sequences that
        // are not valid UTF-8, and a failing preg_replace would return null.
        $collapsed = preg_replace('/\s+/', '', (string) $text);

        return $collapsed ?? (string) $text;
    }

    /**
     * @param  list<string>  $labels
     */
    private function assertTemplateHeading(array $labels, string $haystack, string $what): void
    {
        $squashed = $this->squash($haystack);

        foreach ($labels as $label) {
            $this->assertStringContainsString(
                $this->squash($label),
                $squashed,
                "The {$what} label '{$label}' is missing from the generated document."
            );
        }
    }

    // -----------------------------------------------------------------
    // The supplied template itself
    // -----------------------------------------------------------------

    public function test_the_supplied_template_defines_the_sections_the_renderer_emits(): void
    {
        $path = base_path('docs/report-templates/HarborAI_Report.docx');
        $this->assertFileExists($path, 'The supplied report template must exist in the repository.');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The supplied report template must be a readable DOCX archive.');

        $document = (string) $zip->getFromName('word/document.xml');
        $header = (string) $zip->getFromName('word/header1.xml');
        $footer = (string) $zip->getFromName('word/footer1.xml');
        $zip->close();

        $this->assertNotSame('', $document);
        $this->assertNotSame('', $header);
        $this->assertNotSame('', $footer);

        // Every section heading the renderer emits must exist in the template,
        // so the download is not an invented layout.
        foreach (ReportTemplateRenderer::SECTION_HEADINGS as $heading) {
            $this->assertStringContainsString(
                $heading,
                $document,
                "Renderer section '{$heading}' does not exist in the supplied template."
            );
        }

        foreach (ReportTemplateRenderer::METADATA_LABELS as $label) {
            $this->assertStringContainsString($label, $document, "Template metadata label '{$label}' is missing.");
        }

        $withoutBrackets = str_replace(['[', ']'], '', $document);
        foreach (ReportTemplateRenderer::HIGHLIGHT_HEADINGS as $label) {
            $this->assertStringContainsString(
                $label,
                $withoutBrackets,
                "Template highlight column '{$label}' is missing."
            );
        }

        foreach (ReportTemplateRenderer::APPROVAL_HEADINGS as $label) {
            $this->assertStringContainsString($label, $document, "Template signature block '{$label}' is missing.");
        }

        $this->assertStringContainsString('ADMINISTRATIVE REPORT', $header);
        $this->assertStringContainsString('ADMINISTRATIVE REPORT', $footer);
        $this->assertStringContainsString('DOCUMENT CONTROL NOTE', $document);
    }

    // -----------------------------------------------------------------
    // PDF download
    // -----------------------------------------------------------------

    public function test_the_downloaded_pdf_is_a_real_a4_document_paginated_across_multiple_pages(): void
    {
        $this->seedProducers(60);

        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $response = $this->actingAs($admin)->get("/api/admin/reports/{$id}/download/pdf");
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf, 'The download must be a real PDF document.');
        $this->assertStringContainsString('%%EOF', $pdf);

        $pageCount = preg_match_all('#/Type\s*/Page[^s]#', $pdf);
        $this->assertGreaterThan(
            1,
            $pageCount,
            'A 60-row producer activity report must paginate across more than one page.'
        );

        $this->assertMatchesRegularExpression(
            '/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/',
            $pdf,
            'Every page must use the A4 page box from the template.'
        );
    }

    public function test_the_pdf_carries_every_template_section_and_real_snapshot_values_on_its_pages(): void
    {
        $this->seedProducers(60);

        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $rendered = $this->renderPages($this->snapshot($admin, $id));

        $this->assertGreaterThan(
            1,
            $rendered['pages'],
            'The rendered document must contain more than one physical page.'
        );
        $this->assertCount(
            $rendered['pages'],
            $rendered['texts'],
            'Every rendered page must carry extractable text.'
        );

        $allText = implode("\n", $rendered['texts']);

        $this->assertTemplateHeading(ReportTemplateRenderer::SECTION_HEADINGS, $allText, 'section');
        $this->assertTemplateHeading(ReportTemplateRenderer::METADATA_LABELS, $allText, 'metadata');
        $this->assertTemplateHeading(ReportTemplateRenderer::APPROVAL_HEADINGS, $allText, 'signature');
        $this->assertTemplateHeading(ReportTemplateRenderer::HIGHLIGHT_HEADINGS, $allText, 'key highlights');

        // Masthead, running header and footer content from the template.
        $this->assertStringContainsString('ADMINISTRATIVE REPORT', $allText);
        $this->assertStringContainsString('Province of Cagayan', $allText);
        $this->assertStringContainsString('Municipality of Aparri', $allText);
        $this->assertStringContainsString('SMART TRADING AND ENTERPRISE PLATFORM FOR AGRICULTURE IN APARRI', $allText);
        $this->assertStringContainsString('DOCUMENT CONTROL NOTE', $allText);

        // Real snapshot content rather than template placeholders.
        $this->assertStringContainsString('Producer Activity Report', $allText);
        $this->assertStringContainsString('Aparri', $allText);
        $this->assertStringContainsString('Cagayan', $allText);
        $this->assertStringContainsString('Report Administrator', $allText);
        $this->assertMatchesRegularExpression('/Grower0\d\d/', $allText);

        // No un-replaced template placeholders are emitted.
        foreach (['[REPORT TITLE]', '[REPORT-ID-0001]', '[Month DD, YYYY]', '[Name / ID]', '[Continue as needed]'] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $allText, "Template placeholder {$placeholder} was not replaced.");
        }
    }

    public function test_multi_page_output_keeps_the_column_header_on_every_record_page_and_loses_no_rows(): void
    {
        $this->seedProducers(45);

        $admin = $this->admin();
        $id = $this->generateReport($admin);
        $snapshot = $this->snapshot($admin, $id);

        $this->assertSame(45, $snapshot['row_count']);

        $rendered = $this->renderPages($snapshot);
        $this->assertGreaterThan(2, $rendered['pages'], 'A 45-row report must span at least three pages.');

        // Every snapshot row must be drawn somewhere in the document.
        $allText = implode(' ', $rendered['texts']);
        foreach (['Grower001', 'Grower015', 'Grower030', 'Grower045'] as $name) {
            $this->assertStringContainsString(
                $name,
                $allText,
                "Row {$name} was lost across a page break."
            );
        }

        // The repeated table header must appear on more than one page, which
        // is only true when `thead { display: table-header-group; }` is
        // honoured.
        $pagesWithHeader = 0;
        foreach ($rendered['texts'] as $pageText) {
            $squashed = $this->squash($pageText);
            if (str_contains($squashed, $this->squash('Producer ID'))
                && str_contains($squashed, $this->squash('Verification Status'))
                && str_contains($squashed, $this->squash('Activity'))) {
                $pagesWithHeader++;
            }
        }

        $this->assertGreaterThanOrEqual(
            2,
            $pagesWithHeader,
            'Column headers must repeat on each page that carries record rows.'
        );
    }

    public function test_an_empty_report_still_downloads_the_complete_template_with_honest_empty_states(): void
    {
        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $rendered = $this->renderPages($this->snapshot($admin, $id));

        $this->assertGreaterThanOrEqual(1, $rendered['pages']);

        $allText = implode("\n", $rendered['texts']);
        $this->assertTemplateHeading(ReportTemplateRenderer::SECTION_HEADINGS, $allText, 'section');

        // Zero counts are reported honestly rather than being hidden.
        $this->assertStringContainsString('No records matched', $allText);
        $this->assertMatchesRegularExpression('/\b0\b/', $allText);
        $this->assertStringNotContainsString('[REPORT TITLE]', $allText);
    }

    // -----------------------------------------------------------------
    // Spreadsheet and CSV downloads
    // -----------------------------------------------------------------

    public function test_the_downloaded_xlsx_is_a_real_workbook_carrying_every_template_section_and_row(): void
    {
        $this->seedProducers(30);

        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $response = $this->actingAs($admin)->get("/api/admin/reports/{$id}/download/xlsx");
        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        // The download endpoint returns a file response; read it from disk.
        $path = (string) $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The XLSX download must be a real archive.');

        $sharedStrings = (string) $zip->getFromName('xl/sharedStrings.xml');
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        @unlink($path);

        $this->assertNotSame('', $sheet, 'The workbook must contain a real worksheet.');
        $this->assertStringContainsString('<sheetData>', $sheet);
        $this->assertStringContainsString('Producer Activity Report', $workbook);

        // OpenSpout writes cell text inline, with any shared strings
        // additionally collected in the shared string table.
        $text = html_entity_decode(
            strip_tags(str_replace(['</t>', '</v>'], [' ', ' '], $sharedStrings . $sheet)),
            ENT_QUOTES,
            'UTF-8'
        );

        $this->assertTemplateHeading(ReportTemplateRenderer::SECTION_HEADINGS, $text, 'section');
        $this->assertTemplateHeading(ReportTemplateRenderer::METADATA_LABELS, $text, 'metadata');
        $this->assertTemplateHeading(ReportTemplateRenderer::APPROVAL_HEADINGS, $text, 'signature');

        $this->assertStringContainsString('Grower001', $text);
        $this->assertStringContainsString('Grower030', $text);
    }

    public function test_the_downloaded_csv_carries_every_template_section_and_row(): void
    {
        $this->seedProducers(30);

        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $response = $this->actingAs($admin)->get("/api/admin/reports/{$id}/download/csv");
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));

        $csv = (string) $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'The CSV export must keep its UTF-8 BOM for Excel.');

        $this->assertTemplateHeading(ReportTemplateRenderer::SECTION_HEADINGS, $csv, 'section');
        $this->assertTemplateHeading(ReportTemplateRenderer::APPROVAL_HEADINGS, $csv, 'signature');

        $this->assertStringContainsString('Grower001', $csv);
        $this->assertStringContainsString('Grower030', $csv);

        $lines = array_values(array_filter(explode("\n", $csv), static fn (string $line): bool => trim($line) !== ''));
        $this->assertGreaterThan(30, count($lines), 'The CSV must contain a preamble plus every record row.');
    }

    // -----------------------------------------------------------------
    // Renderer fidelity
    // -----------------------------------------------------------------

    public function test_the_html_renderer_uses_the_template_masthead_metadata_and_signature_blocks(): void
    {
        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $html = app(ReportTemplateRenderer::class)->render($this->snapshot($admin, $id));

        $this->assertStringContainsString('@page', $html);
        $this->assertStringContainsString('Republic of the Philippines', $html);
        $this->assertStringContainsString('Province of Cagayan', $html);
        $this->assertStringContainsString('Municipality of Aparri', $html);
        $this->assertStringContainsString('SMART TRADING AND ENTERPRISE PLATFORM FOR AGRICULTURE IN APARRI', $html);
        $this->assertStringContainsString('ADMINISTRATIVE REPORT', $html);
        $this->assertStringContainsString('Report Reference', $html);
        $this->assertStringContainsString('PREPARED BY', $html);
        $this->assertStringContainsString('REVIEWED BY', $html);
        $this->assertStringContainsString('APPROVED BY', $html);
        $this->assertStringContainsString('Report Administrator', $html);
        $this->assertStringContainsString('DOCUMENT CONTROL NOTE', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,', $html);
    }

    public function test_record_values_are_escaped_instead_of_injected_as_markup(): void
    {
        $admin = $this->admin();

        $buyerUser = User::create([
            'email' => 'buyer-esc@example.test',
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Esc',
            'last_name' => 'Aper',
            'user_type' => 'Buyer',
        ]);
        $buyer = Buyer::create([
            'user_id' => $buyerUser->user_id,
            'organization_name' => '<script>alert(1)</script> "Quoted" & Ampersand',
            'buyer_type' => 'Institution',
            'verification_status' => 'Verified',
        ]);

        \App\Models\DemandRequest::create([
            'buyer_id' => $buyer->buyer_id,
            'product_name' => 'Rice',
            'category' => 'Grains',
            'quantity_needed' => 10,
            'unit' => 'kg',
            'max_price_per_unit' => 60,
            'location' => 'Aparri',
            'status' => 'Active',
            'created_at' => Carbon::parse('2026-09-05 09:00:00'),
        ]);

        $id = $this->generateReport($admin, ['type' => 'demand_summary']);

        $rendered = app(ReportTemplateRenderer::class)->render($this->snapshot($admin, $id));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
    }

    public function test_the_pdf_renders_on_the_a4_paper_size_the_template_declares(): void
    {
        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $dompdf = new Dompdf();
        $dompdf->loadHtml(app(ReportTemplateRenderer::class)->render($this->snapshot($admin, $id)), 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        // getPaperSize() returns [x, y, width, height] in points.
        $size = array_values($dompdf->getPaperSize());
        $this->assertSame([595.28, 841.89], [
            round((float) $size[2], 2),
            round((float) $size[3], 2),
        ]);
        $this->assertSame('portrait', $dompdf->getPaperOrientation());
    }

    public function test_the_pdf_facade_used_by_the_download_endpoint_renders_without_warnings(): void
    {
        $admin = $this->admin();
        $id = $this->generateReport($admin);

        $pdf = Pdf::loadHTML(app(ReportTemplateRenderer::class)->render($this->snapshot($admin, $id)), 'UTF-8')
            ->setPaper('a4', 'portrait')
            ->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }
}