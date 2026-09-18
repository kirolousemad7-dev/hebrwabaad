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
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
            'scope' => ['sometimes', 'nullable', 'string'],
            'deliverables' => ['sometimes', 'nullable', 'array'],
            'deliverables.*' => ['string', 'max:500'],
            'features' => ['sometimes', 'nullable', 'array'],
            'features.*' => ['string', 'max:500'],
            'process_steps' => ['sometimes', 'nullable', 'array'],
            'process_steps.*.title' => ['required_with:process_steps', 'string', 'max:255'],
            'process_steps.*.description' => ['nullable', 'string', 'max:2000'],
            'faq' => ['sometimes', 'nullable', 'array'],
            'faq.*.question' => ['required_with:faq', 'string', 'max:500'],
            'faq.*.answer' => ['required_with:faq', 'string', 'max:5000'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'gallery' => ['sometimes', 'nullable', 'array'],
            'gallery.*' => ['string', 'max:500'],
            'hero_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'required', Rule::in(ServiceCategory::values())],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:120'],
            'base_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'pricing_mode' => ['sometimes', Rule::in(CatalogPricingMode::values())],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'revision_rounds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'task_title_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_task_priority' => ['sometimes', 'nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'URGENT'])],
            'requires_review' => ['sometimes', 'boolean'],
            'requires_customer_approval' => ['sometimes', 'boolean'],
            'checklist_template' => ['sometimes', 'nullable', 'array'],
            'checklist_template.*' => ['string', 'max:255'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'og_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'og_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:64'],
            'addon_ids' => ['sometimes', 'nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:catalog_addons,id'],
            'sector_ids' => ['sometimes', 'nullable', 'array'],
            'sector_ids.*' => ['integer', 'exists:sectors,id'],
            'portfolio_item_ids' => ['sometimes', 'nullable', 'array'],
            'portfolio_item_ids.*' => ['integer', 'exists:portfolio_items,id'],
            'supplier_ids' => ['sometimes', 'nullable', 'array'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
            'product_ids' => ['sometimes', 'nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:supplier_products,id'],
            'project_ids' => ['sometimes', 'nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'quotation_ids' => ['sometimes', 'nullable', 'array'],
            'quotation_ids.*' => ['integer', 'exists:commercial_quotations,id'],
        ];
    }
}
