<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class ConvertCrmLeadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'order_title' => ['nullable', 'string', 'max:200'],
            'order_description' => ['nullable', 'string', 'max:5000'],
            'project_title' => ['nullable', 'string', 'max:200'],
            'project_description' => ['nullable', 'string', 'max:5000'],
            'create_project' => ['nullable', 'boolean'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
