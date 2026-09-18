<?php

namespace App\Http\Requests\NeedsDiscovery;

use App\Enums\RequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(RequirementStatus::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'qualify' => ['sometimes', 'boolean'],
        ];
    }
}
