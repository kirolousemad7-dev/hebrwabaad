<?php

namespace App\Http\Requests\Invoices;

use App\Enums\PaymentMethod;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class RecordInvoicePaymentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $invoice = $this->route('invoice');

        return $user !== null && $user->can('recordPayment', $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
