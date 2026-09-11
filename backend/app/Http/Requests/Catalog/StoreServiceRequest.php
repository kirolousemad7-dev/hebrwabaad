<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogPricingMode;
use App\Enums\ServiceCategory;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:services,slug'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(ServiceCategory::values())],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pricing_mode' => ['nullable', Rule::in(CatalogPricingMode::values())],
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'revision_rounds' => ['nullable', 'integer', 'min:0', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'task_title_template' => ['nullable', 'string', 'max:255'],
            'default_task_priority' => ['nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'URGENT'])],
            'requires_review' => ['nullable', 'boolean'],
            'requires_customer_approval' => ['nullable', 'boolean'],
            'checklist_template' => ['nullable', 'array'],
            'checklist_template.*' => ['string', 'max:255'],
        ];
    }
}
