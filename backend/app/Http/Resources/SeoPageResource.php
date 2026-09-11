<?php

namespace App\Http\Resources;

use App\Models\SeoPage;
use App\Support\SeoPages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SeoPage
 */
class SeoPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = SeoPages::labels();

        return [
            'page_key' => $this->page_key,
            'label' => $labels[$this->page_key] ?? $this->page_key,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title ?: $this->title,
            'og_description' => $this->og_description ?: $this->description,
            'og_image' => $this->og_image,
            'twitter_title' => $this->twitter_title ?: $this->og_title ?: $this->title,
            'twitter_description' => $this->twitter_description ?: $this->og_description ?: $this->description,
            'twitter_image' => $this->twitter_image ?: $this->og_image,
            'robots' => $this->robots,
            'schema_json' => $this->schema_json,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
