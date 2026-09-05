<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceExpansionTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_new_roles_receive_dedicated_workspaces(): void
    {
        $cases = [
            [User::factory()->mediaBuyer()->create(), 'media-buyer', '/api/workspace/media-buyer'],
            [User::factory()->videoEditor()->create(), 'video-editor', '/api/workspace/video-editor'],
            [User::factory()->accountManager()->create(), 'account-manager', '/api/workspace/account-manager'],
            [User::factory()->hr()->create(), 'hr', '/api/workspace/hr'],
            [User::factory()->marketingSpecialist()->create(), 'marketing', '/api/workspace/marketing'],
            [User::factory()->eventSpecialist()->create(), 'event', '/api/workspace/event'],
            [User::factory()->printingSpecialist()->create(), 'printing', '/api/workspace/printing'],
        ];

        foreach ($cases as [$user, $workspace, $endpoint]) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();
            $token = $this->tokenFor($user);

            $this->withToken($token)
                ->getJson('/api/workspace')
                ->assertOk()
                ->assertJsonPath('data.workspace', $workspace);

            $this->withToken($token)
                ->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('data.workspace', $workspace);

            $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
            $this->withToken($token)->getJson('/api/admin/employees')->assertForbidden();
        }
    }

    public function test_role_specific_endpoints_reject_other_workspace_roles(): void
    {
        $developer = User::factory()->webDeveloper()->create();

        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/media-buyer')->assertForbidden();
        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/video-editor')->assertForbidden();
        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/account-manager')->assertForbidden();
        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/hr')->assertForbidden();
        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/marketing')->assertForbidden();
        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/event')->assertForbidden();
        $this->withToken($this->tokenFor($developer))->getJson('/api/workspace/printing')->assertForbidden();
    }

    public function test_media_buyer_does_not_receive_fake_campaign_metrics(): void
    {
        $user = User::factory()->mediaBuyer()->create();

        $domains = $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace')
            ->assertOk()
            ->json('data.domains');

        $this->assertFalse($domains['campaigns']['available']);
        $this->assertFalse($domains['budgets']['available']);
        $this->assertFalse($domains['reports']['available']);
        $this->assertTrue($domains['tasks']['available']);
        $this->assertArrayNotHasKey('count', $domains['campaigns']);
    }

    public function test_marketing_workspace_marks_campaigns_unavailable_without_fake_metrics(): void
    {
        $user = User::factory()->marketingSpecialist()->create();

        $payload = $this->withToken($this->tokenFor($user))
            ->getJson('/api/workspace/marketing')
            ->assertOk()
            ->json('data');

        $this->assertSame('marketing', $payload['workspace']);
        $this->assertFalse($payload['domains']['campaigns']['available']);
        $this->assertFalse($payload['domains']['content']['available']);
        $this->assertFalse($payload['domains']['reports']['available']);
        $this->assertTrue($payload['domains']['tasks']['available']);
        $this->assertArrayNotHasKey('count', $payload['domains']['campaigns']);
    }

    public function test_role_change_from_account_manager_revokes_task_creation(): void
    {
        $user = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $token = $this->tokenFor($user);

        $user->update(['role' => UserRole::EventSpecialist]);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'event');

        $this->withToken($token)
            ->postJson('/api/workspace/account-manager/tasks', [
                'title' => 'Should fail',
                'assigned_to' => $developer->id,
                'priority' => 'LOW',
            ])
            ->assertForbidden();
    }
}
