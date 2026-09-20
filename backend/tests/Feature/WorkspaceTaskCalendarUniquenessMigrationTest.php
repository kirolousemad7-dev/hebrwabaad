<?php

namespace Tests\Feature;

use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkspaceTaskCalendarUniquenessMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_calendar_item_id_has_unique_index_after_migrations(): void
    {
        $indexes = collect(Schema::getIndexes('tasks'));

        $uniqueOnCalendar = $indexes->first(function (array $index): bool {
            return ($index['unique'] ?? false) === true
                && $index['columns'] === ['calendar_item_id'];
        });

        $this->assertNotNull(
            $uniqueOnCalendar,
            'Expected a unique index on tasks.calendar_item_id after migrations.'
        );
    }

    public function test_tasks_cannot_share_the_same_calendar_item_id(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
        ]);

        $calendar = CalendarItem::factory()->create([
            'created_by' => $manager->id,
            'related_type' => 'workspace_task',
            'related_id' => null,
        ]);

        $first = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'calendar_item_id' => $calendar->id,
        ]);

        $this->assertSame($calendar->id, (int) $first->calendar_item_id);

        $this->expectException(QueryException::class);

        Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'calendar_item_id' => $calendar->id,
        ]);
    }

    public function test_multiple_tasks_may_have_null_calendar_item_id(): void
    {
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
        ]);

        Task::factory()->count(2)->create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'calendar_item_id' => null,
        ]);

        $this->assertSame(2, Task::query()->whereNull('calendar_item_id')->count());
    }

    public function test_mysql_migration_drops_foreign_key_before_index(): void
    {
        $path = database_path('migrations/2026_09_20_010135_add_workspace_task_calendar_uniqueness.php');
        $source = file_get_contents($path);
        $this->assertNotFalse($source);

        $fkPos = strpos($source, "dropForeign(['calendar_item_id'])");
        $indexPos = strpos($source, "dropIndex(['calendar_item_id'])");
        $uniquePos = strpos($source, "unique('calendar_item_id')");

        $this->assertNotFalse($fkPos);
        $this->assertNotFalse($indexPos);
        $this->assertNotFalse($uniquePos);
        $this->assertLessThan($indexPos, $fkPos, 'Foreign key must be dropped before the non-unique index.');
        $this->assertLessThan($uniquePos, $indexPos, 'Unique index must be created after dropping the old index.');
        $this->assertStringContainsString('nullOnDelete()', $source);
    }
}
