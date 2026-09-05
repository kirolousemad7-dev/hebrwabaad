<?php

namespace App\Http\Requests\Consultant;

use App\Http\Requests\ApiFormRequest;

class StoreConsultationEventRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
