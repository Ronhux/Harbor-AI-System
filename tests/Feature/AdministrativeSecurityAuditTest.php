<?php

namespace Tests\Feature;

use App\Models\RegisteredProducer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdministrativeSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $type, bool $active = true): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => $type,
            'user_type' => $type,
            'is_active' => $active,
        ]);
    }

    public function test_unauthenticated_test_registration_and_sql_endpoints_are_not_registered(): void
    {
        $postRoutes = Route::getRoutes()->getRoutesByMethod()['POST'] ?? [];
        $registeredUris = array_map(static fn ($route) => $route->uri(), $postRoutes);
        $this->assertNotContains('api/test-register', $registeredUris);
        $this->assertNotContains('api/test-sql', $registeredUris);

        $testRegisterResponse = $this->postJson('/api/test-register', [
            'email' => 'not-created@example.test',
            'password' => 'secret123',
            'name' => 'Not Created',
        ]);
        $this->assertFalse($testRegisterResponse->isSuccessful());

        $testSqlResponse = $this->postJson('/api/test-sql', [
            'email' => 'not-created@example.test',
            'password' => 'secret123',
            'name' => 'Not Created',
        ]);
        $this->assertFalse($testSqlResponse->isSuccessful());

        $this->assertDatabaseMissing('users', ['email' => 'not-created@example.test']);
    }

    public function test_official_registry_is_read_only_for_admin_and_import_is_super_admin_only(): void
    {
        $record = RegisteredProducer::create([
            'rsbsa_number' => 'RSBSA-SECURE-1',
            'name' => 'Official Name',
            'full_name' => 'Official Name',
            'municipality' => 'Aparri',
            'province' => 'Cagayan',
            'farm_type' => 'Rice',
        ]);
        $admin = $this->makeUser('registry-admin@example.test', 'Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/registry')
            ->assertOk()
            ->assertJsonFragment(['rsbsa_number' => 'RSBSA-SECURE-1']);

        $file = UploadedFile::fake()->createWithContent(
            'registry.csv',
            "rsbsa_number,name,municipality,province,farm_type,farm_size,contact_number,email\nRSBSA-SECURE-1,Changed Name,Aparri,Cagayan,Rice,2 hectares,09170000000,farmer@example.test\n",
        );

        $adminImportResponse = $this->post('/api/admin/registry/import', ['file' => $file]);
        $this->assertFalse($adminImportResponse->isSuccessful());
        $adminUpdateResponse = $this->putJson('/api/admin/registry/' . $record->registry_id, ['name' => 'Changed Name']);
        $this->assertFalse($adminUpdateResponse->isSuccessful());
        $adminDeleteResponse = $this->deleteJson('/api/admin/registry/' . $record->registry_id);
        $this->assertFalse($adminDeleteResponse->isSuccessful());

        $this->post('/api/superadmin/registry/import', ['file' => $file])->assertForbidden();
        $this->assertDatabaseHas('registered_producers', [
            'registry_id' => $record->registry_id,
            'name' => 'Official Name',
            'rsbsa_number' => 'RSBSA-SECURE-1',
        ]);

        $superAdmin = $this->makeUser('registry-super@example.test', 'Super Admin');
        $authorizedFile = UploadedFile::fake()->createWithContent(
            'registry.csv',
            "rsbsa_number,name,municipality,province,farm_type,farm_size,contact_number,email\nRSBSA-SECURE-1,Updated Official Name,Aparri,Cagayan,Rice,2 hectares,09170000000,farmer@example.test\n",
        );

        $this->actingAs($superAdmin, 'sanctum')
            ->post('/api/superadmin/registry/import', ['file' => $authorizedFile])
            ->assertOk();

        $this->assertDatabaseHas('registered_producers', [
            'registry_id' => $record->registry_id,
            'name' => 'Updated Official Name',
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $superAdmin->user_id,
            'action' => 'Imported official producer registry',
            'subject' => 'api/superadmin/registry/import',
            'status' => 'success',
        ]);
    }

    public function test_admin_report_audit_records_completed_success_and_validation_failure_without_payload(): void
    {
        $admin = $this->makeUser('audit-admin@example.test', 'Admin');
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/admin/reports/preview', [
            'type' => 'producer_activity',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ])->assertOk();

        $this->postJson('/api/admin/reports/preview', [
            'type' => 'invalid-report-type',
            'password' => 'never-store-this-secret',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->user_id,
            'action' => 'Previewed admin report',
            'subject' => 'api/admin/reports/preview',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $admin->user_id,
            'action' => 'Previewed admin report',
            'subject' => 'api/admin/reports/preview',
            'status' => 'failure',
        ]);

        $logs = json_encode(DB::table('admin_activity_logs')->get()->toArray());
        $this->assertStringNotContainsString('never-store-this-secret', $logs);
        $this->assertSame(2, DB::table('admin_activity_logs')->count());
    }

    public function test_inactive_admin_cannot_log_in_or_use_protected_admin_endpoints(): void
    {
        $admin = $this->makeUser('inactive-admin@example.test', 'Admin', false);

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/demands')
            ->assertForbidden();

        $this->assertDatabaseCount('admin_activity_logs', 0);
    }

    public function test_super_admin_cannot_deactivate_their_own_account(): void
    {
        $superAdmin = $this->makeUser('self-admin@example.test', 'Super Admin');

        $this->actingAs($superAdmin, 'sanctum')
            ->patchJson('/api/superadmin/admins/' . $superAdmin->user_id . '/status', ['is_active' => false])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'user_id' => $superAdmin->user_id,
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('admin_activity_logs', 0);
    }
}
