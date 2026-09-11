<?php

namespace App\Http\Resources;

use App\Models\WorkSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkSubmission
 */
class WorkSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isReviewer = $user?->canReviewContent() === true;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category?->value,
            'service_id' => $this->service_id,
            'cover_media_id' => $this->cover_media_id,
            'cover_url' => $this->coverUrl(),
            'gallery_media_ids' => $this->gallery_media_ids ?? [],
            'tags' => $this->tags ?? [],
            'tools' => $this->tools ?? [],
            'project_url' => $this->project_url,
            'video_url' => $this->video_url,
            'client_label' => $this->client_label,
            'employee_notes' => $this->employee_notes,
            'status' => $this->status?->value,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'employee' => $this->when($isReviewer && $this->relationLoaded('employee'), fn () => [
                'id' => $this->employee?->id,
                'name' => $this->employee?->name,
                'role' => $this->employee?->role?->value,
            ]),
        ];
    }
}
