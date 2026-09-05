<?php

namespace App\Http\Requests\Workspace;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceTaskRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'priority' => ['required', 'string', Rule::enum(TaskPriority::class)],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
        ];
    }
}
