<?php

namespace App\Http\Requests\Cms;

use App\Enums\CmsPageType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreCmsPageRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('cms_pages', 'slug')],
            'content' => ['required', 'string'],
            'page_type' => ['required', 'string', Rule::in(CmsPageType::values())],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'],
            'og_image' => ['nullable', 'string', 'max:2048'],
            'is_published' => ['sometimes', 'boolean'],
            'show_in_footer' => ['sometimes', 'boolean'],
            'footer_group' => ['nullable', 'string', 'max:100'],
            'footer_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
