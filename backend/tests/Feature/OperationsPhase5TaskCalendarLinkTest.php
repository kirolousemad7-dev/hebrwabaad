<?php

namespace Tests\Feature;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\CalendarItem;
use App\Models\CalendarItemActivity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Operations\TaskCalendarSyncContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase5TaskCalendarLinkTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_link_from_task_creates_calendar_and_bidirectional_link(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'Soft-linked task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'priority' => TaskPriority::High,
            'status' => TaskStatus::Todo,
        ]);

        $startsAt = now()->addDay()->setTime(10, 0)->toIso8601String();
        $endsAt = now()->addDay()->setTime(11, 0)->toIso8601String();

        $response = $this->asUser($manager)
            ->postJson('/api/operations/work/task/'.$task->id.'/link-calendar', [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => false,
                'reminders' => ['MINUTES_15'],
            ])
            ->assertCreated();

        $calendarId = (int) $response->json('data.calendar_item.id');
        $this->assertGreaterThan(0, $calendarId);

        $task->refresh();
        $item = CalendarItem::query()->findOrFail($calendarId);

        $this->assertSame($calendarId, (int) $task->calendar_item_id);
        $this->assertSame(CalendarItemType::Task, $item->type);
        $this->assertSame('workspace_task', $item->related_type);
        $this->assertSame($task->id, (int) $item->related_id);
        $this->assertSame('Soft-linked task', $item->title);
        $this->assertTrue($item->assignees()->where('users.id', $developer->id)->exists());
        $this->assertSame(1, $item->reminders()->count());
    }

    public function test_complete_task_completes_linked_calendar(): void
    {
        [$manager, $developer, $task, $item] = $this->linkedPair();

        $this->asUser($developer)
            ->postJson('/api/operations/work/task:'.$task->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
        $this->assertSame(CalendarItemStatus::Completed, $item->fresh()->status);
        $this->assertNotNull($item->fresh()->completed_at);
    }

    public function test_complete_calendar_completes_linked_task(): void
    {
        [$manager, $developer, $task, $item] = $this->linkedPair();

        $this->asUser($developer)
            ->postJson('/api/operations/work/calendar:'.$item->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(CalendarItemStatus::Completed, $item->fresh()->status);
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }

    public function test_completion_sync_does_not_loop_infinitely(): void
    {
        [$manager, $developer, $task, $item] = $this->linkedPair();

        TaskCalendarSyncContext::clear();

        $this->asUser($developer)
            ->postJson('/api/operations/work/task:'.$task->id.'/complete')
            ->assertOk();

        $this->assertSame(0, TaskCalendarSyncContext::depth());
        $this->assertFalse(TaskCalendarSyncContext::isSyncing());

        $completedLogs = CalendarItemActivity::query()
            ->where('calendar_item_id', $item->id)
            ->where('action', 'completed')
            ->count();

        $this->assertSame(1, $completedLogs);
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
        $this->assertSame(CalendarItemStatus::Completed, $item->fresh()->status);
    }

    public function test_unlink_clears_both_sides_without_deleting_records(): void
    {
        [$manager, $developer, $task, $item] = $this->linkedPair();

        $this->asUser($manager)
            ->deleteJson('/api/operations/work/links/'.$task->id)
            ->assertOk()
            ->assertJsonPath('data.unlinked', true);

        $this->assertNull($task->fresh()->calendar_item_id);
        $this->assertNotNull(CalendarItem::query()->find($item->id));
        $this->assertNotNull(Task::query()->find($task->id));

        $item->refresh();
        $this->assertNull($item->related_type);
        $this->assertNull($item->related_id);
    }

    public function test_unauthorized_user_cannot_link_calendar_from_task(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $outsider = User::factory()->graphicDesigner()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
        ]);

        $this->asUser($outsider)
            ->postJson('/api/operations/work/task/'.$task->id.'/link-calendar', [
                'starts_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertForbidden();

        $this->assertNull($task->fresh()->calendar_item_id);
        $this->assertSame(0, CalendarItem::query()->count());
    }

    public function test_unified_work_list_shows_one_item_for_linked_pair(): void
    {
        [$manager, $developer, $task, $item] = $this->linkedPair();

        $items = $this->asUser($developer)->getJson('/api/operations/work?scope=mine')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains('task:'.$task->id, $ids);
        $this->assertNotContains('calendar:'.$item->id, $ids);
        $this->assertSame(1, collect($ids)->filter(
            fn ($id) => $id === 'task:'.$task->id || $id === 'calendar:'.$item->id
        )->count());
    }

    public function test_link_from_calendar_creates_task_with_bidirectional_link(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $item = CalendarItem::factory()->create([
            'title' => 'Calendar origin task',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $manager->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(15, 0),
            'related_type' => 'project',
            'related_id' => $project->id,
        ]);
        $item->assignees()->sync([$developer->id]);

        $response = $this->asUser($manager)
            ->postJson('/api/operations/work/calendar/'.$item->id.'/link-task', [
                'project_id' => $project->id,
            ])
            ->assertCreated();

        $taskId = (int) $response->json('data.task.id');
        $task = Task::query()->findOrFail($taskId);

        $this->assertSame($item->id, (int) $task->calendar_item_id);
        $this->assertSame($project->id, (int) $task->project_id);
        $this->assertSame($developer->id, (int) $task->assigned_to);
        $this->assertSame('workspace_task', $item->fresh()->related_type);
        $this->assertSame($task->id, (int) $item->fresh()->related_id);
    }

    /**
     * @return array{0: User, 1: User, 2: Task, 3: CalendarItem}
     */
    private function linkedPair(): array
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'Linked pair task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'status' => TaskStatus::InProgress,
            'priority' => TaskPriority::Medium,
        ]);

        $this->asUser($manager)
            ->postJson('/api/operations/work/task/'.$task->id.'/link-calendar', [
                'starts_at' => now()->addHours(2)->toIso8601String(),
                'ends_at' => now()->addHours(3)->toIso8601String(),
            ])
            ->assertCreated();

        $task->refresh();
        $item = CalendarItem::query()->findOrFail((int) $task->calendar_item_id);

        return [$manager, $developer, $task, $item];
    }
}
