<?php

namespace App\Http\Requests\Catalog;

use App\Enums\CatalogPricingMode;
use App\Enums\ServiceCategory;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $serviceId = $this->route('service')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('services', 'slug')->ignore($serviceId)],
            'summary' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'required', Rule::in(ServiceCategory::values())],
            'base_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'pricing_mode' => ['sometimes', Rule::in(CatalogPricingMode::values())],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'revision_rounds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'task_title_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_task_priority' => ['sometimes', 'nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'URGENT'])],
            'requires_review' => ['sometimes', 'boolean'],
            'requires_customer_approval' => ['sometimes', 'boolean'],
            'checklist_template' => ['sometimes', 'nullable', 'array'],
            'checklist_template.*' => ['string', 'max:255'],
        ];
    }
}
