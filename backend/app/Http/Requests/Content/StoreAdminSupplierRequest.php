<?php

namespace App\Http\Requests\Content;

use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\SupplierVisibility;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreAdminSupplierRequest extends ApiFormRequest
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
            'legal_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'supplier_code' => ['nullable', 'string', 'max:32', 'unique:suppliers,supplier_code'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:suppliers,slug'],
            'logo' => ['nullable', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'short_description' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:8000'],
            'specialties' => ['nullable', 'array'],
            'specialties.*' => ['string', 'max:80'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:80'],
            'location' => ['required', 'string', 'max:120'],
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
            'notes' => ['nullable', 'string', 'max:4000'],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['sometimes', 'string', Rule::enum(SupplierStatus::class)],
            'verification_status' => ['sometimes', 'string', Rule::enum(SupplierVerificationStatus::class)],
            'onboarding_status' => ['sometimes', 'string', Rule::enum(SupplierOnboardingStatus::class)],
            'show_public_contact' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', 'string', Rule::enum(SupplierVisibility::class)],
            'availability' => ['nullable', 'string', 'max:40'],
            'delivery_time' => ['nullable', 'string', 'max:120'],
            'service_areas' => ['nullable', 'array'],
            'service_areas.*' => ['string', 'max:120'],
            'certifications' => ['nullable', 'array'],
            'certifications.*' => ['string', 'max:255'],
            'save_as_draft' => ['sometimes', 'boolean'],
            'company_id' => ['nullable', 'integer', 'exists:crm_companies,id'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'account_password' => ['required_with:account_email', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
