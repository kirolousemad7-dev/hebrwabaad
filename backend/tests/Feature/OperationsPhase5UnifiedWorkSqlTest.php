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
use App\Services\Operations\Work\UnifiedWorkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase5UnifiedWorkSqlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.unified_work_driver' => 'sql']);
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_sql_driver_includes_task(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'SQL task row',
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

        $this->assertContains('task:'.$task->id, collect($items)->pluck('id')->all());
    }

    public function test_sql_driver_includes_calendar_task(): void
    {
        $employee = User::factory()->graphicDesigner()->create();

        $item = CalendarItem::factory()->create([
            'title' => 'SQL calendar row',
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

        $this->assertContains('calendar:'.$item->id, collect($items)->pluck('id')->all());
    }

    public function test_sql_driver_dedupes_linked_calendar(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $task = Task::factory()->create([
            'title' => 'Canonical linked task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'deadline' => now()->toDateString(),
        ]);

        $clone = CalendarItem::factory()->create([
            'title' => 'Linked calendar clone',
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

        $task->update(['calendar_item_id' => $clone->id]);

        $items = $this->asUser($developer)->getJson('/api/operations/work?scope=mine')
            ->assertOk()
            ->json('data.items');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains('task:'.$task->id, $ids);
        $this->assertNotContains('calendar:'.$clone->id, $ids);
    }

    public function test_sql_driver_pagination_meta(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        Task::factory()->count(5)->create([
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'deadline' => now()->toDateString(),
        ]);

        $meta = $this->asUser($developer)->getJson('/api/operations/work?page=1&per_page=2&scope=mine')
            ->assertOk()
            ->json('data.meta');

        $this->assertSame(1, $meta['current_page']);
        $this->assertSame(2, $meta['per_page']);
        $this->assertSame(5, $meta['total']);
        $this->assertSame(3, $meta['last_page']);
    }

    public function test_sql_driver_mine_scope_hides_others(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $designer = User::factory()->graphicDesigner()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $other = Task::factory()->create([
            'title' => 'Someone else',
            'assigned_to' => $designer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
        ]);

        $items = $this->asUser($developer)->getJson('/api/operations/work?scope=mine')
            ->assertOk()
            ->json('data.items');

        $this->assertNotContains('task:'.$other->id, collect($items)->pluck('id')->all());
    }

    public function test_php_and_sql_drivers_return_same_total_for_fixture(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        Task::factory()->count(3)->create([
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'status' => TaskStatus::Todo,
            'deadline' => now()->toDateString(),
        ]);

        $calendar = CalendarItem::factory()->create([
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $developer->id,
            'starts_at' => now()->setTime(14, 0),
            'ends_at' => now()->setTime(15, 0),
        ]);
        $calendar->assignees()->sync([$developer->id]);

        $linkedTask = Task::factory()->create([
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'deadline' => now()->addDay()->toDateString(),
        ]);
        $linkedCal = CalendarItem::factory()->create([
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $manager->id,
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
            'related_type' => 'workspace_task',
            'related_id' => $linkedTask->id,
        ]);
        $linkedCal->assignees()->sync([$developer->id]);
        $linkedTask->update(['calendar_item_id' => $linkedCal->id]);

        /** @var UnifiedWorkService $service */
        $service = $this->app->make(UnifiedWorkService::class);
        $filters = [
            'scope' => 'mine',
            'bucket' => 'all',
            'page' => 1,
            'per_page' => 50,
            'sort' => 'newest',
        ];

        config(['operations.unified_work_driver' => 'php']);
        $php = $service->list($developer, $filters);

        config(['operations.unified_work_driver' => 'sql']);
        $sql = $service->list($developer, $filters);

        $this->assertSame($php['meta']['total'], $sql['meta']['total']);
        $this->assertSame(
            collect($php['items'])->pluck('id')->sort()->values()->all(),
            collect($sql['items'])->pluck('id')->sort()->values()->all(),
        );
    }
}
