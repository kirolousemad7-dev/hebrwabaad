<?php

namespace App\Http\Requests\Printing;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrintingQuotationPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role instanceof UserRole
            && $user->role->canReviewPrintingRequests();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in([
                PaymentMethod::Card->value,
                PaymentMethod::Instapay->value,
                PaymentMethod::BankTransfer->value,
            ])],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mark_paid' => ['sometimes', 'boolean'],
        ];
    }
}
