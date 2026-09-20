<?php

namespace App\Services\Operations\Work;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarSource;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\CalendarItem;
use App\Models\Task;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use App\Services\Operations\OperationsAuditLogger;
use App\Services\TaskService;
use App\Support\Operations\TaskCalendarSyncContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Explicit soft links between workspace Task and CalendarItem (type=TASK).
 *
 * Tables stay separate — no auto-create, no circular sync loops.
 */
class TaskCalendarLinkService
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly TaskService $tasks,
        private readonly OperationsAuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     starts_at: string,
     *     ends_at?: string|null,
     *     all_day?: bool,
     *     reminders?: list<string>
     * }  $schedule
     */
    public function linkFromTask(User $actor, Task $task, array $schedule): CalendarItem
    {
        return DB::transaction(function () use ($actor, $task, $schedule): CalendarItem {
            /** @var Task $locked */
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            $existing = $this->resolveLinkedCalendar($locked);
            if ($existing !== null) {
                if ($locked->calendar_item_id === null) {
                    $locked->forceFill(['calendar_item_id' => $existing->id])->save();
                }

                return $existing->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
            }

            if (! isset($schedule['starts_at']) || ! is_string($schedule['starts_at']) || $schedule['starts_at'] === '') {
                throw ValidationException::withMessages([
                    'starts_at' => ['A start time is required to link a calendar item.'],
                ]);
            }

            $priority = $locked->priority instanceof TaskPriority
                ? $locked->priority->value
                : (string) $locked->priority;

            try {
                return TaskCalendarSyncContext::with('task_to_calendar', function () use ($actor, $locked, $schedule, $priority): CalendarItem {
                    $item = $this->calendar->create($actor, [
                        'title' => $locked->title,
                        'description' => $locked->description,
                        'type' => CalendarItemType::Task->value,
                        'priority' => $priority,
                        'source' => CalendarSource::Project->value,
                        'starts_at' => $schedule['starts_at'],
                        'ends_at' => $schedule['ends_at'] ?? null,
                        'all_day' => (bool) ($schedule['all_day'] ?? false),
                        'reminders' => $schedule['reminders'] ?? [],
                        'related_type' => 'workspace_task',
                        'related_id' => $locked->id,
                        'assignee_ids' => $locked->assigned_to
                            ? [(int) $locked->assigned_to]
                            : [$actor->id],
                    ]);

                    $locked->forceFill(['calendar_item_id' => $item->id])->save();

                    $this->audit->log($actor, 'task_calendar.linked', $locked, [
                        'direction' => 'task_to_calendar',
                        'calendar_item_id' => $item->id,
                    ]);

                    return $item->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
                });
            } catch (QueryException $exception) {
                // Concurrent create hit uniqueness — reuse the winner.
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $winner = $this->resolveLinkedCalendar($locked->fresh() ?? $locked);
                if ($winner === null) {
                    throw $exception;
                }

                if ($locked->calendar_item_id !== $winner->id) {
                    $locked->forceFill(['calendar_item_id' => $winner->id])->save();
                }

                return $winner->fresh(['creator', 'assignees', 'reminders'])->loadCount(['comments', 'files']);
            }
        });
    }

    public function linkFromCalendar(User $actor, CalendarItem $item, ?int $projectId = null): Task
    {
        $type = $item->type instanceof CalendarItemType
            ? $item->type
            : CalendarItemType::tryFrom((string) $item->type);

        if ($type !== CalendarItemType::Task) {
            throw ValidationException::withMessages([
                'calendar_item' => ['Only calendar items of type TASK can be linked to a workspace task.'],
            ]);
        }

        if ($this->findLinkedTask($item) !== null) {
            throw ValidationException::withMessages([
                'calendar_item' => ['This calendar item is already linked to a workspace task.'],
            ]);
        }

        $resolvedProjectId = $projectId
            ?? (($item->related_type === 'project' && $item->related_id) ? (int) $item->related_id : null);

        if ($resolvedProjectId === null || $resolvedProjectId <= 0) {
            throw ValidationException::withMessages([
                'project_id' => ['A project_id is required when the calendar item is not related to a project.'],
            ]);
        }

        $assigneeId = $this->resolveAssigneeId($item, $actor);
        $priority = $item->priority instanceof CalendarItemPriority
            ? $item->priority->value
            : (string) ($item->priority ?? TaskPriority::Medium->value);

        return TaskCalendarSyncContext::with('calendar_to_task', function () use (
            $actor,
            $item,
            $resolvedProjectId,
            $assigneeId,
            $priority,
        ): Task {
            $task = $this->tasks->create($actor, [
                'title' => $item->title,
                'description' => $item->description,
                'project_id' => $resolvedProjectId,
                'assigned_to' => $assigneeId,
                'priority' => $priority,
                'status' => TaskStatus::Todo->value,
                'deadline' => $item->starts_at?->toDateString(),
            ]);

            $task->forceFill(['calendar_item_id' => $item->id])->save();

            $this->calendar->update($actor, $item, [
                'related_type' => 'workspace_task',
                'related_id' => $task->id,
            ]);

            $this->audit->log($actor, 'task_calendar.linked', $task, [
                'direction' => 'calendar_to_task',
                'calendar_item_id' => $item->id,
            ]);

            return $task->fresh(['assignee', 'creator', 'project', 'calendarItem']);
        });
    }

    public function unlink(User $actor, Task|CalendarItem $subject): void
    {
        TaskCalendarSyncContext::with('unlink', function () use ($actor, $subject): void {
            if ($subject instanceof Task) {
                $calendarItemId = $subject->calendar_item_id;
                $this->clearLinkForTask($subject);
                $this->audit->log($actor, 'task_calendar.unlinked', $subject, [
                    'calendar_item_id' => $calendarItemId,
                ]);

                return;
            }

            $task = $this->findLinkedTask($subject);
            if ($task !== null) {
                $this->clearLinkForTask($task);
                $this->audit->log($actor, 'task_calendar.unlinked', $task, [
                    'calendar_item_id' => $subject->id,
                ]);

                return;
            }

            if ($subject->related_type === 'workspace_task') {
                $subject->forceFill([
                    'related_type' => null,
                    'related_id' => null,
                    'related_label' => null,
                    'related_href' => null,
                ])->save();
                $this->audit->log($actor, 'task_calendar.unlinked', $subject, [
                    'calendar_item_id' => $subject->id,
                ]);
            }
        });
    }

    public function syncCompletionFromTask(Task $task): void
    {
        if (TaskCalendarSyncContext::isSyncing()) {
            return;
        }

        $item = $this->resolveLinkedCalendar($task);
        if ($item === null) {
            return;
        }

        $status = $item->status instanceof CalendarItemStatus
            ? $item->status
            : CalendarItemStatus::tryFrom((string) $item->status);

        if ($status === CalendarItemStatus::Completed) {
            return;
        }

        $actor = $this->actorForTask($task);

        TaskCalendarSyncContext::with('completion_from_task', function () use ($actor, $item): void {
            $this->calendar->complete($actor, $item);
        });
    }

    public function syncCompletionFromCalendar(CalendarItem $item): void
    {
        if (TaskCalendarSyncContext::isSyncing()) {
            return;
        }

        $task = $this->findLinkedTask($item);
        if ($task === null) {
            return;
        }

        $status = $task->status instanceof TaskStatus
            ? $task->status
            : TaskStatus::tryFrom((string) $task->status);

        if ($status === TaskStatus::Completed) {
            return;
        }

        TaskCalendarSyncContext::with('completion_from_calendar', function () use ($task): void {
            $actor = $this->actorForTask($task);
            $this->tasks->updateStatus($actor, $task, TaskStatus::Completed);
        });
    }

    public function syncTitleFromTask(Task $task): void
    {
        if (TaskCalendarSyncContext::isSyncing()) {
            return;
        }

        $item = $this->resolveLinkedCalendar($task);
        if ($item === null || $item->title === $task->title) {
            return;
        }

        $actor = $this->actorForTask($task);

        TaskCalendarSyncContext::with('title_from_task', function () use ($actor, $item, $task): void {
            $this->calendar->update($actor, $item, [
                'title' => $task->title,
            ]);
        });
    }

    private function clearLinkForTask(Task $task): void
    {
        $calendarItemId = $task->calendar_item_id !== null ? (int) $task->calendar_item_id : null;
        $task->forceFill(['calendar_item_id' => null])->save();

        $item = $calendarItemId !== null
            ? CalendarItem::query()->find($calendarItemId)
            : null;

        if ($item === null && $task->id) {
            $item = CalendarItem::query()
                ->where('related_type', 'workspace_task')
                ->where('related_id', $task->id)
                ->first();
        }

        if ($item !== null && $item->related_type === 'workspace_task' && (int) $item->related_id === (int) $task->id) {
            $item->forceFill([
                'related_type' => null,
                'related_id' => null,
                'related_label' => null,
                'related_href' => null,
            ])->save();
        }
    }

    private function resolveLinkedCalendar(Task $task): ?CalendarItem
    {
        if ($task->calendar_item_id !== null) {
            $byFk = CalendarItem::query()->find((int) $task->calendar_item_id);
            if (
                $byFk !== null
                && $byFk->related_type === 'workspace_task'
                && (int) $byFk->related_id === (int) $task->id
            ) {
                return $byFk;
            }

            // Stale FK — clear and fall through to related lookup / recreate.
            $task->forceFill(['calendar_item_id' => null])->save();
        }

        return CalendarItem::query()
            ->where('related_type', 'workspace_task')
            ->where('related_id', $task->id)
            ->first();
    }

    private function findLinkedTask(CalendarItem $item): ?Task
    {
        $byFk = Task::query()->where('calendar_item_id', $item->id)->first();
        if ($byFk !== null) {
            return $byFk;
        }

        if ($item->related_type === 'workspace_task' && $item->related_id) {
            return Task::query()->find((int) $item->related_id);
        }

        return null;
    }

    private function resolveAssigneeId(CalendarItem $item, User $actor): int
    {
        $item->loadMissing('assignees');

        $assignee = $item->assignees->first();
        if ($assignee instanceof User) {
            return (int) $assignee->id;
        }

        return (int) $actor->id;
    }

    private function actorForTask(Task $task): User
    {
        $task->loadMissing(['assignee', 'creator']);

        if ($task->assignee instanceof User) {
            return $task->assignee;
        }

        if ($task->creator instanceof User) {
            return $task->creator;
        }

        return User::query()->findOrFail((int) ($task->assigned_to ?? $task->created_by));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = $exception->getMessage();

        return $sqlState === '23000'
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'calendar_items_workspace_task')
            || str_contains($message, 'tasks_calendar_item_id_unique');
    }
}
