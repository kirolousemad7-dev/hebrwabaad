<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    public function test_guest_cannot_access_tasks(): void
    {
        $this->getJson('/api/workspace/tasks')->assertUnauthorized();
        $this->postJson('/api/workspace/account-manager/tasks', [])->assertUnauthorized();
    }

    public function test_customer_cannot_access_task_apis(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/workspace/tasks')->assertForbidden();
        $this->withToken($token)->postJson('/api/workspace/account-manager/tasks', [])->assertForbidden();
    }

    public function test_account_manager_creates_and_assigns_task(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
        ]);
        $token = $this->tokenFor($manager);

        $payload = $this->withToken($token)
            ->postJson('/api/workspace/account-manager/tasks', [
                'title' => 'Implement landing page',
                'description' => 'Build the campaign landing page.',
                'project_id' => $project->id,
                'assigned_to' => $developer->id,
                'priority' => TaskPriority::High->value,
                'deadline' => now()->addDays(5)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Implement landing page')
            ->assertJsonPath('data.assigned_to', $developer->id)
            ->assertJsonPath('data.created_by', $manager->id)
            ->assertJsonPath('data.status', TaskStatus::Todo->value)
            ->assertJsonPath('data.project_id', $project->id)
            ->json('data');

        $this->assertDatabaseHas('tasks', [
            'id' => $payload['id'],
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
        ]);

        $list = $this->withToken($token)
            ->getJson('/api/workspace/account-manager/tasks')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $list['summary']['total']);
        $this->assertSame(0, $list['summary']['in_progress']);
        $this->assertSame(0, $list['summary']['completed']);
        $this->assertSame(0, $list['summary']['overdue']);
        $this->assertCount(1, $list['items']);
    }

    public function test_account_manager_cannot_assign_owner_customer_or_inactive_employee(): void
    {
        $manager = User::factory()->accountManager()->create();
        $token = $this->tokenFor($manager);
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $inactive = User::factory()->webDeveloper()->inactive()->create();
        $hr = User::factory()->hr()->create();
        $admin = User::factory()->adminManager()->create();

        foreach ([$owner, $customer, $inactive, $hr, $manager, $admin] as $target) {
            $this->withToken($token)
                ->postJson('/api/workspace/account-manager/tasks', [
                    'title' => 'Invalid assignment',
                    'assigned_to' => $target->id,
                    'priority' => TaskPriority::Low->value,
                ])
                ->assertUnprocessable();
        }

        $this->assertSame(0, Task::query()->count());
    }

    public function test_employee_sees_only_assigned_tasks_and_can_update_status(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $designer = User::factory()->graphicDesigner()->create();

        $own = Task::factory()->create([
            'title' => 'Developer task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
        ]);
        $other = Task::factory()->create([
            'title' => 'Designer task',
            'assigned_to' => $designer->id,
            'created_by' => $manager->id,
        ]);

        $devToken = $this->tokenFor($developer);
        $items = $this->withToken($devToken)
            ->getJson('/api/workspace/tasks')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame($own->id, $items[0]['id']);
        $this->assertSame('Developer task', $items[0]['title']);

        $this->withToken($devToken)
            ->patchJson('/api/workspace/tasks/'.$own->id.'/status', [
                'status' => TaskStatus::InProgress->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::InProgress->value);

        $this->withToken($devToken)
            ->patchJson('/api/workspace/tasks/'.$other->id.'/status', [
                'status' => TaskStatus::Completed->value,
            ])
            ->assertForbidden();

        $this->assertSame(TaskStatus::Todo, $other->fresh()->status);
    }

    public function test_employee_cannot_create_or_reassign_tasks(): void
    {
        $developer = User::factory()->webDeveloper()->create();
        $other = User::factory()->graphicDesigner()->create();

        $this->withToken($this->tokenFor($developer))
            ->postJson('/api/workspace/account-manager/tasks', [
                'title' => 'Should fail',
                'assigned_to' => $other->id,
                'priority' => TaskPriority::Medium->value,
            ])
            ->assertForbidden();
    }

    public function test_account_manager_cannot_access_owner_employee_management(): void
    {
        $manager = User::factory()->accountManager()->create();
        $token = $this->tokenFor($manager);

        $this->withToken($token)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/workspace/hr/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/workspace/developer')->assertForbidden();
    }

    public function test_assignee_list_only_includes_active_task_receivers(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create(['name' => 'Active Dev']);
        User::factory()->webDeveloper()->inactive()->create(['name' => 'Inactive Dev']);
        User::factory()->owner()->create(['name' => 'Owner User']);
        User::factory()->create(['name' => 'Customer User']);
        User::factory()->hr()->create(['name' => 'HR User']);
        User::factory()->adminManager()->create(['name' => 'Admin User']);

        $items = $this->withToken($this->tokenFor($manager))
            ->getJson('/api/workspace/account-manager/assignees')
            ->assertOk()
            ->json('data');

        $names = array_column($items, 'name');
        $this->assertContains('Active Dev', $names);
        $this->assertNotContains('Inactive Dev', $names);
        $this->assertNotContains('Owner User', $names);
        $this->assertNotContains('Customer User', $names);
        $this->assertNotContains('HR User', $names);
        $this->assertNotContains('Admin User', $names);
        $this->assertSame($developer->id, $items[0]['id']);
        $this->assertArrayNotHasKey('password', $items[0]);
    }

    public function test_inactive_account_manager_cannot_create_tasks(): void
    {
        $manager = User::factory()->accountManager()->inactive()->create();
        $developer = User::factory()->webDeveloper()->create();

        $this->withToken($this->tokenFor($manager))
            ->postJson('/api/workspace/account-manager/tasks', [
                'title' => 'Blocked',
                'assigned_to' => $developer->id,
                'priority' => TaskPriority::Low->value,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Account deactivated.');
    }

    public function test_overdue_summary_uses_real_deadlines(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();

        Task::factory()->create([
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'status' => TaskStatus::Todo,
            'deadline' => now()->subDay()->toDateString(),
        ]);

        $this->withToken($this->tokenFor($manager))
            ->getJson('/api/workspace/account-manager/tasks')
            ->assertOk()
            ->assertJsonPath('data.summary.overdue', 1)
            ->assertJsonPath('data.summary.total', 1);
    }

    public function test_account_manager_can_filter_tasks_on_the_server(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $designer = User::factory()->graphicDesigner()->create();

        Task::factory()->create([
            'title' => 'Landing page',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'priority' => TaskPriority::High,
            'status' => TaskStatus::Todo,
        ]);
        Task::factory()->create([
            'title' => 'Banner design',
            'assigned_to' => $designer->id,
            'created_by' => $manager->id,
            'priority' => TaskPriority::Low,
            'status' => TaskStatus::InProgress,
        ]);

        $token = $this->tokenFor($manager);

        $this->withToken($token)
            ->getJson('/api/workspace/account-manager/tasks?q=Banner')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Banner design')
            ->assertJsonPath('data.meta.total', 1);

        $this->withToken($token)
            ->getJson('/api/workspace/account-manager/tasks?status='.TaskStatus::Todo->value)
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Landing page')
            ->assertJsonPath('data.meta.total', 1);

        $this->withToken($token)
            ->getJson('/api/workspace/account-manager/tasks?priority='.TaskPriority::Low->value.'&assigned_to='.$designer->id)
            ->assertOk()
            ->assertJsonPath('data.items.0.assigned_to', $designer->id)
            ->assertJsonPath('data.meta.total', 1);
    }
}
