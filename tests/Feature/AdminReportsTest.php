<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\DemandRequest;
use App\Models\OrderDetail;
use App\Models\Producer;
use App\Models\ProductListing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class AdminReportsTest extends TestCase
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

    private function user(string $email, string $type, array $values = []): User
    {
        $user = User::create(array_merge([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => ucfirst($type),
            'user_type' => $type,
        ], $values));
        DB::table('users')->where('user_id', $user->user_id)->update([
            User::accountCreatedAtColumn() => Carbon::now()->toDateTimeString(),
        ]);
        return $user->fresh();
    }

    private function admin(string $email = 'admin@reports.test'): User
    {
        return $this->user($email, 'Admin');
    }

    private function producer(string $email, array $userValues = [], string $location = 'Aparri', string $verification = 'Verified'): Producer
    {
        $user = $this->user($email, 'Farmer', $userValues);
        return Producer::create([
            'user_id' => $user->user_id,
            'verification_status' => $verification,
            'producer_type' => 'Farmer',
            'location' => $location,
        ]);
    }

    private function buyer(string $email, string $organization = 'Aparri LGU'): Buyer
    {
        $user = $this->user($email, 'Buyer');
        return Buyer::create([
            'user_id' => $user->user_id,
            'organization_name' => $organization,
            'buyer_type' => 'Institution',
            'verification_status' => 'Verified',
        ]);
    }

    private function listing(Producer $producer, array $values = []): ProductListing
    {
        return ProductListing::create(array_merge([
            'producer_id' => $producer->producer_id,
            'product_name' => 'Rice',
            'category' => 'Grains',
            'product_category' => 'Grains',
            'quantity_available' => 20,
            'quantity' => 20,
            'unit' => 'kg',
            'unit_of_measure' => 'kg',
            'status' => 'Active',
            'current_price_per_unit' => 55,
        ], $values));
    }

    private function demand(Buyer $buyer, array $values = []): DemandRequest
    {
        return DemandRequest::create(array_merge([
            'buyer_id' => $buyer->buyer_id,
            'product_name' => 'Rice',
            'category' => 'Grains',
            'quantity_needed' => 100,
            'unit' => 'kg',
            'location' => 'Aparri',
            'deadline' => '2026-11-01',
            'status' => 'Active',
        ], $values));
    }

    private function order(Buyer $buyer, ProductListing $listing, array $values = []): int
    {
        $date = $values['order_date'] ?? '2026-10-10';
        $orderId = DB::table('orders')->insertGetId(array_merge([
            'buyer_id' => $buyer->buyer_id,
            'order_date' => $date,
            'delivery_date' => '2026-10-25',
            'total_amount' => 110,
            'fulfillment_status' => 'Order Submitted',
            'payment_status' => 'Pending',
            'created_at' => $date . ' 12:00:00',
            'updated_at' => $date . ' 12:00:00',
        ], $values), 'order_id');
        OrderDetail::create([
            'order_id' => $orderId,
            'listing_id' => $listing->listing_id,
            'quantity' => 2,
            'unit_price' => 55,
            'subtotal' => 110,
        ]);
        return (int) $orderId;
    }

    private function snapshot(int $id): array
    {
        return json_decode(DB::table('generated_reports')->where('id', $id)->value('snapshot'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function streamedContent($response): string
    {
        ob_start();
        $response->baseResponse->sendContent();
        return (string) ob_get_clean();
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        return $rows;
    }

    public function test_report_routes_require_admin_and_super_admin_is_not_implicitly_granted_access(): void
    {
        $this->getJson('/api/admin/reports')->assertUnauthorized();
        $this->postJson('/api/admin/reports/preview', [])->assertUnauthorized();
        $this->getJson('/api/admin/reports/1')->assertUnauthorized();
        $this->getJson('/api/admin/reports/1/download')->assertUnauthorized();
        $this->getJson('/api/admin/reports/1/download/xlsx')->assertUnauthorized();
        $this->getJson('/api/admin/reports/1/download/pdf')->assertUnauthorized();

        $producer = $this->user('producer@reports.test', 'Farmer');
        $buyer = $this->user('buyer@reports.test', 'Buyer');
        $superAdmin = $this->user('super@reports.test', 'Super Admin');
        foreach ([$producer, $buyer, $superAdmin] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/admin/reports')->assertForbidden();
            $this->postJson('/api/admin/reports/preview', [])->assertForbidden();
        }
    }

    public function test_producer_activity_report_applies_product_location_status_and_inclusive_dates(): void
    {
        $a = $this->producer('activity-a@reports.test', ['first_name' => 'José, "Ñ"', 'last_name' => "Dela\nCruz"], 'Aparri', 'Verified');
        $b = $this->producer('activity-b@reports.test', [], 'Buguey', 'Pending');
        $wrongLocation = $this->producer('activity-wrong-location@reports.test', [], 'Buguey', 'Verified');
        $wrongProduct = $this->producer('activity-wrong-product@reports.test', [], 'Aparri', 'Verified');
        $outOfRange = $this->producer('activity-outside@reports.test', [], 'Aparri', 'Verified');
        $userCreatedAt = User::accountCreatedAtColumn();
        DB::table('users')->where('user_id', $a->user_id)->update([$userCreatedAt => '2026-10-01 00:00:00']);
        DB::table('users')->where('user_id', $b->user_id)->update([$userCreatedAt => '2026-10-20 23:59:59']);
        DB::table('users')->where('user_id', $wrongLocation->user_id)->update([$userCreatedAt => '2026-10-05 12:00:00']);
        DB::table('users')->where('user_id', $wrongProduct->user_id)->update([$userCreatedAt => '2026-10-05 12:00:00']);
        DB::table('users')->where('user_id', $outOfRange->user_id)->update([$userCreatedAt => '2026-09-30 23:59:59']);
        $this->listing($a, ['product_name' => 'Fresh Rice', 'created_at' => '2026-10-20 23:59:59']);
        $this->listing($b, ['product_name' => 'Corn', 'created_at' => '2026-10-05 12:00:00']);
        $this->listing($wrongLocation, ['product_name' => 'Rice']);
        $this->listing($wrongProduct, ['product_name' => 'Corn']);
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'producer_activity',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-20',
            'filters' => ['product' => 'Rice', 'location' => 'Aparri', 'status' => 'verified'],
        ]);
        $response->assertOk()
            ->assertJsonPath('report_type', 'producer_activity')
            ->assertJsonPath('row_count', 1)
            ->assertJsonPath('pagination.total_rows', 1)
            ->assertJsonPath('rows.0.producer_name', "José, \"Ñ\" Dela\nCruz")
            ->assertJsonPath('rows.0.listings_created_in_period', 1)
            ->assertJsonPath('summary.verified_producers', 1)
            ->assertJsonPath('filters.product', 'Rice');

        $unsupported = $this->postJson('/api/admin/reports/preview', [
            'type' => 'producer_activity', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
            'filters' => ['unknown' => 'x'],
        ]);
        $unsupported->assertUnprocessable();
    }

    public function test_supply_demand_report_keeps_supply_and_demand_separate_by_product_and_unit(): void
    {
        $producer = $this->producer('supply@reports.test', [], 'Aparri');
        $unverified = $this->producer('unverified@reports.test', [], 'Aparri', 'Pending');
        $buyer = $this->buyer('supply-buyer@reports.test');
        $this->listing($producer, ['product_name' => 'Tomato', 'unit' => 'kg', 'unit_of_measure' => 'kg', 'created_at' => '2026-10-01 00:00:00']);
        $this->listing($producer, ['product_name' => 'Tomato', 'unit' => 'crate', 'unit_of_measure' => 'crate', 'created_at' => '2026-10-02 00:00:00']);
        $this->listing($unverified, ['product_name' => 'Tomato', 'created_at' => '2026-10-02 00:00:00']);
        $wrongLocationProducer = $this->producer('supply-wrong-location@reports.test', [], 'Buguey');
        $this->listing($wrongLocationProducer, ['product_name' => 'Tomato']);
        $this->demand($buyer, ['product_name' => 'Tomato', 'unit' => 'kg', 'quantity_needed' => 80, 'created_at' => '2026-10-03 00:00:00']);
        $this->demand($buyer, ['product_name' => 'Tomato', 'unit' => 'crate', 'quantity_needed' => 5, 'created_at' => '2026-10-04 00:00:00']);
        $this->demand($buyer, ['product_name' => 'Rice', 'unit' => 'kg', 'quantity_needed' => 50, 'location' => 'Buguey', 'created_at' => '2026-10-04 00:00:00']);
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'supply_demand', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
            'filters' => ['product' => 'Tomato', 'location' => 'Aparri'],
        ]);
        $response->assertOk()->assertJsonPath('row_count', 4);
        $rows = $response->json('rows');
        $this->assertEqualsCanonicalizing(['Available Supply', 'Available Supply', 'Active Buyer Demand', 'Active Buyer Demand'], array_column($rows, 'record_type'));
        $this->assertEqualsCanonicalizing(['crate', 'kg'], array_values(array_unique(array_column($rows, 'unit'))));
        $this->assertSame(2, $response->json('summary.available_listing_groups'));
        $this->assertSame(2, $response->json('summary.active_demand_groups'));
        $this->assertEqualsCanonicalizing([
            ['unit' => 'crate', 'quantity' => 20],
            ['unit' => 'kg', 'quantity' => 20],
        ], $response->json('summary.supply_totals_by_unit'));
        $this->assertEqualsCanonicalizing([
            ['unit' => 'crate', 'quantity' => 5],
            ['unit' => 'kg', 'quantity' => 80],
        ], $response->json('summary.demand_totals_by_unit'));
        $this->assertSame('Supply Totals By Unit', $response->json('presentation.summary_tables.0.title'));
        $this->assertSame('Test Admin', $response->json('presentation.approval.prepared_by'));
        $this->assertSame('System-generated snapshot; not an approval', $response->json('presentation.metadata.7.value'));
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'supply_demand', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
            'filters' => ['status' => 'Active'],
        ])->assertUnprocessable();
    }

    public function test_demand_summary_filters_and_unit_summaries_are_applied(): void
    {
        $buyer = $this->buyer('demand-buyer@reports.test', 'Aparri Schools');
        $this->demand($buyer, ['product_name' => 'Rice', 'unit' => 'kg', 'quantity_needed' => 10, 'location' => 'Aparri', 'status' => 'Active', 'created_at' => '2026-10-01 00:00:00']);
        $this->demand($buyer, ['product_name' => 'Rice', 'unit' => 'sack', 'quantity_needed' => 4, 'location' => 'Aparri', 'status' => 'Cancelled', 'created_at' => '2026-10-20 23:59:59']);
        $this->demand($buyer, ['product_name' => 'Corn', 'unit' => 'kg', 'quantity_needed' => 100, 'location' => 'Buguey', 'status' => 'Active', 'created_at' => '2026-10-05 00:00:00']);
        $this->demand($buyer, ['product_name' => 'Rice', 'unit' => 'kg', 'quantity_needed' => 30, 'location' => 'Buguey', 'status' => 'Cancelled', 'created_at' => '2026-10-05 00:00:00']);
        $this->demand($buyer, ['product_name' => 'Corn', 'unit' => 'kg', 'quantity_needed' => 40, 'location' => 'Aparri', 'status' => 'Cancelled', 'created_at' => '2026-10-05 00:00:00']);
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
            'filters' => ['product' => 'Rice', 'location' => 'Aparri', 'status' => 'Cancelled'],
        ]);
        $response->assertOk()->assertJsonPath('row_count', 1)->assertJsonPath('rows.0.quantity_requested', 4)->assertJsonPath('rows.0.unit', 'sack');
        $this->assertEquals([['unit' => 'sack', 'quantity' => 4]], $response->json('summary.requested_quantities_by_unit'));
    }

    public function test_order_transactions_use_real_order_dates_status_filters_and_non_revenue_labels(): void
    {
        $producer = $this->producer('orders-producer@reports.test');
        $buyer = $this->buyer('orders-buyer@reports.test', 'District Hospital');
        $rice = $this->listing($producer, ['product_name' => 'Rice']);
        $corn = $this->listing($producer, ['product_name' => 'Corn']);
        $this->order($buyer, $rice, ['order_date' => '2026-10-01', 'fulfillment_status' => 'Completed', 'total_amount' => 110]);
        $this->order($buyer, $corn, ['order_date' => '2026-10-20', 'fulfillment_status' => 'Order Submitted', 'total_amount' => 250]);
        $this->order($buyer, $rice, ['order_date' => '2026-10-05', 'fulfillment_status' => 'Order Submitted', 'total_amount' => 160]);
        $this->order($buyer, $corn, ['order_date' => '2026-10-06', 'fulfillment_status' => 'Completed', 'total_amount' => 275]);
        $this->order($buyer, $rice, ['order_date' => '2026-09-30', 'fulfillment_status' => 'Completed', 'total_amount' => 300]);
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'order_transactions', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
            'filters' => ['product' => 'Rice', 'status' => 'Completed'],
        ]);
        $response->assertOk()->assertJsonPath('row_count', 1)->assertJsonPath('rows.0.buyer_organization', 'District Hospital')
            ->assertJsonPath('rows.0.recorded_order_amount', 110)->assertJsonPath('summary.recorded_order_amount_total_not_revenue', 110);
        $this->assertStringContainsString('not realized revenue', strtolower($response->json('definitions.recorded_order_amount')));
    }

    public function test_program_utilization_is_unsupported_and_date_validation_is_consistent(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'program_utilization', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
        ])->assertUnprocessable();

        $this->postJson('/api/admin/reports/preview', [
            'type' => 'demand_summary', 'start_date' => '2026-10-21', 'end_date' => '2026-10-20',
        ])->assertUnprocessable();
        $this->postJson('/api/admin/reports/preview', [
            'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-21',
        ])->assertUnprocessable();
    }

    public function test_empty_report_can_be_previewed_and_saved_with_an_honest_zero_row_count(): void
    {
        $admin = $this->admin('empty-report-admin@reports.test');
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports/preview', [
            'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
        ])->assertOk()->assertJsonPath('row_count', 0)->assertJsonCount(0, 'rows');

        $created = $this->postJson('/api/admin/reports', [
            'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
        ])->assertCreated()->assertJsonPath('report.row_count', 0);
        $reportId = $created->json('id');
        $this->assertDatabaseHas('generated_reports', [
            'id' => $reportId,
            'generated_by' => $admin->user_id,
        ]);

        $csv = $this->get('/api/admin/reports/' . $reportId . '/download');
        $csv->assertOk();
        $this->assertStringContainsString('No records found.', $this->streamedContent($csv));

        $xlsx = $this->get('/api/admin/reports/' . $reportId . '/download/xlsx')->assertOk();
        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($xlsx->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('No records found.', $sheet);
        $zip->close();

        $pdf = $this->get('/api/admin/reports/' . $reportId . '/download/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_saved_reports_are_admin_private_and_super_admin_has_no_implicit_access(): void
    {
        $producer = $this->producer('ownership-producer@reports.test');
        $this->listing($producer);
        $firstAdmin = $this->admin('first-admin@reports.test');
        $otherAdmin = $this->admin('other-admin@reports.test');
        $reportId = $this->actingAs($firstAdmin, 'sanctum')->postJson('/api/admin/reports', [
            'type' => 'producer_activity', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('generated_reports', ['id' => $reportId, 'generated_by' => $firstAdmin->user_id]);
        $this->actingAs($otherAdmin, 'sanctum')->getJson("/api/admin/reports/{$reportId}")->assertNotFound();
        $this->getJson("/api/admin/reports/{$reportId}/download")->assertNotFound();
        $this->getJson("/api/admin/reports/{$reportId}/download/xlsx")->assertNotFound();
        $this->getJson("/api/admin/reports/{$reportId}/download/pdf")->assertNotFound();

        $superAdmin = $this->user('super-reports@reports.test', 'Super Admin');
        $this->actingAs($superAdmin, 'sanctum')->getJson('/api/admin/reports')->assertForbidden();
        $this->getJson("/api/admin/reports/{$reportId}")->assertForbidden();
    }

    public function test_snapshot_pagination_full_csv_export_and_csv_escaping_unicode(): void
    {
        $buyer = $this->buyer('unicode@reports.test', "Aparri, \"José\"\nDistrict");
        for ($index = 0; $index < 30; $index++) {
            $this->demand($buyer, ['product_name' => $index === 0 ? "Bigas, \"Premium\"\nÑ" : 'Demand ' . $index]);
        }
        $admin = $this->admin('csv-admin@reports.test');
        DB::table('users')->where('user_id', $admin->user_id)->update([
            User::accountCreatedAtColumn() => '2026-10-12',
        ]);

        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports', [
            'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
        ])->assertCreated();
        $reportId = $created->json('id');
        $created->assertJsonPath('report.row_count', 30)->assertJsonPath('report.pagination.per_page', 25);
        $snapshot = $this->snapshot($reportId);
        $this->assertSame(30, $snapshot['row_count']);

        $rows = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reports/' . $reportId . '?page=2&per_page=1')
            ->assertOk()->assertJsonPath('pagination.page', 2)->assertJsonPath('pagination.total_rows', 30);
        $this->assertSame($snapshot['row_count'], $rows->json('row_count'));

        $csvResponse = $this->get('/api/admin/reports/' . $reportId . '/download');
        $csvResponse->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $this->streamedContent($csvResponse);
        $parsed = $this->parseCsv($csv);
        $headerRowIndex = collect($parsed)->search(fn ($row) => in_array('Buyer Organization', $row, true));
        $this->assertNotFalse($headerRowIndex);
        $headers = $parsed[$headerRowIndex];
        $headerIndex = array_search('Buyer Organization', $headers, true);
        $reportRow = $parsed[$headerRowIndex + 1];
        $this->assertSame("Aparri, \"José\"\nDistrict", $reportRow[$headerIndex]);
        $productIndex = array_search('Product', $headers, true);
        $this->assertSame("Bigas, \"Premium\"\nÑ", $reportRow[$productIndex]);
        $this->assertStringContainsString('Demand 29', $csv);
        $this->assertStringNotContainsString('Demand 30', $csv);
        $this->assertStringContainsString('Requested Quantities By Unit', $csv);
        $this->assertStringContainsString('Executive Summary', $csv);
        $this->assertStringNotContainsString('{"unit"', $csv);
    }

    public function test_xlsx_and_pdf_are_real_formats_generated_from_the_saved_full_snapshot(): void
    {
        $buyer = $this->buyer('export@reports.test', 'Snapshot Buyer');
        for ($index = 0; $index < 30; $index++) {
            $this->demand($buyer, ['product_name' => 'Demand ' . $index]);
        }
        $admin = $this->admin('export-admin@reports.test');
        $reportId = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/reports', [
            'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
        ])->assertCreated()->json('id');
        $saved = $this->snapshot($reportId);
        $this->assertSame(30, $saved['row_count']);
        DB::table('demand_requests')->where('request_id', $saved['rows'][0]['request_id'])->update(['product_name' => 'Changed After Snapshot']);

        $xlsx = $this->get('/api/admin/reports/' . $reportId . '/download/xlsx')->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx->headers->get('content-type'));
        $this->assertStringEndsWith('.xlsx', $xlsx->headers->get('content-disposition'));
        $xlsxFile = $xlsx->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($xlsxFile));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('Demand 29', $sheet);
        $this->assertStringNotContainsString('Changed After Snapshot', $sheet);
        $this->assertStringContainsString('Requested Quantities By Unit', $sheet);
        $this->assertStringContainsString('>kg<', $sheet);
        $this->assertStringNotContainsString('{&quot;unit&quot;', $sheet);
        $zip->close();

        $pdf = $this->get('/api/admin/reports/' . $reportId . '/download/pdf')->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringEndsWith('.pdf', $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertGreaterThan(1000, strlen($pdf->getContent()));
    }

    public function test_history_is_private_and_pagination_does_not_return_other_admin_rows(): void
    {
        $admin = $this->admin('history-one@reports.test');
        $another = $this->admin('history-two@reports.test');
        foreach ([$admin, $another] as $owner) {
            $this->actingAs($owner, 'sanctum')->postJson('/api/admin/reports', [
                'type' => 'demand_summary', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20',
            ])->assertCreated();
        }

        $history = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reports?page=1&per_page=1')->assertOk();
        $history->assertJsonPath('pagination.total_rows', 1)->assertJsonCount(1, 'data');
        $this->assertSame($admin->user_id, DB::table('generated_reports')->where('id', $history->json('data.0.id'))->value('generated_by'));
    }
}
