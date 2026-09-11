<?php

namespace App\Services\Operations;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProjectMilestoneService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(Project $project): array
    {
        return ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->orderByRaw("CASE status WHEN 'PENDING' THEN 0 WHEN 'MISSED' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectMilestone $row) => $this->serialize($row))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, Project $project, array $attributes): ProjectMilestone
    {
        $status = (string) ($attributes['status'] ?? ProjectMilestone::STATUS_PENDING);
        $this->assertStatus($status);

        return ProjectMilestone::query()->create([
            'project_id' => $project->id,
            'title' => $attributes['title'],
            'due_date' => $attributes['due_date'] ?? null,
            'status' => $status,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $actor->id,
            'completed_at' => $status === ProjectMilestone::STATUS_DONE ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ProjectMilestone $milestone, array $attributes): ProjectMilestone
    {
        $payload = [];

        if (array_key_exists('title', $attributes)) {
            $payload['title'] = $attributes['title'];
        }
        if (array_key_exists('due_date', $attributes)) {
            $payload['due_date'] = $attributes['due_date'];
        }
        if (array_key_exists('notes', $attributes)) {
            $payload['notes'] = $attributes['notes'];
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

        return $milestone->fresh() ?? $milestone;
    }

    public function delete(ProjectMilestone $milestone): void
    {
        $milestone->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ProjectMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'project_id' => $milestone->project_id,
            'title' => $milestone->title,
            'due_date' => $milestone->due_date?->toDateString(),
            'status' => $milestone->status,
            'notes' => $milestone->notes,
            'created_by' => $milestone->created_by,
            'completed_at' => $milestone->completed_at?->toIso8601String(),
            'created_at' => $milestone->created_at?->toIso8601String(),
            'updated_at' => $milestone->updated_at?->toIso8601String(),
        ];
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
