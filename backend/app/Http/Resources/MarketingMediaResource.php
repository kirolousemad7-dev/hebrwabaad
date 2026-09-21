<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Services\Marketing\MarketingMediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner-facing marketing media library resource (MediaAsset).
 *
 * @mixin Media
 */
class MarketingMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = $this->metadata ?? [];
        $localPath = is_string($meta['local_public_path'] ?? null) ? $meta['local_public_path'] : null;
        $library = app(MarketingMediaLibraryService::class);

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'url' => $localPath ?: $this->url(),
            'alt_text' => (string) ($meta['alt_text'] ?? ''),
            'title' => (string) ($meta['title'] ?? ''),
            'width' => isset($meta['width']) ? (int) $meta['width'] : null,
            'height' => isset($meta['height']) ? (int) $meta['height'] : null,
            'is_active' => array_key_exists('is_active', $meta) ? (bool) $meta['is_active'] : true,
            'local_public_path' => $localPath,
            'source_key' => $meta['source_key'] ?? null,
            'is_registry_only' => (bool) ($meta['is_registry_only'] ?? false),
            'usage_count' => $library->usageCount($this->resource),
            'usages' => $library->usages($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
