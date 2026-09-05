<?php

namespace App\Http\Requests\Workspace;

use App\Enums\TaskStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
        ];
    }
}
