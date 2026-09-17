<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierPricingModel;
use App\Enums\SupplierVisibility;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertSupplierServiceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->canReviewContent() || $user->role?->value === 'SUPPLIER');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'pricing_model' => ['sometimes', 'string', Rule::enum(SupplierPricingModel::class)],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'maximum_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'delivery_time' => ['nullable', 'string', 'max:120'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', 'string', Rule::enum(SupplierVisibility::class)],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
