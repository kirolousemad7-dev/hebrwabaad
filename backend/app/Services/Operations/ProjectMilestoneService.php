<?php

namespace App\Services\Operations;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectActivityService;
use App\Support\ProjectActivityAction;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectMilestoneService
{
    public function __construct(
        private readonly ProjectActivityService $activities,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Project $project, bool $clientVisibleOnly = false): array
    {
        $query = ProjectMilestone::query()
            ->with(['responsible:id,name', 'phase:id,title'])
            ->where('project_id', $project->id);

        if ($clientVisibleOnly) {
            $query->where('is_client_visible', true);
        }

        $milestones = $query
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 0 WHEN 'MISSED' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $tasksByMilestone = Task::query()
            ->where('project_id', $project->id)
            ->whereIn('milestone_id', $milestones->pluck('id')->filter()->all())
            ->get(['id', 'milestone_id', 'status', 'deadline'])
            ->groupBy('milestone_id');

        return $milestones
            ->map(fn (ProjectMilestone $row) => $this->serialize(
                $row,
                $tasksByMilestone->get($row->id) ?? collect(),
            ))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, Project $project, array $attributes): ProjectMilestone
    {
        $status = (string) ($attributes['status'] ?? ProjectMilestone::STATUS_PENDING);
        $this->assertStatus($status);
        $phaseId = $this->resolvePhaseId($project, $attributes['phase_id'] ?? null);

        $sortOrder = array_key_exists('sort_order', $attributes)
            ? (int) $attributes['sort_order']
            : (int) (ProjectMilestone::query()->where('project_id', $project->id)->max('sort_order') ?? 0) + 1;

        $milestone = ProjectMilestone::query()->create([
            'project_id' => $project->id,
            'phase_id' => $phaseId,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'due_date' => $attributes['due_date'] ?? null,
            'status' => $status,
            'sort_order' => $sortOrder,
            'is_client_visible' => (bool) ($attributes['is_client_visible'] ?? false),
            'responsible_user_id' => $attributes['responsible_user_id'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $actor->id,
            'completed_at' => $status === ProjectMilestone::STATUS_DONE ? now() : null,
        ]);

        $this->activities->recordUserAction(
            project: $project,
            user: $actor,
            action: ProjectActivityAction::MILESTONE_CREATED,
            entityType: 'milestone',
            entityId: (int) $milestone->id,
            description: 'Milestone created',
            metadata: [
                'title' => $milestone->title,
                'status' => $milestone->status,
            ],
            isClientVisible: (bool) $milestone->is_client_visible,
        );

        if ($status === ProjectMilestone::STATUS_DONE) {
            $this->activities->recordUserAction(
                project: $project,
                user: $actor,
                action: ProjectActivityAction::MILESTONE_COMPLETED,
                entityType: 'milestone',
                entityId: (int) $milestone->id,
                description: 'Milestone completed',
                metadata: ['title' => $milestone->title],
                isClientVisible: (bool) $milestone->is_client_visible,
            );
        }

        return $milestone;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, Project $project, ProjectMilestone $milestone, array $attributes): ProjectMilestone
    {
        $payload = [];
        $oldStatus = $milestone->status;

        foreach (['title', 'description', 'starts_at', 'due_date', 'notes', 'sort_order', 'responsible_user_id'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('phase_id', $attributes)) {
            $payload['phase_id'] = $this->resolvePhaseId($project, $attributes['phase_id']);
        }

        if (array_key_exists('is_client_visible', $attributes)) {
            $payload['is_client_visible'] = (bool) $attributes['is_client_visible'];
        }

        if (array_key_exists('status', $attributes)) {
            $status = (string) $attributes['status'];
            $this->assertStatus($status);
            $payload['status'] = $status;
            $payload['completed_at'] = $status === ProjectMilestone::STATUS_DONE
                ? ($milestone->completed_at ?? now())
                : null;
        }

        $milestone->update($payload);
        $milestone = $milestone->fresh(['responsible:id,name', 'phase:id,title']) ?? $milestone;

        if (
            array_key_exists('status', $attributes)
            && $oldStatus !== $milestone->status
            && $milestone->status === ProjectMilestone::STATUS_DONE
        ) {
            $this->activities->recordUserAction(
                project: $project,
                user: $actor,
                action: ProjectActivityAction::MILESTONE_COMPLETED,
                entityType: 'milestone',
                entityId: (int) $milestone->id,
                description: 'Milestone completed',
                metadata: ['title' => $milestone->title],
                isClientVisible: (bool) $milestone->is_client_visible,
            );
        } else {
            $this->activities->recordUserAction(
                project: $project,
                user: $actor,
                action: ProjectActivityAction::MILESTONE_UPDATED,
                entityType: 'milestone',
                entityId: (int) $milestone->id,
                description: 'Milestone updated',
                metadata: ['title' => $milestone->title],
                isClientVisible: (bool) $milestone->is_client_visible,
            );
        }

        return $milestone;
    }

    public function delete(ProjectMilestone $milestone): void
    {
        Task::query()->where('milestone_id', $milestone->id)->update(['milestone_id' => null]);
        $milestone->delete();
    }

    /**
     * @param  Collection<int, Task>|null  $tasks
     * @return array<string, mixed>
     */
    public function serialize(ProjectMilestone $milestone, $tasks = null): array
    {
        $tasks = $tasks ?? Task::query()->where('milestone_id', $milestone->id)->get();
        $total = $tasks->count();
        $completed = $tasks->filter(function (Task $task): bool {
            $status = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);

            return $status === TaskStatus::Completed;
        })->count();

        $overdue = $tasks->filter(function (Task $task): bool {
            $status = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);

            return $status !== TaskStatus::Completed
                && $task->deadline !== null
                && $task->deadline->toDateString() < now()->toDateString();
        })->count();

        $isOverdue = $milestone->status !== ProjectMilestone::STATUS_DONE
            && $milestone->due_date !== null
            && $milestone->due_date->toDateString() < now()->toDateString();

        return [
            'id' => $milestone->id,
            'project_id' => $milestone->project_id,
            'phase_id' => $milestone->phase_id,
            'phase' => $milestone->relationLoaded('phase') && $milestone->phase
                ? ['id' => $milestone->phase->id, 'title' => $milestone->phase->title]
                : null,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'starts_at' => $milestone->starts_at?->toDateString(),
            'due_date' => $milestone->due_date?->toDateString(),
            'status' => $milestone->status,
            'sort_order' => (int) $milestone->sort_order,
            'is_client_visible' => (bool) $milestone->is_client_visible,
            'is_overdue' => $isOverdue,
            'responsible_user_id' => $milestone->responsible_user_id,
            'responsible' => $milestone->relationLoaded('responsible') && $milestone->responsible
                ? ['id' => $milestone->responsible->id, 'name' => $milestone->responsible->name]
                : null,
            'notes' => $milestone->notes,
            'created_by' => $milestone->created_by,
            'completed_at' => $milestone->completed_at?->toIso8601String(),
            'open_tasks' => max(0, $total - $completed),
            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'overdue' => $overdue,
                'percent' => $total === 0 ? 0.0 : round(($completed / $total) * 100, 1),
            ],
            'created_at' => $milestone->created_at?->toIso8601String(),
            'updated_at' => $milestone->updated_at?->toIso8601String(),
        ];
    }

    private function resolvePhaseId(Project $project, mixed $phaseId): ?int
    {
        if ($phaseId === null || $phaseId === '') {
            return null;
        }

        $id = (int) $phaseId;
        $exists = ProjectPhase::query()
            ->where('project_id', $project->id)
            ->where('id', $id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'phase_id' => ['Selected phase does not belong to this project.'],
            ]);
        }

        return $id;
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, [
            ProjectMilestone::STATUS_PENDING,
            ProjectMilestone::STATUS_DONE,
            ProjectMilestone::STATUS_MISSED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid milestone status.'],
            ]);
        }
    }
}
