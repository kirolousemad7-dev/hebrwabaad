<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class LoseCrmLeadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lost_reason_id' => ['required', 'integer', 'exists:crm_lost_reasons,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'competitor' => ['nullable', 'string', 'max:160'],
        ];
    }
}
