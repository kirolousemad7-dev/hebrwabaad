<?php

namespace App\Http\Requests\Consultant;

use App\Http\Requests\ApiFormRequest;

class UpdateConsultantSettingsRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'provider' => ['sometimes', 'in:rules,openai'],
        ];
    }
}
