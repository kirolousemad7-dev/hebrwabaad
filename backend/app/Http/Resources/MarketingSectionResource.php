<?php

namespace App\Http\Resources;

use App\Enums\MarketingSectionType;
use App\Enums\MediaVisibility;
use App\Models\MarketingContent;
use App\Models\MarketingSection;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MarketingSection
 */
class MarketingSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $request->is('api/owner/*');
        $type = $this->type instanceof MarketingSectionType
            ? $this->type->value
            : (string) $this->type;

        $payload = [
            'key' => $this->key,
            'type' => $type,
            'sort_order' => (int) $this->sort_order,
            'contents' => $this->whenLoaded('contents', function () use ($request, $isOwner) {
                return $this->contents
                    ->when(! $isOwner, fn ($c) => $c->where('is_enabled', true)->values())
                    ->map(fn (MarketingContent $content) => $this->mapContent($content, $isOwner, $request))
                    ->values()
                    ->all();
            }),
        ];

        if ($isOwner) {
            $payload['id'] = $this->id;
            $payload['admin_title'] = $this->admin_title;
            $payload['is_enabled'] = (bool) $this->is_enabled;
            $payload['config'] = $this->config;
            $payload['type_label'] = $this->type instanceof MarketingSectionType
                ? $this->type->labelAr()
                : null;
            $payload['created_at'] = $this->created_at?->toIso8601String();
            $payload['updated_at'] = $this->updated_at?->toIso8601String();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapContent(MarketingContent $content, bool $isOwner, Request $request): array
    {
        $row = [
            'key' => $content->content_key,
            'text' => $content->value_text,
            'html' => $content->value_html,
            'media' => $this->mapMedia($content->media, $isOwner),
            'sort_order' => (int) $content->sort_order,
        ];

        if ($isOwner) {
            $row['id'] = $content->id;
            $row['content_key'] = $content->content_key;
            $row['value_text'] = $content->value_text;
            $row['value_html'] = $content->value_html;
            $row['media_id'] = $content->media_id;
            $row['is_enabled'] = (bool) $content->is_enabled;
            $row['metadata'] = $content->metadata;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapMedia(?Media $media, bool $isOwner): ?array
    {
        if ($media === null) {
            return null;
        }

        $meta = $media->metadata ?? [];
        $isActive = array_key_exists('is_active', $meta) ? (bool) $meta['is_active'] : true;

        if (! $isOwner && (! $isActive || $media->visibilityEnum() !== MediaVisibility::Public)) {
            return null;
        }

        $localPath = is_string($meta['local_public_path'] ?? null) ? $meta['local_public_path'] : null;
        $url = $localPath ?: $media->url();

        $payload = [
            'url' => $url,
            'alt' => (string) ($meta['alt_text'] ?? ''),
            'title' => (string) ($meta['title'] ?? ''),
            'width' => isset($meta['width']) ? (int) $meta['width'] : null,
            'height' => isset($meta['height']) ? (int) $meta['height'] : null,
        ];

        if ($isOwner) {
            $payload['id'] = $media->id;
            $payload['original_name'] = $media->original_name;
            $payload['mime_type'] = $media->mime_type;
            $payload['size'] = $media->size;
            $payload['is_active'] = $isActive;
            $payload['local_public_path'] = $localPath;
            $payload['source_key'] = $meta['source_key'] ?? null;
            $payload['is_registry_only'] = (bool) ($meta['is_registry_only'] ?? false);
        }

        return $payload;
    }
}
