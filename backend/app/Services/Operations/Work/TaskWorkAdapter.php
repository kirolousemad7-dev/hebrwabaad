<?php

namespace App\Services\Operations\Work;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use App\Support\Operations\UnifiedWorkItem;
use App\Support\Operations\UnifiedWorkReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskWorkAdapter
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toItem(Task $task, User $actor): array
    {
        $task->loadMissing(['assignee', 'project', 'creator']);

        $statusValue = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
        $priorityValue = $task->priority instanceof TaskPriority ? $task->priority->value : (string) $task->priority;
        $isOverdue = $task->isOverdue();
        $isCompleted = $statusValue === TaskStatus::Completed->value;
        $normalizedStatus = $isOverdue && ! $isCompleted
            ? 'overdue'
            : $this->normalizeStatus($statusValue);

        $canUpdate = Gate::forUser($actor)->allows('update', $task);
        $canUpdateStatus = Gate::forUser($actor)->allows('updateStatus', $task);

        return UnifiedWorkItem::fromArray([
            'id' => UnifiedWorkReference::forTask((int) $task->id)->toString(),
            'source_type' => 'task',
            'source_id' => (int) $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $normalizedStatus,
            'priority' => $priorityValue,
            'due_at' => $task->deadline?->toDateString(),
            'starts_at' => null,
            'assignee_ids' => $task->assigned_to ? [(int) $task->assigned_to] : [],
            'department_id' => $task->department_id !== null
                ? (int) $task->department_id
                : ($task->assignee?->department_id !== null ? (int) $task->assignee->department_id : null),
            'project_id' => $task->project_id !== null ? (int) $task->project_id : null,
            'related_type' => $task->project_id ? 'project' : null,
            'related_id' => $task->project_id !== null ? (int) $task->project_id : null,
            'created_by' => $task->created_by !== null ? (int) $task->created_by : null,
            'is_overdue' => $isOverdue,
            'is_completed' => $isCompleted,
            'is_blocked' => false,
            'blocked_label' => null,
            'href' => $this->href($actor, (int) $task->id),
            'capabilities' => [
                'can_edit' => $canUpdate,
                'can_complete' => ($canUpdate || $canUpdateStatus) && ! $isCompleted,
                'can_assign' => $canUpdate,
                'can_reschedule' => $canUpdate,
                'can_start' => ($canUpdate || $canUpdateStatus)
                    && ! $isCompleted
                    && $statusValue !== TaskStatus::InProgress->value,
            ],
            'source_badge' => 'task',
        ])->toArray() + [
            'calendar_item_id' => $task->calendar_item_id !== null ? (int) $task->calendar_item_id : null,
        ];
    }

    public function complete(User $actor, Task $task): array
    {
        $this->assertCanMutateStatus($actor, $task);

        $task = $this->tasks->updateStatus($task, TaskStatus::Completed);

        return $this->toItem($task, $actor);
    }

    /**
     * @param  list<int>  $assigneeIds
     */
    public function assign(User $actor, Task $task, array $assigneeIds): array
    {
        $this->assertCanManage($actor, $task);

        $first = (int) ($assigneeIds[0] ?? 0);
        if ($first <= 0) {
            throw ValidationException::withMessages([
                'assignee_ids' => ['At least one assignee is required.'],
            ]);
        }

        $task = $this->tasks->update($actor, $task, [
            'title' => $task->title,
            'description' => $task->description,
            'project_id' => $task->project_id,
            'assigned_to' => $first,
            'priority' => $task->priority instanceof TaskPriority ? $task->priority->value : $task->priority,
            'status' => $task->status instanceof TaskStatus ? $task->status->value : $task->status,
            'deadline' => $task->deadline?->toDateString(),
        ]);

        return $this->toItem($task, $actor);
    }

    public function setPriority(User $actor, Task $task, string $priority): array
    {
        $this->assertCanManage($actor, $task);

        $mapped = TaskPriority::tryFrom(strtoupper($priority));
        if ($mapped === null) {
            throw ValidationException::withMessages([
                'priority' => ['Invalid priority.'],
            ]);
        }

        $task = $this->tasks->update($actor, $task, [
            'title' => $task->title,
            'description' => $task->description,
            'project_id' => $task->project_id,
            'assigned_to' => $task->assigned_to,
            'priority' => $mapped->value,
            'status' => $task->status instanceof TaskStatus ? $task->status->value : $task->status,
            'deadline' => $task->deadline?->toDateString(),
        ]);

        return $this->toItem($task, $actor);
    }

    public function setStatus(User $actor, Task $task, string $status): array
    {
        $this->assertCanMutateStatus($actor, $task);

        $mapped = $this->denormalizeStatus($status);
        $task = $this->tasks->updateStatus($task, $mapped);

        return $this->toItem($task, $actor);
    }

    public function reschedule(User $actor, Task $task, ?string $dueAt): array
    {
        $this->assertCanManage($actor, $task);

        $task = $this->tasks->update($actor, $task, [
            'title' => $task->title,
            'description' => $task->description,
            'project_id' => $task->project_id,
            'assigned_to' => $task->assigned_to,
            'priority' => $task->priority instanceof TaskPriority ? $task->priority->value : $task->priority,
            'status' => $task->status instanceof TaskStatus ? $task->status->value : $task->status,
            'deadline' => $dueAt,
        ]);

        return $this->toItem($task, $actor);
    }

    public function start(User $actor, Task $task): array
    {
        return $this->setStatus($actor, $task, 'in_progress');
    }

    private function assertCanManage(User $actor, Task $task): void
    {
        if (! Gate::forUser($actor)->allows('update', $task)) {
            throw new AuthorizationException;
        }
    }

    private function assertCanMutateStatus(User $actor, Task $task): void
    {
        if (Gate::forUser($actor)->allows('updateStatus', $task) || Gate::forUser($actor)->allows('update', $task)) {
            return;
        }

        throw new AuthorizationException;
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            TaskStatus::Todo->value => 'open',
            TaskStatus::InProgress->value => 'in_progress',
            TaskStatus::Review->value, TaskStatus::Revision->value => 'review',
            TaskStatus::Completed->value => 'completed',
            default => 'open',
        };
    }

    private function denormalizeStatus(string $status): TaskStatus
    {
        return match (strtolower($status)) {
            'open' => TaskStatus::Todo,
            'in_progress' => TaskStatus::InProgress,
            'review' => TaskStatus::Review,
            'completed' => TaskStatus::Completed,
            default => throw ValidationException::withMessages([
                'status' => ['Unsupported status for workspace tasks.'],
            ]),
        };
    }

    private function href(User $actor, int $taskId): string
    {
        $role = $actor->role instanceof UserRole ? $actor->role : null;

        if ($role === UserRole::AccountManager || $role === UserRole::Owner) {
            return '/workspace/account-manager/tasks?task='.$taskId;
        }

        return '/workspace/tasks?task='.$taskId;
    }
}
