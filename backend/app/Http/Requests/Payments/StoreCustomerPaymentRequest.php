<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPaymentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'method' => ['required', 'string', Rule::in(PaymentMethod::values())],
        ];
    }
}
