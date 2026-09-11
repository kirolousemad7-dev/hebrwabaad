<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class AssignCrmLeadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
