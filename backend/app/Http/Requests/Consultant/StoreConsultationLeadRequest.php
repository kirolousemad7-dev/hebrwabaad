<?php

namespace App\Http\Requests\Consultant;

use App\Http\Requests\ApiFormRequest;

class StoreConsultationLeadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'business_name' => ['nullable', 'string', 'max:160'],
            'contact_method' => ['nullable', 'in:email,phone,whatsapp'],
        ];
    }
}
