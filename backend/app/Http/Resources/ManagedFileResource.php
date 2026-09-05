<?php

namespace App\Http\Resources;

use App\Models\ManagedFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ManagedFile
 */
class ManagedFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'can_preview' => $this->isPreviewable(),
            'created_at' => $this->created_at?->toIso8601String(),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->id,
                'title' => $this->project->title,
            ]),
            'order' => $this->whenLoaded('order', fn () => $this->order === null ? null : [
                'id' => $this->order->id,
                'reference' => $this->order->reference,
            ]),
            'task' => $this->whenLoaded('task', fn () => $this->task === null ? null : [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ]),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader === null ? null : [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),
        ];
    }
}
