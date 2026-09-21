<?php

namespace App\Http\Requests\Marketing;

use App\Http\Requests\ApiFormRequest;
use App\Services\Marketing\MarketingMediaLibraryService;

class StoreMarketingMediaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = MarketingMediaLibraryService::MAX_KILOBYTES;

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:'.implode(',', MarketingMediaLibraryService::ALLOWED_MIMES),
            ],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'section_key' => ['sometimes', 'nullable', 'string', 'max:64', 'exists:marketing_sections,key'],
            'source_key' => ['sometimes', 'nullable', 'string', 'max:96'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
