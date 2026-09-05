<?php

namespace App\Http\Requests\Workspace;

use App\Enums\ProjectStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceProjectRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', Rule::enum(ProjectStatus::class)],
            'started_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
        ];
    }
}
