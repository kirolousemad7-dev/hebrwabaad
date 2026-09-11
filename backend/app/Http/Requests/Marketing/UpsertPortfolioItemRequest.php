<?php

namespace App\Http\Requests\Marketing;

use App\Enums\PortfolioCategory;
use App\Enums\PortfolioMediaType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertPortfolioItemRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('portfolioItem');
        $itemId = is_object($item) ? $item->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('portfolio_items', 'slug')->ignore($itemId)],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(PortfolioCategory::values())],
            'description' => ['nullable', 'string', 'max:5000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'challenge' => ['nullable', 'string', 'max:5000'],
            'solution' => ['nullable', 'string', 'max:5000'],
            'execution' => ['nullable', 'string', 'max:5000'],
            'deliverables' => ['nullable', 'array'],
            'deliverables.*' => ['string', 'max:255'],
            'results' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'image_url' => [$itemId ? 'sometimes' : 'required', 'string', 'max:2048', 'regex:/^(https:\/\/|\/)(?!\/)/i'],
            'project_url' => ['nullable', 'string', 'max:2048'],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'primary_media_type' => ['nullable', Rule::in(PortfolioMediaType::values())],
            'is_sample' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'sector_ids' => ['nullable', 'array'],
            'sector_ids.*' => ['integer', 'exists:sectors,id'],
            'media' => ['nullable', 'array', 'max:40'],
            'media.*.id' => ['nullable', 'integer'],
            'media.*.type' => ['required_with:media', Rule::in(PortfolioMediaType::values())],
            'media.*.title' => ['nullable', 'string', 'max:255'],
            'media.*.caption' => ['nullable', 'string', 'max:1000'],
            'media.*.url' => ['nullable', 'string', 'max:2048'],
            'media.*.thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'media.*.content_media_id' => ['nullable', 'uuid', 'exists:content_media,id'],
            'media.*.thumbnail_media_id' => ['nullable', 'uuid', 'exists:content_media,id'],
            'media.*.display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'media.*.is_featured' => ['sometimes', 'boolean'],
            'media.*.is_public' => ['sometimes', 'boolean'],
        ];
    }
}
