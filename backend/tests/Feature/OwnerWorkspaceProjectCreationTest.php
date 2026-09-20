<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\ProjectActivityAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerWorkspaceProjectCreationTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_list_customers_for_project_creation(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['name' => 'Visible Customer']);
        User::factory()->webDeveloper()->create(['name' => 'Not A Customer']);

        $items = $this->asUser($owner)
            ->getJson('/api/workspace/account-manager/customers')
            ->assertOk()
            ->json('data');

        $ids = collect($items)->pluck('id')->all();

        $this->assertContains($customer->id, $ids);
        $this->assertSame(
            [$customer->id],
            collect($items)->pluck('id')->all()
        );
    }

    public function test_owner_can_create_project_with_selected_account_manager(): void
    {
        $owner = User::factory()->owner()->create();
        $accountManager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        $payload = $this->asUser($owner)
            ->postJson('/api/workspace/projects', [
                'title' => 'Owner created project',
                'description' => 'Created by owner',
                'customer_id' => $customer->id,
                'account_manager_id' => $accountManager->id,
                'started_at' => now()->toDateString(),
                'deadline' => now()->addDays(21)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Owner created project')
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.account_manager_id', $accountManager->id)
            ->assertJsonPath('data.status', ProjectStatus::Planning->value)
            ->json('data');

        $this->assertDatabaseHas('projects', [
            'id' => $payload['id'],
            'account_manager_id' => $accountManager->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $payload['id'],
            'user_id' => $owner->id,
        ]);

        $this->assertSame(0, ProjectMember::query()->where('project_id', $payload['id'])->count());

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $payload['id'],
            'action' => ProjectActivityAction::PROJECT_CREATED,
            'actor_user_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $payload['id'],
            'action' => ProjectActivityAction::PROJECT_ACCOUNT_MANAGER_ASSIGNED,
            'actor_user_id' => $owner->id,
        ]);

        $this->asUser($owner)
            ->getJson('/api/workspace/projects/'.$payload['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $payload['id']);

        $this->asUser($owner)
            ->getJson('/api/operations/projects')
            ->assertOk()
            ->assertJsonFragment(['id' => $payload['id']]);
    }

    public function test_owner_must_provide_account_manager_id(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();

        $this->asUser($owner)
            ->postJson('/api/workspace/projects', [
                'title' => 'Missing AM',
                'customer_id' => $customer->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_manager_id']);

        $this->assertSame(0, Project::query()->count());
    }

    public function test_owner_cannot_assign_non_account_manager_users(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $developer = User::factory()->webDeveloper()->create();
        $inactiveAm = User::factory()->accountManager()->inactive()->create();

        foreach ([$owner, $developer, $customer, $inactiveAm] as $invalid) {
            $this->asUser($owner)
                ->postJson('/api/workspace/projects', [
                    'title' => 'Invalid AM assignment',
                    'customer_id' => $customer->id,
                    'account_manager_id' => $invalid->id,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['account_manager_id']);
        }

        $this->assertSame(0, Project::query()->count());
    }

    public function test_account_manager_creation_remains_compatible_without_account_manager_id(): void
    {
        $manager = User::factory()->accountManager()->create();
        $otherManager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();

        $this->asUser($manager)
            ->postJson('/api/workspace/projects', [
                'title' => 'AM project',
                'customer_id' => $customer->id,
                'account_manager_id' => $otherManager->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_manager_id']);

        $payload = $this->asUser($manager)
            ->postJson('/api/workspace/projects', [
                'title' => 'AM project',
                'description' => 'Managed by AM',
                'customer_id' => $customer->id,
                'started_at' => now()->toDateString(),
                'deadline' => now()->addDays(7)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.account_manager_id', $manager->id)
            ->json('data');

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $payload['id'],
            'action' => ProjectActivityAction::PROJECT_CREATED,
            'actor_user_id' => $manager->id,
        ]);
    }

    public function test_customer_and_team_roles_cannot_create_projects(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $developer = User::factory()->webDeveloper()->create();
        $accountManager = User::factory()->accountManager()->create();
        $projectCustomer = User::factory()->create();

        foreach ([$customer, $developer] as $user) {
            $this->asUser($user)
                ->postJson('/api/workspace/projects', [
                    'title' => 'Blocked',
                    'customer_id' => $projectCustomer->id,
                    'account_manager_id' => $accountManager->id,
                ])
                ->assertForbidden();
        }

        $this->assertSame(0, Project::query()->count());
    }
}
