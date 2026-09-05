<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\ApiFormRequest;

class UpdateEmployeeStatusRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
