<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\EmployeeWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_guest_cannot_access_workspace(): void
    {
        $this->getJson('/api/workspace')
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_employee_workspace(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace')
            ->assertForbidden();
    }

    public function test_owner_cannot_access_employee_workspace(): void
    {
        $user = User::factory()->owner()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace')
            ->assertForbidden();
    }

    public function test_admin_manager_cannot_access_employee_workspace(): void
    {
        $user = User::factory()->adminManager()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace')
            ->assertForbidden();
    }

    public function test_inactive_employee_cannot_access_workspace(): void
    {
        $user = User::factory()->webDeveloper()->inactive()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_employee_receives_role_specific_workspace(): void
    {
        $cases = [
            [UserRole::WebDeveloper, 'web-developer', ['projects', 'tasks', 'files', 'messages', 'deadlines', 'requirements', 'revisions']],
            [UserRole::GraphicDesigner, 'graphic-designer', ['projects', 'design-briefs', 'tasks', 'deadlines', 'files', 'revisions', 'messages']],
            [UserRole::VideoEditor, 'video-editor', ['projects', 'tasks', 'files', 'revisions', 'deadlines', 'messages']],
            [UserRole::MarketingSpecialist, 'marketing', ['campaigns', 'tasks', 'projects', 'content', 'deadlines', 'client-requests', 'reports']],
            [UserRole::EventSpecialist, 'event', ['events', 'tasks', 'projects', 'deadlines', 'files', 'client-requests']],
            [UserRole::PrintingSpecialist, 'printing', ['printing-queue', 'tasks', 'projects', 'deadlines', 'files', 'messages']],
            [UserRole::MediaBuyer, 'media-buyer', ['campaigns', 'tasks', 'projects', 'deadlines', 'budgets', 'reports', 'performance']],
            [UserRole::AccountManager, 'account-manager', ['tasks', 'task-progress', 'deadlines', 'clients', 'client-requests', 'projects', 'files']],
            [UserRole::Hr, 'hr', ['employees', 'active-employees', 'inactive-employees', 'tasks', 'attendance', 'employee-requests']],
        ];

        foreach ($cases as [$role, $workspace, $expectedWidgets]) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();

            $user = User::factory()->create(['role' => $role]);

            $response = $this->withToken($this->tokenFor($user))
                ->getJson('/api/workspace')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.role', $role->value)
                ->assertJsonPath('data.workspace', $workspace)
                ->assertJsonPath('data.is_active', true)
                ->assertJsonMissingPath('data.password');

            $widgets = $response->json('data.widgets');
            $this->assertContains('overview', $widgets);
            foreach ($expectedWidgets as $widgetId) {
                $this->assertContains($widgetId, $widgets);
            }
            $this->assertNotContains('owner-dashboard', $widgets);
            $this->assertSame($widgets, EmployeeWorkspace::widgetIdsFor($role));
        }
    }

    public function test_printing_workspace_does_not_include_owner_widgets(): void
    {
        $user = User::factory()->printingSpecialist()->create();

        $widgets = $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace')
            ->assertOk()
            ->json('data.widgets');

        $this->assertContains('printing-queue', $widgets);
        $this->assertContains('projects', $widgets);
        $this->assertNotContains('campaigns', $widgets);
    }

    public function test_role_change_updates_workspace_resolution(): void
    {
        $user = User::factory()->webDeveloper()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'web-developer');

        $user->update(['role' => UserRole::GraphicDesigner]);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::GraphicDesigner->value)
            ->assertJsonPath('data.workspace', 'graphic-designer');

        $this->assertContains(
            'revisions',
            $this->withToken($token)->getJson('/api/workspace')->json('data.widgets')
        );
        $this->assertContains(
            'design-briefs',
            $this->withToken($token)->getJson('/api/workspace')->json('data.widgets')
        );
        $this->assertNotContains(
            'requirements',
            $this->withToken($token)->getJson('/api/workspace')->json('data.widgets')
        );
    }

    public function test_employee_cannot_access_owner_dashboard_or_employees(): void
    {
        $user = User::factory()->webDeveloper()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/admin/employees')
            ->assertForbidden();
    }

    public function test_unknown_or_non_workspace_roles_have_no_capabilities(): void
    {
        $this->assertSame([], EmployeeWorkspace::capabilitiesFor(UserRole::Owner));
        $this->assertSame([], EmployeeWorkspace::capabilitiesFor(UserRole::Customer));
        $this->assertSame([], EmployeeWorkspace::capabilitiesFor(UserRole::AdminManager));
        $this->assertSame([], EmployeeWorkspace::widgetIdsFor(UserRole::Owner));
    }
}
