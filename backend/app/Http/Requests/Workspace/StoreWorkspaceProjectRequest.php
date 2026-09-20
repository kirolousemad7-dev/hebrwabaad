<?php

namespace App\Http\Requests\Workspace;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceProjectRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isOwner = $this->user()?->role === UserRole::Owner;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'account_manager_id' => $isOwner
                ? [
                    'required',
                    'integer',
                    Rule::exists('users', 'id')->where(function ($query): void {
                        $query->where('role', UserRole::AccountManager->value)
                            ->where('is_active', true);
                    }),
                ]
                : ['prohibited'],
            'status' => ['sometimes', 'string', Rule::enum(ProjectStatus::class)],
            'started_at' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
        ];
    }
}
