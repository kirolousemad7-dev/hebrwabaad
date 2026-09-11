<?php

namespace App\Http\Requests\Crm;

use App\Enums\CrmLeadPriority;
use App\Enums\CrmLeadStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCrmLeadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'alt_phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'integer', 'exists:crm_lead_sources,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(CrmLeadStatus::values())],
            'stage_id' => ['nullable', 'integer', 'exists:crm_pipeline_stages,id'],
            'priority' => ['nullable', Rule::in(CrmLeadPriority::values())],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'next_follow_up_at' => ['nullable', 'date'],
            'expected_close_at' => ['nullable', 'date'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'company_id' => ['nullable', 'integer', 'exists:crm_companies,id'],
        ];
    }
}
