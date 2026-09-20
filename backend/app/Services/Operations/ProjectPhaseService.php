<?php

namespace App\Services\Operations;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectPhaseService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(Project $project): array
    {
        return ProjectPhase::query()
            ->with(['responsible:id,name'])
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectPhase $phase) => $this->serialize($phase))
            ->all();
    }

    /**
     * Hierarchical Project → Phases → Milestones → Tasks (same Task records).
     *
     * @return array<string, mixed>
     */
    public function structure(Project $project): array
    {
        $phases = ProjectPhase::query()
            ->with(['responsible:id,name'])
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $milestones = ProjectMilestone::query()
            ->with(['responsible:id,name'])
            ->where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $tasks = Task::query()
            ->with(['assignee:id,name'])
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        $milestonesByPhase = $milestones->groupBy(fn (ProjectMilestone $row) => $row->phase_id ?? 0);
        $tasksByPhase = $tasks->groupBy(fn (Task $row) => $row->phase_id ?? 0);
        $tasksByMilestone = $tasks->groupBy(fn (Task $row) => $row->milestone_id ?? 0);

        $phaseNodes = $phases->map(function (ProjectPhase $phase) use ($milestonesByPhase, $tasksByPhase, $tasksByMilestone): array {
            $phaseMilestones = ($milestonesByPhase->get($phase->id) ?? collect())
                ->map(function (ProjectMilestone $milestone) use ($tasksByMilestone): array {
                    $related = ($tasksByMilestone->get($milestone->id) ?? collect())->values();

                    return $this->serializeMilestoneNode($milestone, $related->all());
                })
                ->values()
                ->all();

            $orphanTasks = ($tasksByPhase->get($phase->id) ?? collect())
                ->filter(fn (Task $task) => $task->milestone_id === null)
                ->values()
                ->map(fn (Task $task) => $this->serializeTaskNode($task))
                ->all();

            $allPhaseTasks = ($tasksByPhase->get($phase->id) ?? collect())->values();

            return [
                ...$this->serialize($phase),
                'progress' => $this->progressFromTasks($allPhaseTasks->all()),
                'milestones' => $phaseMilestones,
                'tasks' => $orphanTasks,
            ];
        })->values()->all();

        $unassignedMilestones = ($milestonesByPhase->get(0) ?? collect())
            ->map(function (ProjectMilestone $milestone) use ($tasksByMilestone): array {
                $related = ($tasksByMilestone->get($milestone->id) ?? collect())->values();

                return $this->serializeMilestoneNode($milestone, $related->all());
            })
            ->values()
            ->all();

        $unassignedTasks = ($tasksByPhase->get(0) ?? collect())
            ->filter(fn (Task $task) => $task->milestone_id === null)
            ->values()
            ->map(fn (Task $task) => $this->serializeTaskNode($task))
            ->all();

        return [
            'project_id' => $project->id,
            'phases' => $phaseNodes,
            'unassigned_milestones' => $unassignedMilestones,
            'unassigned_tasks' => $unassignedTasks,
            'progress' => $this->progressFromTasks($tasks->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, Project $project, array $attributes): ProjectPhase
    {
        $status = (string) ($attributes['status'] ?? ProjectPhase::STATUS_PENDING);
        $this->assertStatus($status);

        $sortOrder = array_key_exists('sort_order', $attributes)
            ? (int) $attributes['sort_order']
            : (int) (ProjectPhase::query()->where('project_id', $project->id)->max('sort_order') ?? 0) + 1;

        return ProjectPhase::query()->create([
            'project_id' => $project->id,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'status' => $status,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'sort_order' => $sortOrder,
            'is_client_visible' => (bool) ($attributes['is_client_visible'] ?? true),
            'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ProjectPhase $phase, array $attributes): ProjectPhase
    {
        $payload = [];

        foreach (['title', 'description', 'starts_at', 'ends_at', 'sort_order', 'responsible_user_id'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('is_client_visible', $attributes)) {
            $payload['is_client_visible'] = (bool) $attributes['is_client_visible'];
        }

        if (array_key_exists('status', $attributes)) {
            $status = (string) $attributes['status'];
            $this->assertStatus($status);
            $payload['status'] = $status;
        }

        $phase->update($payload);

        return $phase->fresh(['responsible:id,name']) ?? $phase;
    }

    public function delete(ProjectPhase $phase): void
    {
        DB::transaction(function () use ($phase): void {
            Task::query()->where('phase_id', $phase->id)->update(['phase_id' => null]);
            ProjectMilestone::query()->where('phase_id', $phase->id)->update(['phase_id' => null]);
            $phase->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ProjectPhase $phase): array
    {
        return [
            'id' => $phase->id,
            'project_id' => $phase->project_id,
            'title' => $phase->title,
            'description' => $phase->description,
            'status' => $phase->status,
            'starts_at' => $phase->starts_at?->toDateString(),
            'ends_at' => $phase->ends_at?->toDateString(),
            'sort_order' => (int) $phase->sort_order,
            'is_client_visible' => (bool) $phase->is_client_visible,
            'responsible_user_id' => $phase->responsible_user_id,
            'responsible' => $phase->relationLoaded('responsible') && $phase->responsible
                ? ['id' => $phase->responsible->id, 'name' => $phase->responsible->name]
                : null,
            'created_at' => $phase->created_at?->toIso8601String(),
            'updated_at' => $phase->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<Task>  $tasks
     * @return array<string, mixed>
     */
    private function serializeMilestoneNode(ProjectMilestone $milestone, array $tasks): array
    {
        return [
            'id' => $milestone->id,
            'project_id' => $milestone->project_id,
            'phase_id' => $milestone->phase_id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'starts_at' => $milestone->starts_at?->toDateString(),
            'due_date' => $milestone->due_date?->toDateString(),
            'status' => $milestone->status,
            'sort_order' => (int) $milestone->sort_order,
            'is_client_visible' => (bool) $milestone->is_client_visible,
            'responsible_user_id' => $milestone->responsible_user_id,
            'responsible' => $milestone->relationLoaded('responsible') && $milestone->responsible
                ? ['id' => $milestone->responsible->id, 'name' => $milestone->responsible->name]
                : null,
            'progress' => $this->progressFromTasks($tasks),
            'tasks' => array_map(fn (Task $task) => $this->serializeTaskNode($task), $tasks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTaskNode(Task $task): array
    {
        $status = $task->status instanceof TaskStatus
            ? $task->status->value
            : (string) $task->status;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $status,
            'priority' => $task->priority?->value ?? $task->priority,
            'phase_id' => $task->phase_id,
            'milestone_id' => $task->milestone_id,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->relationLoaded('assignee') && $task->assignee
                ? ['id' => $task->assignee->id, 'name' => $task->assignee->name]
                : null,
            'deadline' => $task->deadline?->toDateString(),
            'start_at' => $task->start_at?->toIso8601String(),
            'due_at' => $task->due_at?->toIso8601String(),
            'calendar_item_id' => $task->calendar_item_id,
            'is_client_visible' => (bool) $task->is_client_visible,
        ];
    }

    /**
     * @param  list<Task>  $tasks
     * @return array{total: int, completed: int, in_progress: int, pending: int, overdue: int, percent: float}
     */
    private function progressFromTasks(array $tasks): array
    {
        $total = count($tasks);
        $completed = 0;
        $inProgress = 0;
        $pending = 0;
        $overdue = 0;
        $today = now()->toDateString();

        foreach ($tasks as $task) {
            $status = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);

            if ($status === TaskStatus::Completed) {
                $completed++;
            } elseif (in_array($status, [TaskStatus::InProgress, TaskStatus::Review, TaskStatus::Revision], true)) {
                $inProgress++;
            } else {
                $pending++;
            }

            if ($status !== TaskStatus::Completed && $task->deadline !== null && $task->deadline->toDateString() < $today) {
                $overdue++;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'overdue' => $overdue,
            'percent' => $total === 0 ? 0.0 : round(($completed / $total) * 100, 1),
        ];
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, [
            ProjectPhase::STATUS_PENDING,
            ProjectPhase::STATUS_IN_PROGRESS,
            ProjectPhase::STATUS_DONE,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid phase status.'],
            ]);
        }
    }
}
