<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce one workspace Task → one CalendarItem soft-link.
 *
 * - tasks.calendar_item_id becomes unique (nullable)
 * - calendar_items gains a unique workspace_task link key
 *   (partial unique on SQLite/Postgres; generated column on MySQL)
 *
 * MySQL/MariaDB: drop the FK before replacing tasks_calendar_item_id_index
 * with a unique index, then recreate the FK (ON DELETE SET NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeWorkspaceTaskCalendarLinks();

        $driver = Schema::getConnection()->getDriverName();

        if ($this->isMysqlFamily($driver)) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropForeign(['calendar_item_id']);
                $table->dropIndex(['calendar_item_id']);
                $table->unique('calendar_item_id');
                $table->foreign('calendar_item_id')
                    ->references('id')
                    ->on('calendar_items')
                    ->nullOnDelete();
            });
        } else {
            // SQLite / PostgreSQL: index swap only (no MySQL FK/index coupling).
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropIndex(['calendar_item_id']);
                $table->unique('calendar_item_id');
            });
        }

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX calendar_items_workspace_task_unique ON calendar_items (related_id) WHERE related_type = 'workspace_task' AND deleted_at IS NULL"
            );

            return;
        }

        // MySQL / MariaDB: generated column so only workspace_task rows compete for uniqueness.
        Schema::table('calendar_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_task_link_id')
                ->nullable()
                ->storedAs("CASE WHEN `related_type` = 'workspace_task' AND `deleted_at` IS NULL THEN `related_id` ELSE NULL END")
                ->after('related_id');
            $table->unique('workspace_task_link_id', 'calendar_items_workspace_task_link_unique');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS calendar_items_workspace_task_unique');
        } else {
            Schema::table('calendar_items', function (Blueprint $table): void {
                $table->dropUnique('calendar_items_workspace_task_link_unique');
                $table->dropColumn('workspace_task_link_id');
            });
        }

        if ($this->isMysqlFamily($driver)) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropForeign(['calendar_item_id']);
                $table->dropUnique(['calendar_item_id']);
                $table->index('calendar_item_id');
                $table->foreign('calendar_item_id')
                    ->references('id')
                    ->on('calendar_items')
                    ->nullOnDelete();
            });

            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique(['calendar_item_id']);
            $table->index('calendar_item_id');
        });
    }

    private function isMysqlFamily(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    /**
     * Keep the oldest calendar row per workspace_task; soft-delete extras and repair FKs.
     */
    private function dedupeWorkspaceTaskCalendarLinks(): void
    {
        $duplicates = DB::table('calendar_items')
            ->select('related_id')
            ->where('related_type', 'workspace_task')
            ->whereNull('deleted_at')
            ->whereNotNull('related_id')
            ->groupBy('related_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('related_id');

        foreach ($duplicates as $taskId) {
            $ids = DB::table('calendar_items')
                ->where('related_type', 'workspace_task')
                ->where('related_id', $taskId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->pluck('id');

            $keepId = (int) $ids->shift();
            $extraIds = $ids->map(fn ($id) => (int) $id)->all();

            if ($extraIds === []) {
                continue;
            }

            DB::table('calendar_items')
                ->whereIn('id', $extraIds)
                ->update(['deleted_at' => now()]);

            DB::table('tasks')
                ->where('id', (int) $taskId)
                ->whereIn('calendar_item_id', $extraIds)
                ->update(['calendar_item_id' => $keepId]);
        }

        // Clear task FKs pointing at missing/soft-deleted calendar rows.
        $orphanTaskIds = DB::table('tasks')
            ->whereNotNull('calendar_item_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('calendar_items')
                    ->whereColumn('calendar_items.id', 'tasks.calendar_item_id')
                    ->whereNull('calendar_items.deleted_at');
            })
            ->pluck('id');

        if ($orphanTaskIds->isNotEmpty()) {
            DB::table('tasks')
                ->whereIn('id', $orphanTaskIds->all())
                ->update(['calendar_item_id' => null]);
        }
    }
};
