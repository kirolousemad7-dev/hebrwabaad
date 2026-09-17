<?php

namespace App\Jobs;

use App\Enums\GoogleCalendarSyncStatus;
use App\Models\Task;
use App\Services\GoogleCalendar\GoogleCalendarTaskSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTaskToGoogleCalendar implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $taskId,
        public readonly int $expectedVersion,
    ) {}

    public function uniqueId(): string
    {
        return 'google-task-sync:'.$this->taskId;
    }

    public function handle(GoogleCalendarTaskSyncService $sync): void
    {
        $task = Task::query()->find($this->taskId);
        if ($task === null || ! $task->google_sync_enabled) {
            return;
        }

        if ((int) $task->google_sync_version !== $this->expectedVersion) {
            return;
        }

        if ($task->google_sync_status === GoogleCalendarSyncStatus::Synced
            && (int) $task->google_sync_version === $this->expectedVersion
            && $task->google_event_id) {
            // Already synced this version — skip duplicate.
            return;
        }

        $sync->syncNow($task);
    }
}
