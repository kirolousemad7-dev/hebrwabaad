<?php

namespace App\Http\Requests\Crm;

use App\Enums\CrmFollowUpType;
use App\Enums\CrmLeadPriority;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCrmFollowUpRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:crm_opportunities,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', Rule::in(CrmFollowUpType::values())],
            'scheduled_at' => ['required', 'date'],
            'priority' => ['nullable', Rule::in(CrmLeadPriority::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
