<?php

namespace App\Http\Requests\Printing;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrintingQuotationRequest extends FormRequest
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
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'total' => ['sometimes', 'numeric', 'min:0'],
            'deposit_required' => ['nullable', 'numeric', 'min:0'],
            'payment_policy' => ['sometimes', Rule::in(PrintingPaymentPolicy::values())],
            'currency' => ['sometimes', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
