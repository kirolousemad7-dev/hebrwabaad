<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebDeveloperWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_web_developer_can_access_workspace_and_developer_summary(): void
    {
        $user = User::factory()->webDeveloper()->create();
        $token = $this->tokenFor($user);

        $workspace = $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'web-developer')
            ->assertJsonPath('data.role', UserRole::WebDeveloper->value)
            ->json('data');

        $this->assertSame(
            ['overview', 'tasks', 'projects', 'deadlines', 'requirements', 'revisions', 'files', 'messages'],
            $workspace['widgets']
        );

        $this->assertTrue($workspace['domains']['files']['available']);

        foreach (['requirements', 'revisions', 'messages'] as $domain) {
            $this->assertFalse($workspace['domains'][$domain]['available']);
            $this->assertSame('unavailable', $workspace['domains'][$domain]['status']);
        }

        $this->assertTrue($workspace['domains']['tasks']['available']);
        $this->assertTrue($workspace['domains']['deadlines']['available']);
        $this->assertTrue($workspace['domains']['projects']['available']);

        $this->withToken($token)
            ->getJson('/api/workspace/developer')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'web-developer')
            ->assertJsonPath('data.domains.projects.available', true);
    }

    public function test_other_roles_cannot_access_developer_workspace_endpoint(): void
    {
        $cases = [
            User::factory()->create(),
            User::factory()->owner()->create(),
            User::factory()->adminManager()->create(),
            User::factory()->printingSpecialist()->create(),
            User::factory()->create(['role' => UserRole::GraphicDesigner]),
        ];

        foreach ($cases as $user) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();

            $this->withToken($this->tokenFor($user))
                ->getJson('/api/workspace/developer')
                ->assertForbidden();
        }
    }

    public function test_web_developer_cannot_access_owner_routes_or_data(): void
    {
        $user = User::factory()->webDeveloper()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/employees/'.$user->id)->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/services')->assertForbidden();
    }

    public function test_web_developer_does_not_receive_other_employees_records(): void
    {
        $developer = User::factory()->webDeveloper()->create();
        User::factory()->printingSpecialist()->create(['name' => 'Other Staff']);

        $payload = $this->withToken($this->tokenFor($developer))
            ->getJson('/api/workspace')
            ->assertOk()
            ->json('data');

        $this->assertSame($developer->id, $payload['id']);
        $this->assertSame($developer->email, $payload['email']);
        $this->assertStringNotContainsString('Other Staff', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_role_change_away_from_web_developer_revokes_developer_endpoint(): void
    {
        $user = User::factory()->webDeveloper()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/workspace/developer')
            ->assertOk();

        $user->update(['role' => UserRole::EventSpecialist]);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson('/api/workspace/developer')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'event');

        $widgets = $this->withToken($token)->getJson('/api/workspace')->json('data.widgets');
        $this->assertNotContains('requirements', $widgets);
        $this->assertContains('events', $widgets);
        $this->assertContains('tasks', $widgets);
    }
}
