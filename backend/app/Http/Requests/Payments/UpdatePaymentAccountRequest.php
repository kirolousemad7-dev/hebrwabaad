<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiFormRequest;

class UpdatePaymentAccountRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_holder_name' => ['sometimes', 'string', 'max:160'],
            'instapay_address' => ['nullable', 'string', 'max:160'],
            'mobile_number' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'iban' => ['nullable', 'string', 'max:64'],
            'branch_name' => ['nullable', 'string', 'max:160'],
            'instructions' => ['sometimes', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
