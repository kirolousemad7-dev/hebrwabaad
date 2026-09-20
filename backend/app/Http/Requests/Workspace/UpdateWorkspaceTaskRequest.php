<?php

namespace App\Http\Requests\Workspace;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Validation\Rule;

class UpdateWorkspaceTaskRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $project = $this->route('project');
        $task = $this->route('task');

        if ($project instanceof Project && $task instanceof Task
            && (int) $task->project_id !== (int) $project->id) {
            abort(404);
        }

        if ($project instanceof Project) {
            $this->merge(['project_id' => $project->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'phase_id' => [
                'nullable',
                'integer',
                Rule::exists('project_phases', 'id')->where(function ($query): void {
                    $projectId = $this->scopedProjectId();
                    if ($projectId !== null) {
                        $query->where('project_id', $projectId);
                    }
                }),
            ],
            'milestone_id' => [
                'nullable',
                'integer',
                Rule::exists('project_milestones', 'id')->where(function ($query): void {
                    $projectId = $this->scopedProjectId();
                    if ($projectId !== null) {
                        $query->where('project_id', $projectId);
                    }
                }),
            ],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'priority' => ['required', 'string', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'timezone' => ['nullable', 'timezone'],
            'location' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
            'is_client_visible' => ['nullable', 'boolean'],
            'link_to_calendar' => ['nullable', 'boolean'],
            'reminders' => ['nullable', 'array', 'max:10'],
        ];
    }

    private function scopedProjectId(): ?int
    {
        $project = $this->route('project');
        if ($project instanceof Project) {
            return (int) $project->id;
        }

        $projectId = $this->input('project_id');

        return $projectId !== null && $projectId !== '' ? (int) $projectId : null;
    }
}
