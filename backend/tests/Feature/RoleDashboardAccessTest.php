<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\RoleDashboardAccess;
use App\Models\User;
use App\Services\DashboardAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_receives_full_dashboard_access_in_me_payload(): void
    {
        $owner = User::factory()->owner()->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk();
        $access = $response->json('data.dashboard_access');
        $this->assertIsArray($access);
        $this->assertTrue($access['dashboard']);
        $this->assertTrue($access['finance']);
        $this->assertTrue($access['projects']);
        $this->assertTrue($access['role_access']);
    }

    public function test_customer_me_payload_omits_dashboard_access(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonMissingPath('data.dashboard_access');
    }

    public function test_account_manager_inherits_default_workspace_modules(): void
    {
        $manager = User::factory()->accountManager()->create();

        Sanctum::actingAs($manager);

        $access = $this->getJson('/api/auth/me')->json('data.dashboard_access');

        $this->assertTrue($access['workspace']);
        $this->assertTrue($access['workspace.projects']);
        $this->assertFalse($access['finance']);
        $this->assertFalse($access['employees']);
    }

    public function test_owner_can_view_and_update_role_dashboard_access(): void
    {
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        $this->getJson('/api/admin/role-dashboard-access')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'roles',
                    'catalog',
                ],
            ]);

        $update = $this->putJson('/api/admin/role-dashboard-access/ACCOUNT_MANAGER', [
            'modules' => [
                'workspace.projects' => false,
                'finance' => true,
            ],
        ])->assertOk();

        $modules = $update->json('data.modules');
        $this->assertFalse($modules['workspace.projects']);
        $this->assertTrue($modules['finance']);

        $this->assertDatabaseHas('role_dashboard_access', [
            'role' => 'ACCOUNT_MANAGER',
            'module_key' => 'workspace.projects',
            'enabled' => 0,
        ]);
    }

    public function test_role_access_update_applies_to_all_users_with_that_role(): void
    {
        $owner = User::factory()->owner()->create();
        $a = User::factory()->accountManager()->create();
        $b = User::factory()->accountManager()->create();
        $service = app(DashboardAccessService::class);

        $this->assertTrue($service->canAccess($a, 'workspace.projects'));
        $this->assertTrue($service->canAccess($b, 'workspace.projects'));

        Sanctum::actingAs($owner);
        $this->putJson('/api/admin/role-dashboard-access/ACCOUNT_MANAGER', [
            'modules' => ['workspace.projects' => false],
        ])->assertOk();

        $this->assertFalse($service->canAccess($a->fresh(), 'workspace.projects'));
        $this->assertFalse($service->canAccess($b->fresh(), 'workspace.projects'));
    }

    public function test_changing_user_role_changes_effective_dashboard_access(): void
    {
        $user = User::factory()->accountManager()->create();
        $service = app(DashboardAccessService::class);

        $this->assertTrue($service->canAccess($user, 'workspace.projects'));
        $this->assertFalse($service->canAccess($user, 'crm'));

        $user->update(['role' => UserRole::SalesManager]);

        $this->assertFalse($service->canAccess($user->fresh(), 'workspace.projects'));
        $this->assertTrue($service->canAccess($user->fresh(), 'crm'));
    }

    public function test_employee_cannot_modify_role_dashboard_access(): void
    {
        $manager = User::factory()->accountManager()->create();
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/role-dashboard-access/ACCOUNT_MANAGER', [
            'modules' => ['finance' => true],
        ])->assertForbidden();

        $this->assertDatabaseMissing('role_dashboard_access', [
            'role' => 'ACCOUNT_MANAGER',
            'module_key' => 'finance',
        ]);
    }

    public function test_admin_manager_cannot_modify_role_dashboard_access(): void
    {
        $admin = User::factory()->adminManager()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/role-dashboard-access')->assertForbidden();
        $this->putJson('/api/admin/role-dashboard-access/ADMIN_MANAGER', [
            'modules' => ['finance' => true],
        ])->assertForbidden();
    }

    public function test_disabled_module_blocks_api_even_when_role_middleware_allows(): void
    {
        $admin = User::factory()->adminManager()->create();
        RoleDashboardAccess::query()->create([
            'role' => UserRole::AdminManager->value,
            'module_key' => 'suppliers',
            'enabled' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/suppliers')->assertForbidden();
    }

    public function test_enabled_module_allows_api_for_authorized_role(): void
    {
        $admin = User::factory()->adminManager()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/suppliers')->assertOk();
    }

    public function test_owner_override_ignores_role_dashboard_rows(): void
    {
        $owner = User::factory()->owner()->create();
        RoleDashboardAccess::query()->create([
            'role' => UserRole::Owner->value,
            'module_key' => 'finance',
            'enabled' => false,
        ]);

        $service = app(DashboardAccessService::class);

        $this->assertTrue($service->canAccess($owner, 'finance'));
    }

    public function test_owner_cannot_restrict_owner_role_via_api(): void
    {
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        $this->putJson('/api/admin/role-dashboard-access/OWNER', [
            'modules' => ['finance' => false],
        ])->assertNotFound();
    }

    public function test_unauthenticated_role_access_returns_401(): void
    {
        $this->getJson('/api/admin/role-dashboard-access')->assertUnauthorized();
    }
}
