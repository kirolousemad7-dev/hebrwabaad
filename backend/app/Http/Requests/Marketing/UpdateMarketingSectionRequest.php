<?php

namespace App\Http\Requests\Marketing;

use App\Enums\MarketingSectionType;
use App\Http\Requests\ApiFormRequest;
use App\Services\Marketing\MarketingMediaLibraryService;
use Illuminate\Validation\Rule;

class UpdateMarketingSectionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(MarketingSectionType::values())],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'config' => ['sometimes', 'nullable', 'array'],
            'contents' => ['sometimes', 'array'],
            'contents.*.content_key' => ['required_with:contents', 'string', 'max:96'],
            'contents.*.value_text' => ['sometimes', 'nullable', 'string'],
            'contents.*.value_html' => ['sometimes', 'nullable', 'string'],
            'contents.*.media_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('media', 'id')->where(
                    'collection',
                    MarketingMediaLibraryService::COLLECTION,
                ),
            ],
            'contents.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'contents.*.is_enabled' => ['sometimes', 'boolean'],
            'contents.*.metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
