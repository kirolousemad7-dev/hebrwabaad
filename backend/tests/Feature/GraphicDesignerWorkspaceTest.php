<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphicDesignerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_graphic_designer_can_access_workspace_and_designer_summary(): void
    {
        $user = User::factory()->graphicDesigner()->create();
        $token = $this->tokenFor($user);

        $workspace = $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'graphic-designer')
            ->assertJsonPath('data.role', UserRole::GraphicDesigner->value)
            ->json('data');

        $this->assertSame(
            ['overview', 'projects', 'design-briefs', 'tasks', 'deadlines', 'files', 'revisions', 'messages'],
            $workspace['widgets']
        );
        $this->assertNotContains('requirements', $workspace['widgets']);
        $this->assertNotContains('printing-queue', $workspace['widgets']);

        $this->assertTrue($workspace['domains']['files']['available']);

        foreach (['design-briefs', 'revisions', 'messages'] as $domain) {
            $this->assertFalse($workspace['domains'][$domain]['available']);
            $this->assertSame('unavailable', $workspace['domains'][$domain]['status']);
        }

        $this->assertTrue($workspace['domains']['tasks']['available']);
        $this->assertTrue($workspace['domains']['deadlines']['available']);
        $this->assertTrue($workspace['domains']['projects']['available']);

        $this->withToken($token)
            ->getJson('/api/workspace/designer')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'graphic-designer')
            ->assertJsonPath('data.domains.projects.available', true);
    }

    public function test_other_roles_cannot_access_designer_workspace_endpoint(): void
    {
        $cases = [
            User::factory()->create(),
            User::factory()->owner()->create(),
            User::factory()->adminManager()->create(),
            User::factory()->printingSpecialist()->create(),
            User::factory()->webDeveloper()->create(),
            User::factory()->create(['role' => UserRole::EventSpecialist]),
        ];

        foreach ($cases as $user) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();

            $this->withToken($this->tokenFor($user))
                ->getJson('/api/workspace/designer')
                ->assertForbidden();
        }
    }

    public function test_graphic_designer_cannot_access_owner_routes_or_developer_endpoint(): void
    {
        $user = User::factory()->graphicDesigner()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/employees/'.$user->id)->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/services')->assertForbidden();
        $this->withToken($token)->getJson('/api/workspace/developer')->assertForbidden();
    }

    public function test_graphic_designer_does_not_receive_other_employees_records(): void
    {
        $designer = User::factory()->graphicDesigner()->create();
        User::factory()->webDeveloper()->create(['name' => 'Other Staff']);

        $payload = $this->withToken($this->tokenFor($designer))
            ->getJson('/api/workspace')
            ->assertOk()
            ->json('data');

        $this->assertSame($designer->id, $payload['id']);
        $this->assertSame($designer->email, $payload['email']);
        $this->assertStringNotContainsString('Other Staff', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_inactive_graphic_designer_cannot_access_workspace(): void
    {
        $user = User::factory()->graphicDesigner()->inactive()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');

        $this->withToken($token)
            ->getJson('/api/workspace/designer')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_inactive_graphic_designer_cannot_login(): void
    {
        $user = User::factory()->graphicDesigner()->inactive()->create([
            'email' => 'inactive.designer@hebr.test',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_role_change_away_from_graphic_designer_revokes_designer_endpoint(): void
    {
        $user = User::factory()->graphicDesigner()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/workspace/designer')
            ->assertOk();

        $user->update(['role' => UserRole::WebDeveloper]);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson('/api/workspace/designer')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'web-developer');

        $widgets = $this->withToken($token)->getJson('/api/workspace')->json('data.widgets');
        $this->assertNotContains('design-briefs', $widgets);
        $this->assertContains('requirements', $widgets);

        $user->update(['role' => UserRole::EventSpecialist]);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'event');

        $eventWidgets = $this->withToken($token)->getJson('/api/workspace')->json('data.widgets');
        $this->assertContains('events', $eventWidgets);
        $this->assertNotContains('design-briefs', $eventWidgets);
        $this->assertNotContains('revisions', $eventWidgets);
    }
}
