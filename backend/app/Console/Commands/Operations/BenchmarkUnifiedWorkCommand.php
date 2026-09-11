<?php

namespace App\Console\Commands\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Operations\Work\UnifiedWorkService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('operations:benchmark-unified-work {--seed=1000 : Temporary rows to create (rolled back)}')]
#[Description('Benchmark Unified Work list drivers (sql vs php) inside a rolled-back transaction')]
class BenchmarkUnifiedWorkCommand extends Command
{
    public function handle(UnifiedWorkService $unifiedWork): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $seed = max(0, (int) $this->option('seed'));

        DB::beginTransaction();

        try {
            $manager = User::factory()->accountManager()->create();
            $employee = User::factory()->webDeveloper()->create();
            $project = Project::factory()->create(['account_manager_id' => $manager->id]);

            if ($seed > 0) {
                $this->seedFixture($manager, $employee, $project, $seed);
            }

            $filters = [
                'scope' => 'mine',
                'bucket' => 'all',
                'page' => 1,
                'per_page' => 20,
                'sort' => 'overdue_first',
            ];

            $this->line(sprintf('Seeded ~%d tasks + ~%d calendar tasks (transaction will roll back).', $seed, $seed));
            $this->line('Note: Phase 6K recurrence merge applies to bucket=today|this_week|upcoming only.');
            $this->newLine();

            foreach (['sql', 'php'] as $driver) {
                config(['operations.unified_work_driver' => $driver]);
                $this->benchmarkDriver($unifiedWork, $employee, $filters, $driver);
                $this->benchmarkDriver(
                    $unifiedWork,
                    $employee,
                    array_merge($filters, ['bucket' => 'today']),
                    $driver.' bucket=today',
                );
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            DB::rollBack();
            $this->info('Transaction rolled back — no permanent seed data.');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function benchmarkDriver(UnifiedWorkService $unifiedWork, User $actor, array $filters, string $driver): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $started = hrtime(true);
        $result = $unifiedWork->list($actor, $filters);
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->info(sprintf(
            '[%s] page=1 total=%d items=%d  %.2f ms  queries=%d',
            $driver,
            $result['meta']['total'] ?? 0,
            count($result['items']),
            $elapsedMs,
            $queryCount,
        ));
    }

    private function seedFixture(User $manager, User $employee, Project $project, int $seed): void
    {
        $half = (int) ceil($seed / 2);

        Task::factory()
            ->count($half)
            ->create([
                'assigned_to' => $employee->id,
                'created_by' => $manager->id,
                'project_id' => $project->id,
                'status' => TaskStatus::Todo,
                'priority' => TaskPriority::Medium,
                'deadline' => now()->toDateString(),
            ]);

        Task::factory()
            ->count($seed - $half)
            ->create([
                'assigned_to' => $employee->id,
                'created_by' => $manager->id,
                'project_id' => $project->id,
                'status' => TaskStatus::InProgress,
                'priority' => TaskPriority::High,
                'deadline' => now()->addDays(3)->toDateString(),
            ]);

        $calendars = CalendarItem::factory()
            ->count($seed)
            ->create([
                'type' => CalendarItemType::Task,
                'status' => CalendarItemStatus::Scheduled,
                'created_by' => $employee->id,
                'starts_at' => now()->setTime(10, 0),
                'ends_at' => now()->setTime(11, 0),
            ]);

        foreach ($calendars as $item) {
            $item->assignees()->sync([$employee->id]);
        }
    }
}
