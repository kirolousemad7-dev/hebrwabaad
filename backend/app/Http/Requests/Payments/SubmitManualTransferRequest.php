<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiFormRequest;
use App\Models\Payment;

class SubmitManualTransferRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');
        $user = $this->user();

        return $user !== null
            && $payment instanceof Payment
            && $user->can('viewOwned', $payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_number' => ['required', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
