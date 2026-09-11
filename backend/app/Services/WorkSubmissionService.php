<?php

namespace App\Services;

use App\Enums\ContentReviewAction;
use App\Enums\ContentStatus;
use App\Enums\PortfolioCategory;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Models\WorkSubmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WorkSubmissionService
{
    public function __construct(
        private readonly ContentReviewLogger $logger,
        private readonly ContentMediaService $media,
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WorkSubmission>
     */
    public function paginateForEmployee(User $user, array $filters): LengthAwarePaginator
    {
        return WorkSubmission::query()
            ->with(['coverMedia', 'service'])
            ->where('user_id', $user->id)
            ->when(
                is_string($filters['status'] ?? null) && in_array($filters['status'], ContentStatus::values(), true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->orderByDesc('updated_at')
            ->paginate(min(50, max(1, (int) ($filters['per_page'] ?? 15))));
    }

    /**
     * @return array<string, int>
     */
    public function countsForEmployee(User $user): array
    {
        $rows = WorkSubmission::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return $this->summarizeCounts($rows->all());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WorkSubmission>
     */
    public function paginateForReview(array $filters): LengthAwarePaginator
    {
        return WorkSubmission::query()
            ->with(['employee', 'coverMedia', 'service', 'reviewer'])
            ->when(
                isset($filters['employee_id']),
                fn ($query) => $query->where('user_id', (int) $filters['employee_id']),
            )
            ->when(
                is_string($filters['role'] ?? null),
                fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('role', $filters['role'])),
            )
            ->when(
                is_string($filters['category'] ?? null) && in_array($filters['category'], PortfolioCategory::values(), true),
                fn ($query) => $query->where('category', $filters['category']),
            )
            ->when(
                is_string($filters['status'] ?? null) && in_array($filters['status'], ContentStatus::values(), true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->orderByDesc('updated_at')
            ->paginate(min(50, max(1, (int) ($filters['per_page'] ?? 15))));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload): WorkSubmission
    {
        $this->assertContributor($user);

        $work = WorkSubmission::query()->create([
            ...$this->sanitizePayload($payload),
            'user_id' => $user->id,
            'status' => ContentStatus::Draft,
        ]);

        $this->syncMedia($user, $work, $payload);
        $this->logger->record($work, $user, ContentReviewAction::Created, null, ContentStatus::Draft);

        return $work->refresh()->load(['coverMedia', 'service']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $user, WorkSubmission $work, array $payload): WorkSubmission
    {
        $this->assertOwner($user, $work);

        if (! $work->status->contributorCanEdit()) {
            throw new ContentWorkflowException('لا يمكن تعديل العمل في هذه الحالة.');
        }

        $work->fill($this->sanitizePayload($payload));
        $work->save();
        $this->syncMedia($user, $work, $payload);

        return $work->refresh()->load(['coverMedia', 'service']);
    }

    public function submit(User $user, WorkSubmission $work): WorkSubmission
    {
        $this->assertOwner($user, $work);

        if (! $work->status->contributorCanSubmit()) {
            throw new ContentWorkflowException('لا يمكن إرسال العمل في هذه الحالة.');
        }

        $from = $work->status;
        $work->forceFill([
            'status' => ContentStatus::Submitted,
            'review_notes' => null,
        ])->save();

        $this->logger->record($work, $user, ContentReviewAction::Submitted, $from, ContentStatus::Submitted);
        $this->notifier->employeeWorkSubmitted($work->fresh(['employee']));

        return $work->refresh()->load(['coverMedia', 'service']);
    }

    public function delete(User $user, WorkSubmission $work): void
    {
        $this->assertOwner($user, $work);

        if (! $work->status->contributorCanDelete()) {
            throw new ContentWorkflowException('يمكن حذف المسودات فقط.');
        }

        $work->delete();
    }

    public function markUnderReview(User $reviewer, WorkSubmission $work): WorkSubmission
    {
        $this->assertReviewer($reviewer);

        if ($work->status === ContentStatus::Submitted) {
            $from = $work->status;
            $work->forceFill(['status' => ContentStatus::UnderReview])->save();
            $this->logger->record($work, $reviewer, ContentReviewAction::Reviewed, $from, ContentStatus::UnderReview);
        }

        return $work->refresh()->load(['employee', 'coverMedia', 'service', 'reviewer', 'reviews.actor']);
    }

    public function approveAndPublish(User $reviewer, WorkSubmission $work): WorkSubmission
    {
        $this->assertReviewer($reviewer);

        if (! in_array($work->status, [ContentStatus::Submitted, ContentStatus::UnderReview, ContentStatus::Approved], true)) {
            throw new ContentWorkflowException('لا يمكن نشر العمل في هذه الحالة.');
        }

        $from = $work->status;
        $item = $this->upsertPortfolioItem($work);

        $work->forceFill([
            'status' => ContentStatus::Published,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'published_at' => now(),
            'published_portfolio_item_id' => $item->id,
            'review_notes' => null,
        ])->save();

        $item->forceFill([
            'work_submission_id' => $work->id,
            'is_published' => true,
            'is_sample' => false,
        ])->save();

        $this->logger->record($work, $reviewer, ContentReviewAction::Published, $from, ContentStatus::Published);
        $this->notifier->employeeWorkPublished($work->fresh(['employee']));

        return $work->refresh()->load(['employee', 'coverMedia', 'publishedPortfolioItem']);
    }

    public function reject(User $reviewer, WorkSubmission $work, string $notes): WorkSubmission
    {
        return $this->returnToContributor($reviewer, $work, ContentStatus::Rejected, ContentReviewAction::Rejected, $notes);
    }

    public function requestChanges(User $reviewer, WorkSubmission $work, string $notes): WorkSubmission
    {
        return $this->returnToContributor($reviewer, $work, ContentStatus::ChangesRequested, ContentReviewAction::ChangesRequested, $notes);
    }

    public function unpublish(User $reviewer, WorkSubmission $work): WorkSubmission
    {
        $this->assertReviewer($reviewer);

        if ($work->status !== ContentStatus::Published) {
            throw new ContentWorkflowException('العمل غير منشور.');
        }

        $from = $work->status;
        $work->forceFill(['status' => ContentStatus::Archived])->save();
        $work->publishedPortfolioItem?->forceFill(['is_published' => false])->save();
        $this->logger->record($work, $reviewer, ContentReviewAction::Unpublished, $from, ContentStatus::Archived);

        return $work->refresh();
    }

    public function archive(User $reviewer, WorkSubmission $work): WorkSubmission
    {
        $this->assertReviewer($reviewer);
        $from = $work->status;
        $work->forceFill(['status' => ContentStatus::Archived])->save();
        $work->publishedPortfolioItem?->forceFill(['is_published' => false])->save();
        $this->logger->record($work, $reviewer, ContentReviewAction::Archived, $from, ContentStatus::Archived);

        return $work->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        unset($payload['status'], $payload['user_id'], $payload['reviewed_by'], $payload['published_at'], $payload['published_portfolio_item_id']);

        return [
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'category' => $payload['category'],
            'service_id' => $payload['service_id'] ?? null,
            'cover_media_id' => $payload['cover_media_id'] ?? null,
            'gallery_media_ids' => array_values($payload['gallery_media_ids'] ?? []),
            'tags' => array_values($payload['tags'] ?? []),
            'tools' => array_values($payload['tools'] ?? []),
            'project_url' => $payload['project_url'] ?? null,
            'video_url' => $payload['video_url'] ?? null,
            'client_label' => $payload['client_label'] ?? null,
            'employee_notes' => $payload['employee_notes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncMedia(User $user, WorkSubmission $work, array $payload): void
    {
        if (isset($payload['cover_media_id']) && is_string($payload['cover_media_id'])) {
            $cover = $this->media->ownedBy($user, $payload['cover_media_id']);
            $this->media->attach($cover, $work, 'cover');
            $work->forceFill(['cover_media_id' => $cover->id])->save();
        }

        foreach ($payload['gallery_media_ids'] ?? [] as $uuid) {
            if (! is_string($uuid)) {
                continue;
            }
            $item = $this->media->ownedBy($user, $uuid);
            $this->media->attach($item, $work, 'gallery');
        }
    }

    private function upsertPortfolioItem(WorkSubmission $work): PortfolioItem
    {
        $work->loadMissing('coverMedia');

        $attributes = [
            'title' => $work->title,
            'category' => $work->category,
            'description' => $work->description,
            'tags' => $work->tags ?? [],
            'image_url' => $work->coverUrl() ?? '/brand/logo.png',
            'is_sample' => false,
            'is_published' => true,
            'sort_order' => 0,
            'work_submission_id' => $work->id,
        ];

        $existing = $work->published_portfolio_item_id
            ? PortfolioItem::query()->find($work->published_portfolio_item_id)
            : PortfolioItem::query()->where('work_submission_id', $work->id)->first();

        if ($existing) {
            $existing->fill($attributes)->save();

            return $existing;
        }

        return PortfolioItem::query()->create($attributes);
    }

    private function returnToContributor(
        User $reviewer,
        WorkSubmission $work,
        ContentStatus $next,
        ContentReviewAction $action,
        string $notes,
    ): WorkSubmission {
        $this->assertReviewer($reviewer);

        if (! in_array($work->status, [ContentStatus::Submitted, ContentStatus::UnderReview], true)) {
            throw new ContentWorkflowException('لا يمكن مراجعة العمل في هذه الحالة.');
        }

        $from = $work->status;
        $work->forceFill([
            'status' => $next,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->logger->record($work, $reviewer, $action, $from, $next, $notes);

        if ($next === ContentStatus::Rejected) {
            $this->notifier->employeeWorkRejected($work->fresh(['employee']), $notes);
        } else {
            $this->notifier->employeeWorkChangesRequested($work->fresh(['employee']), $notes);
        }

        return $work->refresh()->load(['employee', 'coverMedia']);
    }

    private function assertContributor(User $user): void
    {
        if (! $user->canSubmitEmployeeWork()) {
            throw new ContentWorkflowException('Forbidden.', 403);
        }
    }

    private function assertOwner(User $user, WorkSubmission $work): void
    {
        $this->assertContributor($user);

        if ((int) $work->user_id !== (int) $user->id) {
            throw new ContentWorkflowException('Forbidden.', 403);
        }
    }

    private function assertReviewer(User $user): void
    {
        if (! $user->canReviewContent()) {
            throw new ContentWorkflowException('Forbidden.', 403);
        }
    }

    /**
     * @param  array<string, mixed>  $rows
     * @return array<string, int>
     */
    private function summarizeCounts(array $rows): array
    {
        $value = static fn (ContentStatus $status): int => (int) ($rows[$status->value] ?? 0);

        return [
            'drafts' => $value(ContentStatus::Draft),
            'under_review' => $value(ContentStatus::Submitted) + $value(ContentStatus::UnderReview),
            'published' => $value(ContentStatus::Published),
            'needs_changes' => $value(ContentStatus::ChangesRequested) + $value(ContentStatus::Rejected),
        ];
    }
}
