<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierCategoryRequest extends ApiFormRequest
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
        $categoryId = $this->route('category')?->id ?? $this->route('category');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('supplier_categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
