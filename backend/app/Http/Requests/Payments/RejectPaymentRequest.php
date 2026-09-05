<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiFormRequest;

class RejectPaymentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
