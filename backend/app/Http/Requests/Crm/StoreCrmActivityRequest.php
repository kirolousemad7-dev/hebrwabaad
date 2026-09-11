<?php

namespace App\Http\Requests\Crm;

use App\Enums\CrmActivityType;
use App\Enums\CrmCallResult;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCrmActivityRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', 'exists:crm_leads,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:crm_opportunities,id'],
            'type' => ['required', Rule::in(CrmActivityType::values())],
            'occurred_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'call_result' => ['nullable', Rule::in(CrmCallResult::values())],
            'result' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_action' => ['nullable', 'string', 'max:255'],
        ];
    }
}
