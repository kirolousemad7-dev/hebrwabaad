<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_account_manager_creates_and_updates_project_for_real_customer(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['name' => 'Client Co']);
        $token = $this->tokenFor($manager);

        $payload = $this->withToken($token)
            ->postJson('/api/workspace/projects', [
                'title' => 'Campaign site',
                'description' => 'Website rebuild',
                'customer_id' => $customer->id,
                'started_at' => now()->toDateString(),
                'deadline' => now()->addDays(14)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Campaign site')
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.account_manager_id', $manager->id)
            ->assertJsonPath('data.status', ProjectStatus::Planning->value)
            ->assertJsonPath('data.progress.total', 0)
            ->assertJsonPath('data.progress.percent', 0)
            ->json('data');

        $this->withToken($token)
            ->putJson('/api/workspace/projects/'.$payload['id'], [
                'title' => 'Campaign site v2',
                'description' => 'Website rebuild',
                'customer_id' => $customer->id,
                'status' => ProjectStatus::InProgress->value,
                'started_at' => now()->toDateString(),
                'deadline' => now()->addDays(10)->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Campaign site v2')
            ->assertJsonPath('data.status', ProjectStatus::InProgress->value);
    }

    public function test_account_manager_cannot_attach_owner_employee_or_inactive_customer(): void
    {
        $manager = User::factory()->accountManager()->create();
        $token = $this->tokenFor($manager);
        $owner = User::factory()->owner()->create();
        $developer = User::factory()->webDeveloper()->create();
        $inactive = User::factory()->inactive()->create();

        foreach ([$owner, $developer, $inactive] as $target) {
            $this->withToken($token)
                ->postJson('/api/workspace/projects', [
                    'title' => 'Invalid customer',
                    'customer_id' => $target->id,
                ])
                ->assertUnprocessable();
        }

        $this->assertSame(0, Project::query()->count());
    }

    public function test_account_manager_sees_only_managed_projects_and_progress_is_real(): void
    {
        $manager = User::factory()->accountManager()->create();
        $otherManager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $developer = User::factory()->webDeveloper()->create();
        $own = Project::factory()->create([
            'title' => 'Owned project',
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
        ]);
        Project::factory()->create([
            'title' => 'Other project',
            'account_manager_id' => $otherManager->id,
            'customer_id' => $customer->id,
        ]);

        Task::factory()->create([
            'project_id' => $own->id,
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'status' => TaskStatus::Completed,
        ]);
        Task::factory()->create([
            'project_id' => $own->id,
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'status' => TaskStatus::InProgress,
        ]);
        Task::factory()->create([
            'project_id' => $own->id,
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'status' => TaskStatus::Todo,
        ]);

        $items = $this->withToken($this->tokenFor($manager))
            ->getJson('/api/workspace/projects')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame($own->id, $items[0]['id']);
        $this->assertSame(3, $items[0]['progress']['total']);
        $this->assertSame(1, $items[0]['progress']['completed']);
        $this->assertSame(1, $items[0]['progress']['in_progress']);
        $this->assertSame(1, $items[0]['progress']['todo']);
        $this->assertSame(33.3, $items[0]['progress']['percent']);
    }

    public function test_account_manager_assigns_task_to_employee_on_project(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create()->id,
        ]);
        $token = $this->tokenFor($manager);

        $task = $this->withToken($token)
            ->postJson('/api/workspace/account-manager/tasks', [
                'title' => 'Build homepage',
                'project_id' => $project->id,
                'assigned_to' => $developer->id,
                'priority' => TaskPriority::High->value,
                'deadline' => now()->addDays(3)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->json('data');

        $this->withToken($token)
            ->putJson('/api/workspace/account-manager/tasks/'.$task['id'], [
                'title' => 'Build homepage',
                'description' => 'Updated copy',
                'project_id' => $project->id,
                'assigned_to' => $developer->id,
                'priority' => TaskPriority::Urgent->value,
                'status' => TaskStatus::Review->value,
                'deadline' => now()->addDays(4)->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', TaskPriority::Urgent->value)
            ->assertJsonPath('data.status', TaskStatus::Review->value);
    }

    public function test_employee_sees_only_assigned_project_and_own_tasks(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developerA = User::factory()->webDeveloper()->create();
        $developerB = User::factory()->graphicDesigner()->create();
        $projectA = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create()->id,
        ]);
        $projectB = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create()->id,
        ]);
        $taskA = Task::factory()->create([
            'title' => 'Task A',
            'project_id' => $projectA->id,
            'assigned_to' => $developerA->id,
            'created_by' => $manager->id,
        ]);
        $taskB = Task::factory()->create([
            'title' => 'Task B',
            'project_id' => $projectB->id,
            'assigned_to' => $developerB->id,
            'created_by' => $manager->id,
        ]);

        $tokenA = $this->tokenFor($developerA);

        $projects = $this->withToken($tokenA)
            ->getJson('/api/workspace/projects')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $projects);
        $this->assertSame($projectA->id, $projects[0]['id']);

        $this->withToken($tokenA)->getJson('/api/workspace/projects/'.$projectA->id)->assertOk();
        $this->withToken($tokenA)->getJson('/api/workspace/projects/'.$projectB->id)->assertForbidden();
        $this->withToken($tokenA)->getJson('/api/workspace/tasks/'.$taskA->id)->assertOk();
        $this->withToken($tokenA)->getJson('/api/workspace/tasks/'.$taskB->id)->assertForbidden();

        $projectTasks = $this->withToken($tokenA)
            ->getJson('/api/workspace/projects/'.$projectA->id.'/tasks')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $projectTasks);
        $this->assertSame($taskA->id, $projectTasks[0]['id']);

        $this->withToken($tokenA)
            ->patchJson('/api/workspace/tasks/'.$taskA->id.'/status', [
                'status' => TaskStatus::Revision->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Revision->value);

        $this->withToken($tokenA)
            ->putJson('/api/workspace/account-manager/tasks/'.$taskA->id, [
                'title' => 'Hijack',
                'project_id' => $projectA->id,
                'assigned_to' => $developerB->id,
                'priority' => TaskPriority::Low->value,
                'status' => TaskStatus::Todo->value,
            ])
            ->assertForbidden();

        $this->assertSame($developerA->id, $taskA->fresh()->assigned_to);
    }

    public function test_hr_customer_admin_and_inactive_users_are_denied(): void
    {
        $manager = User::factory()->accountManager()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create()->id,
        ]);
        $developer = User::factory()->webDeveloper()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
        ]);

        $hr = User::factory()->hr()->create();
        $customer = User::factory()->create();
        $admin = User::factory()->adminManager()->create();
        $inactive = User::factory()->webDeveloper()->inactive()->create();

        $this->withToken($this->tokenFor($hr))->getJson('/api/workspace/projects')->assertForbidden();
        $this->withToken($this->tokenFor($hr))->postJson('/api/workspace/projects', [
            'title' => 'HR project',
            'customer_id' => $customer->id,
        ])->assertForbidden();
        $this->withToken($this->tokenFor($hr))->postJson('/api/workspace/account-manager/tasks', [
            'title' => 'HR task',
            'project_id' => $project->id,
            'assigned_to' => $developer->id,
            'priority' => TaskPriority::Low->value,
        ])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($customer))->getJson('/api/workspace/projects')->assertForbidden();
        $this->withToken($this->tokenFor($customer))->getJson('/api/workspace/tasks/'.$task->id)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($admin))->getJson('/api/workspace/projects')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($this->tokenFor($inactive))
            ->getJson('/api/workspace/projects')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
        $this->withToken($this->tokenFor($inactive))
            ->getJson('/api/workspace/tasks')
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_owner_can_view_projects_but_cannot_create_them(): void
    {
        $manager = User::factory()->accountManager()->create();
        $project = Project::factory()->create([
            'title' => 'Oversight project',
            'account_manager_id' => $manager->id,
            'customer_id' => User::factory()->create()->id,
        ]);
        $owner = User::factory()->owner()->create();
        $token = $this->tokenFor($owner);

        $this->withToken($token)
            ->getJson('/api/workspace/projects')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $project->id);

        $this->withToken($token)->getJson('/api/workspace/projects/'.$project->id)->assertOk();
        $this->withToken($token)->postJson('/api/workspace/projects', [
            'title' => 'Owner project',
            'customer_id' => User::factory()->create()->id,
        ])->assertForbidden();
    }

    public function test_guest_cannot_access_project_apis(): void
    {
        $this->getJson('/api/workspace/projects')->assertUnauthorized();
        $this->postJson('/api/workspace/projects', [])->assertUnauthorized();
    }

    public function test_customers_list_excludes_staff_and_inactive_accounts(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['name' => 'Active Client']);
        User::factory()->inactive()->create(['name' => 'Inactive Client']);
        User::factory()->webDeveloper()->create(['name' => 'Staff User']);

        $items = $this->withToken($this->tokenFor($manager))
            ->getJson('/api/workspace/account-manager/customers')
            ->assertOk()
            ->json('data');

        $names = array_column($items, 'name');
        $this->assertContains('Active Client', $names);
        $this->assertNotContains('Inactive Client', $names);
        $this->assertNotContains('Staff User', $names);
        $this->assertSame($customer->id, $items[0]['id']);
        $this->assertArrayNotHasKey('password', $items[0]);
    }
}
