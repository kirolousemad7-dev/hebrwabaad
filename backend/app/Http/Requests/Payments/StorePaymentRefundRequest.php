<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiFormRequest;

class StorePaymentRefundRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'manual' => ['sometimes', 'boolean'],
        ];
    }
}
