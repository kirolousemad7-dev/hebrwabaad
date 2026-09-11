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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase6UnifiedWorkRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.unified_work_driver' => 'sql']);
        Carbon::setTestNow(Carbon::parse('2026-09-08 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recurring_master_appears_as_occurrence_in_today_window(): void
    {
        $employee = User::factory()->webDeveloper()->create();

        $master = CalendarItem::factory()->create([
            'title' => 'Daily standup',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $employee->id,
            'starts_at' => Carbon::parse('2026-09-01 09:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-01 09:30:00', 'UTC'),
            'recurrence_rule' => 'FREQ=DAILY',
        ]);
        $master->assignees()->sync([$employee->id]);

        $service = app(UnifiedWorkService::class);
        $result = $service->list($employee, [
            'scope' => 'mine',
            'bucket' => 'today',
            'source' => 'calendar',
            'page' => 1,
            'per_page' => 50,
        ]);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertContains('calendar:'.$master->id.':2026-09-08', $ids);
        $this->assertNotContains('calendar:'.$master->id, $ids);
    }

    public function test_recurring_master_appears_in_upcoming_window(): void
    {
        $employee = User::factory()->graphicDesigner()->create();

        $master = CalendarItem::factory()->create([
            'title' => 'Weekly review',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $employee->id,
            'starts_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-01 11:00:00', 'UTC'),
            'recurrence_rule' => 'FREQ=DAILY',
        ]);
        $master->assignees()->sync([$employee->id]);

        $service = app(UnifiedWorkService::class);
        $result = $service->list($employee, [
            'scope' => 'mine',
            'bucket' => 'upcoming',
            'source' => 'calendar',
            'page' => 1,
            'per_page' => 50,
        ]);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertContains('calendar:'.$master->id.':2026-09-09', $ids);
        $this->assertNotContains('calendar:'.$master->id.':2026-09-08', $ids);
    }

    public function test_linked_task_dedupes_recurring_occurrences(): void
    {
        $manager = User::factory()->accountManager()->create();
        $developer = User::factory()->webDeveloper()->create();
        $project = Project::factory()->create(['account_manager_id' => $manager->id]);

        $master = CalendarItem::factory()->create([
            'title' => 'Linked recurring',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $manager->id,
            'starts_at' => Carbon::parse('2026-09-01 09:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
            'recurrence_rule' => 'FREQ=DAILY',
        ]);
        $master->assignees()->sync([$developer->id]);

        $task = Task::factory()->create([
            'title' => 'Canonical linked task',
            'assigned_to' => $developer->id,
            'created_by' => $manager->id,
            'project_id' => $project->id,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'deadline' => '2026-09-08',
            'calendar_item_id' => $master->id,
        ]);

        $service = app(UnifiedWorkService::class);
        $result = $service->list($developer, [
            'scope' => 'mine',
            'bucket' => 'today',
            'page' => 1,
            'per_page' => 50,
        ]);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertContains('task:'.$task->id, $ids);
        $this->assertNotContains('calendar:'.$master->id.':2026-09-08', $ids);
        $this->assertNotContains('calendar:'.$master->id, $ids);
    }

    public function test_unconstrained_all_bucket_does_not_expand_occurrences(): void
    {
        $employee = User::factory()->webDeveloper()->create();

        $master = CalendarItem::factory()->create([
            'title' => 'Daily no expand',
            'type' => CalendarItemType::Task,
            'status' => CalendarItemStatus::Scheduled,
            'created_by' => $employee->id,
            'starts_at' => Carbon::parse('2026-09-01 09:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-01 09:30:00', 'UTC'),
            'recurrence_rule' => 'FREQ=DAILY',
        ]);
        $master->assignees()->sync([$employee->id]);

        $service = app(UnifiedWorkService::class);
        $result = $service->list($employee, [
            'scope' => 'mine',
            'bucket' => 'all',
            'source' => 'calendar',
            'page' => 1,
            'per_page' => 50,
        ]);

        $ids = collect($result['items'])->pluck('id')->all();
        $this->assertContains('calendar:'.$master->id, $ids);
        $this->assertFalse(
            collect($ids)->contains(fn (string $id): bool => str_starts_with($id, 'calendar:'.$master->id.':')),
        );
    }
}
