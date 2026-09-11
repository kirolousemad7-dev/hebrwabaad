<?php

namespace App\Http\Requests\Crm;

class UpdateCrmLeadRequest extends StoreCrmLeadRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['full_name'] = ['sometimes', 'required', 'string', 'max:160'];

        return $rules;
    }
}
