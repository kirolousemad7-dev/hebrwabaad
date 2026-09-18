<?php

namespace App\Http\Requests\Invoices;

use App\Enums\UserRole;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $invoice = $this->route('invoice');

        return $user !== null
            && $user->can('update', $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Customer->value)],
            'crm_company_id' => ['nullable', 'integer', 'exists:crm_companies,id'],
            'commercial_quotation_id' => ['nullable', 'integer', 'exists:commercial_quotations,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'number' => ['prohibited'],
            'total' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'amount_due' => ['prohibited'],
            'amount_paid' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
