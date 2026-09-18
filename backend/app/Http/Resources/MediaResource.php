<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Services\Media\MediaEntityRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registry = app(MediaEntityRegistry::class);
        $entityType = $this->owner_type;
        if ($this->relationLoaded('owner') && $this->owner !== null) {
            $entityType = $registry->entityTypeFor($this->owner) ?? $this->owner_type;
        }

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'visibility' => $this->visibility instanceof \BackedEnum
                ? $this->visibility->value
                : $this->visibility,
            'can_preview' => $this->isPreviewable(),
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPdf(),
            'is_video' => $this->isVideo(),
            'metadata' => $this->metadata ?? [],
            'entity_type' => $entityType,
            'entity_id' => $this->owner_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
        ];
    }
}
