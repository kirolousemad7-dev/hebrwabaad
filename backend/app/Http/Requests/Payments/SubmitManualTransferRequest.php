<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiFormRequest;

class SubmitManualTransferRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_number' => ['required', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
