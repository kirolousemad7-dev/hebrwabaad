<?php

namespace App\Services;

use App\Enums\ContentReviewAction;
use App\Enums\ContentStatus;
use App\Models\ContentReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContentReviewLogger
{
    public function record(
        Model $subject,
        User $actor,
        ContentReviewAction $action,
        ?ContentStatus $from,
        ?ContentStatus $to,
        ?string $notes = null,
    ): ContentReview {
        return ContentReview::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
