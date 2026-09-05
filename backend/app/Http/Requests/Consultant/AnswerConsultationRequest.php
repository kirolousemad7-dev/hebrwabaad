<?php

namespace App\Http\Requests\Consultant;

use App\Http\Requests\ApiFormRequest;

class AnswerConsultationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'string', 'max:64'],
            'value' => ['required'],
        ];
    }
}
