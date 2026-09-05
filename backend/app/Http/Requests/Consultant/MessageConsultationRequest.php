<?php

namespace App\Http\Requests\Consultant;

use App\Http\Requests\ApiFormRequest;

class MessageConsultationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
