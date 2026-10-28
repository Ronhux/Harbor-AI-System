<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\DemandRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdministrativeAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $type): User
    {
        return User::create([
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'first_name' => 'Test',
            'last_name' => $type,
            'user_type' => $type,
        ]);
    }

    public function test_admin_demand_monitoring_reads_buyer_requests_and_rejects_other_roles(): void
    {
        $buyerUser = $this->makeUser('buyer@test.local', 'Buyer');
        $buyer = Buyer::create(['user_id' => $buyerUser->user_id, 'organization_name' => 'Aparri LGU', 'buyer_type' => 'Institution', 'verification_status' => 'Verified']);
        DemandRequest::create(['buyer_id' => $buyer->buyer_id, 'product_name' => 'Tilapia', 'category' => 'Fish', 'quantity_needed' => 500, 'unit' => 'kg', 'max_price_per_unit' => 180, 'location' => 'Aparri', 'deadline' => '2026-10-30', 'status' => 'open']);

        $this->actingAs($buyerUser, 'sanctum')->getJson('/api/admin/demands')->assertForbidden();

        $admin = $this->makeUser('admin@test.local', 'Admin');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/demands')
            ->assertOk()->assertJsonPath('data.0.product', 'Tilapia')->assertJsonPath('data.0.quantity', 500)->assertJsonPath('data.0.buyer', 'Aparri LGU');
    }

    public function test_only_super_admin_can_manage_administrator_access(): void
    {
        $admin = $this->makeUser('admin2@test.local', 'Admin');
        $regularAdmin = $this->makeUser('admin1@test.local', 'Admin');
        $this->actingAs($regularAdmin, 'sanctum')->getJson('/api/superadmin/overview')->assertForbidden();

        $superAdmin = $this->makeUser('super@test.local', 'Super Admin');
        $this->actingAs($superAdmin, 'sanctum')->getJson('/api/superadmin/overview')->assertOk()->assertJsonPath('stats.admins', 2);
        $this->patchJson("/api/superadmin/admins/{$admin->user_id}/status", ['is_active' => false])->assertOk();
        $this->assertDatabaseHas('users', ['user_id' => $admin->user_id, 'is_active' => false]);
        $this->assertDatabaseHas('admin_activity_logs', ['user_id' => $superAdmin->user_id, 'action' => 'Deactivated administrator']);
        $admin->refresh();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/demands')->assertForbidden();

        $this->actingAs($superAdmin, 'sanctum')
            ->patchJson("/api/superadmin/admins/{$admin->user_id}/status", ['is_active' => true])
            ->assertOk();
        $this->assertDatabaseHas('users', ['user_id' => $admin->user_id, 'is_active' => true]);
        $admin->refresh();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/demands')->assertOk();
    }

    public function test_super_admin_administrator_list_is_authorized_searchable_filterable_and_paginated(): void
    {
        $superAdmin = $this->makeUser('directory-super@test.local', 'Super Admin');
        $this->makeUser('directory-ordinary-admin@test.local', 'Admin');
        $this->makeUser('directory-buyer@test.local', 'Buyer');
        $this->makeUser('directory-producer@test.local', 'Farmer');

        for ($index = 1; $index <= 17; $index++) {
            User::create([
                'email' => sprintf('directory-%02d@test.local', $index),
                'password_hash' => bcrypt('secret123'),
                'first_name' => sprintf('Directory %02d', $index),
                'last_name' => 'Administrator',
                'user_type' => 'Admin',
                'is_active' => $index % 2 === 1,
            ]);
        }

        $this->getJson('/api/superadmin/admins')->assertUnauthorized();

        $this->actingAs(User::where('email', 'directory-ordinary-admin@test.local')->first(), 'sanctum')
            ->getJson('/api/superadmin/admins')
            ->assertForbidden();
        $this->actingAs(User::where('email', 'directory-buyer@test.local')->first(), 'sanctum')
            ->getJson('/api/superadmin/admins')
            ->assertForbidden();
        $this->actingAs(User::where('email', 'directory-producer@test.local')->first(), 'sanctum')
            ->getJson('/api/superadmin/admins')
            ->assertForbidden();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/superadmin/admins?search=dIrEcToRy%2001')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.email', 'directory-01@test.local')
            ->assertJsonStructure(['data' => [['joined_at']]])
            ->assertJsonMissingPath('data.0.password_hash')
            ->assertJsonMissingPath('data.0.token');

        $this->getJson('/api/superadmin/admins?status=active&per_page=5&page=1')
            ->assertOk()
            ->assertJsonPath('pagination.total', 10)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.is_active', true);

        $this->getJson('/api/superadmin/admins?status=inactive&per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('pagination.page', 2)
            ->assertJsonPath('pagination.per_page', 5)
            ->assertJsonPath('pagination.total', 8)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.is_active', false);

        $this->getJson('/api/superadmin/admins?search=no-such-administrator')
            ->assertOk()
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/superadmin/admins?status=suspended')
            ->assertUnprocessable();
    }

    public function test_overview_remains_available_if_a_statistics_table_is_unavailable(): void
    {
        $superAdmin = $this->makeUser('super2@test.local', 'Super Admin');
        Schema::drop('orders');
        $this->actingAs($superAdmin, 'sanctum')->getJson('/api/superadmin/overview')
            ->assertOk()->assertJsonPath('stats.orders', null)->assertJsonPath('warnings.0', 'Orders totals are temporarily unavailable.');
    }
}
