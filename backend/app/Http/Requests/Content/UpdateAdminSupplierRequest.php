<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Http\Requests\ApiFormRequest;
use App\Support\SeoPages;
use Illuminate\Validation\Rule;

class UpdateAdminSupplierRequest extends ApiFormRequest
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
        $supplierId = $this->route('supplier')?->id ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'supplier_code' => ['nullable', 'string', 'max:32', Rule::unique('suppliers', 'supplier_code')->ignore($supplierId)],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('suppliers', 'slug')->ignore($supplierId)],
            'logo' => ['sometimes', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'short_description' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:8000'],
            'specialties' => ['nullable', 'array'],
            'services' => ['nullable', 'array'],
            'location' => ['sometimes', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:2048'],
            'category' => ['nullable', 'string', 'max:120'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:supplier_categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'brand_colors' => ['nullable', 'array'],
            'brand_description' => ['nullable', 'string', 'max:2000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'min_order_info' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['sometimes', 'string', Rule::enum(SupplierStatus::class)],
            'verification_status' => ['sometimes', 'string', Rule::enum(SupplierVerificationStatus::class)],
            'onboarding_status' => ['sometimes', 'string', Rule::enum(SupplierOnboardingStatus::class)],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'show_public_contact' => ['sometimes', 'boolean'],
            'company_id' => ['nullable', 'integer', 'exists:crm_companies,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'og_title' => ['nullable', 'string', 'max:70'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'canonical_url' => ['nullable', 'string', 'max:2048'],
            'robots' => ['nullable', 'string', 'in:'.implode(',', SeoPages::robotsOptions())],
        ];
    }
}
