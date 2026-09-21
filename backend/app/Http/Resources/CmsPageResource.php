<?php

namespace App\Http\Resources;

use App\Enums\CmsPageType;
use App\Models\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CmsPage
 */
class CmsPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pageType = $this->page_type instanceof \BackedEnum
            ? $this->page_type->value
            : (string) $this->page_type;

        $isOwnerContext = $request->is('api/owner/*');

        $payload = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'page_type' => $pageType,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'path' => $this->publicPath(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($isOwnerContext) {
            $payload['is_published'] = (bool) $this->is_published;
            $payload['show_in_footer'] = (bool) $this->show_in_footer;
            $payload['footer_group'] = $this->footer_group;
            $payload['footer_order'] = (int) $this->footer_order;
            $payload['is_system'] = $this->isSystem();
            $payload['created_at'] = $this->created_at?->toIso8601String();

            if ($this->page_type instanceof CmsPageType) {
                $payload['page_type_label'] = $this->page_type->labelAr();
            }
        }

        return $payload;
    }
}
