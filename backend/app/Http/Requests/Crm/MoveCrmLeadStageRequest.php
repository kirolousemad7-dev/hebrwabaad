<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class MoveCrmLeadStageRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', 'exists:crm_pipeline_stages,id'],
        ];
    }
}
