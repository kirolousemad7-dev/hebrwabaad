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
            'summary' => ['nullable', 'string', 'max:500'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'deliverables' => ['nullable', 'array'],
            'deliverables.*' => ['string', 'max:500'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:500'],
            'process_steps' => ['nullable', 'array'],
            'process_steps.*.title' => ['required_with:process_steps', 'string', 'max:255'],
            'process_steps.*.description' => ['nullable', 'string', 'max:2000'],
            'faq' => ['nullable', 'array'],
            'faq.*.question' => ['required_with:faq', 'string', 'max:500'],
            'faq.*.answer' => ['required_with:faq', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['string', 'max:500'],
            'hero_image' => ['nullable', 'string', 'max:500'],
            'category' => ['required', Rule::in(ServiceCategory::values())],
            'subcategory' => ['nullable', 'string', 'max:120'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pricing_mode' => ['nullable', Rule::in(CatalogPricingMode::values())],
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'revision_rounds' => ['nullable', 'integer', 'min:0', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'task_title_template' => ['nullable', 'string', 'max:255'],
            'default_task_priority' => ['nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'URGENT'])],
            'requires_review' => ['nullable', 'boolean'],
            'requires_customer_approval' => ['nullable', 'boolean'],
            'checklist_template' => ['nullable', 'array'],
            'checklist_template.*' => ['string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'robots' => ['nullable', 'string', 'max:64'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:catalog_addons,id'],
            'sector_ids' => ['nullable', 'array'],
            'sector_ids.*' => ['integer', 'exists:sectors,id'],
            'portfolio_item_ids' => ['nullable', 'array'],
            'portfolio_item_ids.*' => ['integer', 'exists:portfolio_items,id'],
            'supplier_ids' => ['nullable', 'array'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:supplier_products,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'quotation_ids' => ['nullable', 'array'],
            'quotation_ids.*' => ['integer', 'exists:commercial_quotations,id'],
        ];
    }
}
