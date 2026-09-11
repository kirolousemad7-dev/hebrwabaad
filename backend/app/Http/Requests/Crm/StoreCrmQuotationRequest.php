<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\ApiFormRequest;

class StoreCrmQuotationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:crm_opportunities,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'delivery_time' => ['nullable', 'string', 'max:190'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
