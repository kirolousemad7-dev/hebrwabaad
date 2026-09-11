<?php

namespace Tests\Feature;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase4UnifiedWorkTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_task_appears_in_unified_work_list(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'Unified task item',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::High,
            'deadline' => now()->toDateString(),
        ]);

        $items = $this->asUser($developer)->getJson('/api/operations/work?scope=mine')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains('task:'.$task->id, $ids);

        $row = collect($items)->firstWhere('id', 'task:'.$task->id);
        $this->assertSame('task', $row['source_type']);
        $this->assertSame('Unified task item', $row['title']);
        $this->assertSame('HIGH', $row['priority']);
    }

    public function test_calendar_task_appears_in_unified_work_list(): void
    {
        $employee = User::factory()->graphicDesigner()->create();

        $item = CalendarItem::factory()->create([
            'title' => 'Calendar work task',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $employee->id,
            'starts_at' => now()->setTime(11, 0),
            'ends_at' => now()->setTime(12, 0),
        ]);
        $item->assignees()->sync([$employee->id]);

        $items = $this->asUser($employee)->getJson('/api/operations/work?scope=mine&source=calendar')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains('calendar:'.$item->id, $ids);
    }

    public function test_dedupes_calendar_clone_linked_to_workspace_task(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'Canonical task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'deadline' => now()->toDateString(),
        ]);

        $clone = CalendarItem::factory()->create([
            'title' => 'Calendar clone',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $manager->id,
            'starts_at' => now()->setTime(9, 0),
            'ends_at' => now()->setTime(10, 0),
            'related_type' => 'workspace_task',
            'related_id' => $task->id,
            'related_label' => $task->title,
        ]);
        $clone->assignees()->sync([$developer->id]);

        $items = $this->asUser($developer)->getJson('/api/operations/work?scope=mine')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains('task:'.$task->id, $ids);
        $this->assertNotContains('calendar:'.$clone->id, $ids);
    }

    public function test_complete_task_adapter(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
        ]);

        $this->asUser($developer)
            ->postJson('/api/operations/work/task:'.$task->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.is_completed', true);

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }

    public function test_complete_calendar_adapter(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        $item = CalendarItem::factory()->create([
            'title' => 'Finish me',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $employee->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);
        $item->assignees()->sync([$employee->id]);

        $this->asUser($employee)
            ->postJson('/api/operations/work/calendar:'.$item->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(CalendarItemStatus::Completed, $item->fresh()->status);
    }

    public function test_mine_filter_hides_other_users_task(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $designer = User::factory()->graphicDesigner()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $other = Task::factory()->create([
            'title' => 'Not yours',
            'assigned_to' => $designer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
        ]);

        $items = $this->asUser($developer)->getJson('/api/operations/work?scope=mine')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertNotContains('task:'.$other->id, $ids);
    }

    public function test_pagination_meta_present(): void
    {
        $employee = User::factory()->webDeveloper()->create();

        $response = $this->asUser($employee)->getJson('/api/operations/work?page=1&per_page=10')
            ->assertOk()
            ->json('data.meta');

        $this->assertSame(1, $response['current_page']);
        $this->assertSame(10, $response['per_page']);
        $this->assertArrayHasKey('total', $response);
        $this->assertArrayHasKey('last_page', $response);
    }

    public function test_focus_endpoint_returns_items(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        Task::factory()->create([
            'title' => 'Urgent overdue',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'priority' => TaskPriority::Urgent,
            'deadline' => now()->subDay()->toDateString(),
            'status' => TaskStatus::Todo,
        ]);

        $this->asUser($developer)->getJson('/api/operations/work/focus')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items']]);

        $items = $this->asUser($developer)->getJson('/api/operations/work/focus')->json('data.items');
        $this->assertNotEmpty($items);
        $this->assertSame('Urgent overdue', $items[0]['title']);
    }

    public function test_my_day_includes_task_and_calendar_sources(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'My day task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'deadline' => now()->toDateString(),
            'status' => TaskStatus::Todo,
        ]);

        $calendar = CalendarItem::factory()->create([
            'title' => 'My day calendar task',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $developer->id,
            'starts_at' => now()->setTime(14, 0),
            'ends_at' => now()->setTime(15, 0),
        ]);
        $calendar->assignees()->sync([$developer->id]);

        $payload = $this->asUser($developer)->getJson('/api/operations/my-day')
            ->assertOk()
            ->json('data');

        $workIds = collect($payload['work_items'] ?? [])->pluck('id')->all();
        $this->assertContains('task:'.$task->id, $workIds);
        $this->assertContains('calendar:'.$calendar->id, $workIds);
    }
}
