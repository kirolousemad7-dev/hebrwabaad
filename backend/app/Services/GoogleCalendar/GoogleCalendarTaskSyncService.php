<?php

namespace App\Services\GoogleCalendar;

use App\Enums\GoogleCalendarSyncStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Jobs\SyncTaskToGoogleCalendar;
use App\Models\Task;
use App\Models\User;
use App\Support\GoogleCalendarSyncContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleCalendarTaskSyncService
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauth,
        private readonly GoogleCalendarApiClient $api,
    ) {}

    public function enableAndSync(User $actor, Task $task): Task
    {
        $this->assertCanManageTask($actor, $task);
        $connection = $this->oauth->connectionFor($actor);
        if ($connection === null || ! $connection->sync_enabled) {
            throw ValidationException::withMessages([
                'google' => ['Connect Google Calendar before syncing tasks.'],
            ]);
        }

        $task->update([
            'google_sync_enabled' => true,
            'google_sync_status' => GoogleCalendarSyncStatus::Pending,
            'google_sync_error' => null,
            'google_sync_version' => (int) $task->google_sync_version + 1,
            'timezone' => $task->timezone ?: config('app.timezone', 'UTC'),
        ]);

        $this->syncNow($task->fresh() ?? $task, $actor);

        return $task->fresh(['assignee', 'creator', 'project', 'supplier']) ?? $task;
    }

    public function disableAndCancel(User $actor, Task $task, bool $deleteRemote = true): Task
    {
        $this->assertCanManageTask($actor, $task);

        return GoogleCalendarSyncContext::run(function () use ($actor, $task, $deleteRemote): Task {
            $connection = $this->oauth->connectionFor($actor);
            if ($deleteRemote && $connection !== null && filled($task->google_event_id)) {
                try {
                    $this->api->deleteEvent($connection, (string) $task->google_event_id);
                } catch (\Throwable $e) {
                    // Keep local disable even if remote delete fails.
                    $task->google_sync_error = $e->getMessage();
                }
            }

            $task->update([
                'google_sync_enabled' => false,
                'google_sync_status' => GoogleCalendarSyncStatus::Cancelled,
                'google_event_id' => null,
                'google_calendar_id' => null,
                'google_html_link' => null,
                'google_etag' => null,
                'google_synced_at' => now(),
            ]);

            return $task->fresh(['assignee', 'creator', 'project', 'supplier']) ?? $task;
        });
    }

    public function queueSync(Task $task): void
    {
        if (GoogleCalendarSyncContext::isSyncing()) {
            return;
        }

        if (! $task->google_sync_enabled) {
            return;
        }

        $task->update([
            'google_sync_status' => GoogleCalendarSyncStatus::Pending,
            'google_sync_version' => (int) $task->google_sync_version + 1,
        ]);

        SyncTaskToGoogleCalendar::dispatch($task->id, (int) $task->google_sync_version);
    }

    public function syncNow(Task $task, ?User $actor = null): Task
    {
        if (GoogleCalendarSyncContext::isSyncing()) {
            return $task;
        }

        return GoogleCalendarSyncContext::run(function () use ($task, $actor): Task {
            return DB::transaction(function () use ($task, $actor): Task {
                /** @var Task $locked */
                $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

                if (! $locked->google_sync_enabled) {
                    return $locked;
                }

                $owner = $actor ?? $locked->creator ?? User::query()->find($locked->created_by);
                if ($owner === null) {
                    $locked->update([
                        'google_sync_status' => GoogleCalendarSyncStatus::Error,
                        'google_sync_error' => 'Task owner missing for Google sync.',
                    ]);

                    return $locked;
                }

                $connection = $this->oauth->connectionFor($owner);
                if ($connection === null || ! $connection->sync_enabled) {
                    $locked->update([
                        'google_sync_status' => GoogleCalendarSyncStatus::Error,
                        'google_sync_error' => 'Google Calendar not connected.',
                    ]);

                    return $locked;
                }

                $status = $locked->status instanceof TaskStatus
                    ? $locked->status
                    : TaskStatus::tryFrom((string) $locked->status);

                if ($status === TaskStatus::Completed && filled($locked->google_event_id)) {
                    $this->api->deleteEvent($connection, (string) $locked->google_event_id);
                    $locked->update([
                        'google_sync_status' => GoogleCalendarSyncStatus::Cancelled,
                        'google_event_id' => null,
                        'google_html_link' => null,
                        'google_etag' => null,
                        'google_synced_at' => now(),
                        'google_sync_error' => null,
                    ]);
                    $connection->update(['last_synced_at' => now(), 'last_error' => null]);

                    return $locked->fresh() ?? $locked;
                }

                $payload = $this->buildEventPayload($locked, $connection->meet_enabled || $locked->google_meet_enabled);
                $withMeet = (bool) ($connection->meet_enabled || $locked->google_meet_enabled);

                try {
                    if (filled($locked->google_event_id)) {
                        $event = $this->api->updateEvent($connection, (string) $locked->google_event_id, $payload, $withMeet);
                    } else {
                        $event = $this->api->createEvent($connection, $payload, $withMeet);
                    }
                } catch (\Throwable $e) {
                    $locked->update([
                        'google_sync_status' => GoogleCalendarSyncStatus::Error,
                        'google_sync_error' => Str::limit($e->getMessage(), 240),
                    ]);
                    $connection->update(['last_error' => Str::limit($e->getMessage(), 240)]);

                    throw $e;
                }

                $locked->update([
                    'google_event_id' => (string) ($event['id'] ?? $locked->google_event_id),
                    'google_calendar_id' => $connection->calendar_id ?: 'primary',
                    'google_html_link' => $event['htmlLink'] ?? $locked->google_html_link,
                    'google_etag' => $event['etag'] ?? null,
                    'google_sync_status' => GoogleCalendarSyncStatus::Synced,
                    'google_synced_at' => now(),
                    'google_sync_error' => null,
                ]);
                $connection->update(['last_synced_at' => now(), 'last_error' => null]);

                return $locked->fresh(['assignee', 'creator', 'project', 'supplier']) ?? $locked;
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function syncPayload(Task $task): array
    {
        $status = $task->google_sync_status instanceof GoogleCalendarSyncStatus
            ? $task->google_sync_status
            : GoogleCalendarSyncStatus::tryFrom((string) ($task->google_sync_status ?? 'NONE')) ?? GoogleCalendarSyncStatus::None;

        return [
            'google_sync_enabled' => (bool) $task->google_sync_enabled,
            'google_sync_status' => $status->value,
            'google_sync_status_label_ar' => $status->labelAr(),
            'google_event_id' => $task->google_event_id,
            'google_calendar_id' => $task->google_calendar_id,
            'google_html_link' => $task->google_html_link,
            'google_synced_at' => $task->google_synced_at?->toIso8601String(),
            'google_sync_error' => $task->google_sync_error,
            'google_meet_enabled' => (bool) $task->google_meet_enabled,
            'start_at' => $task->start_at?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
            'timezone' => $task->timezone,
            'location' => $task->location,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEventPayload(Task $task, bool $withMeet): array
    {
        $timezone = $task->timezone ?: config('app.timezone', 'UTC');
        [$start, $end] = $this->resolveWindow($task);

        $description = trim((string) ($task->description ?? ''));
        $description .= "\n\nHebr & Abaad task #{$task->id}";
        if ($task->project) {
            $description .= "\nProject: ".$task->project->title;
        }
        // Never include supplier identity for customer-facing copies; this event is internal.

        $attendees = [];
        if ($task->assignee?->email) {
            $attendees[] = ['email' => $task->assignee->email];
        }

        $payload = [
            'summary' => $task->title,
            'description' => trim($description),
            'location' => $task->location,
            'start' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'attendees' => $attendees,
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 15],
                    ['method' => 'popup', 'minutes' => 60],
                ],
            ],
        ];

        if ($withMeet) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'task-'.$task->id.'-'.$task->google_sync_version,
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $payload;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWindow(Task $task): array
    {
        $timezone = $task->timezone ?: config('app.timezone', 'UTC');

        if ($task->start_at !== null) {
            $start = Carbon::parse($task->start_at)->timezone($timezone);
        } elseif ($task->due_at !== null) {
            $start = Carbon::parse($task->due_at)->timezone($timezone)->subHour();
        } elseif ($task->deadline !== null) {
            $start = Carbon::parse($task->deadline->toDateString(), $timezone)->setTime(9, 0);
        } else {
            $start = now($timezone)->addHour()->startOfHour();
        }

        if ($task->due_at !== null) {
            $end = Carbon::parse($task->due_at)->timezone($timezone);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addHour();
            }
        } else {
            $end = $start->copy()->addHour();
        }

        return [$start, $end];
    }

    private function assertCanManageTask(User $actor, Task $task): void
    {
        $task->loadMissing('project');
        if ((int) $task->created_by === (int) $actor->id) {
            return;
        }
        if ((int) $task->assigned_to === (int) $actor->id) {
            return;
        }
        if ($task->project && (int) $task->project->account_manager_id === (int) $actor->id) {
            return;
        }
        if ($actor->role instanceof UserRole && in_array($actor->role, [UserRole::Owner, UserRole::AdminManager], true)) {
            return;
        }

        abort(403);
    }
}
