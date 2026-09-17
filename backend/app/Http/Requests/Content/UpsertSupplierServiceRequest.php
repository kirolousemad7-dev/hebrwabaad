<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierPricingModel;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertSupplierServiceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewContent() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'pricing_model' => ['sometimes', 'string', Rule::enum(SupplierPricingModel::class)],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'maximum_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'delivery_time' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
