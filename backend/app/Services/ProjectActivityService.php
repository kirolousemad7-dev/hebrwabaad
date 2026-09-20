<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectActivityAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProjectActivityService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        Project $project,
        string $action,
        ?User $actor = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $metadata = null,
        bool $isClientVisible = false,
    ): ProjectActivity {
        $actorUserId = null;
        $actorCustomerId = null;

        if ($actor !== null) {
            if ($actor->role === UserRole::Customer) {
                $actorCustomerId = $actor->id;
            } else {
                $actorUserId = $actor->id;
            }
        }

        /** @var ProjectActivity $activity */
        $activity = ProjectActivity::query()->create([
            'project_id' => $project->id,
            'actor_user_id' => $actorUserId,
            'actor_customer_id' => $actorCustomerId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $this->sanitizeMetadata($metadata),
            'is_client_visible' => $isClientVisible,
        ]);

        return $activity;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordUserAction(
        Project $project,
        User $user,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $metadata = null,
        bool $isClientVisible = false,
    ): ProjectActivity {
        return $this->record(
            project: $project,
            action: $action,
            actor: $user,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            metadata: $metadata,
            isClientVisible: $isClientVisible,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordCustomerAction(
        Project $project,
        User $customer,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $metadata = null,
        bool $isClientVisible = true,
    ): ProjectActivity {
        return $this->record(
            project: $project,
            action: $action,
            actor: $customer,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            metadata: $metadata,
            isClientVisible: $isClientVisible,
        );
    }

    /**
     * @return Builder<ProjectActivity>
     */
    public function forProject(Project $project, bool $clientVisibleOnly = false): Builder
    {
        $query = ProjectActivity::query()
            ->with(['actorUser:id,name', 'actorCustomer:id,name'])
            ->where('project_id', $project->id);

        if ($clientVisibleOnly) {
            $query->where('is_client_visible', true)
                ->whereIn('action', ProjectActivityAction::customerSafeActions());
        }

        return $query->latest('id');
    }

    /**
     * @return Collection<int, ProjectActivity>
     */
    public function recentForProject(Project $project, int $limit = 20, bool $clientVisibleOnly = false): Collection
    {
        return $this->forProject($project, $clientVisibleOnly)
            ->limit(max(1, min($limit, 100)))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProjectActivity>
     */
    public function paginateForProject(Project $project, array $filters = [], bool $clientVisibleOnly = false): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 50));

        return $this->forProject($project, $clientVisibleOnly)->paginate($perPage);
    }

    /**
     * Bridge high-signal calendar events onto the project activity feed.
     * Skips items that are not project-linked to avoid duplicating unrelated calendar noise.
     */
    public function recordFromCalendarItem(CalendarItem $item, ?User $actor, string $calendarAction): void
    {
        $projectId = $this->resolveProjectIdFromCalendarItem($item);
        if ($projectId === null) {
            return;
        }

        $project = Project::query()->find($projectId);
        if ($project === null) {
            return;
        }

        $action = match ($calendarAction) {
            'created' => ProjectActivityAction::CALENDAR_ITEM_CREATED,
            'completed' => ProjectActivityAction::CALENDAR_ITEM_COMPLETED,
            'updated', 'rescheduled' => ProjectActivityAction::CALENDAR_ITEM_UPDATED,
            default => null,
        };

        if ($action === null) {
            return;
        }

        $this->record(
            project: $project,
            action: $action,
            actor: $actor,
            entityType: 'calendar_item',
            entityId: (int) $item->id,
            description: match ($action) {
                ProjectActivityAction::CALENDAR_ITEM_CREATED => 'Calendar item created',
                ProjectActivityAction::CALENDAR_ITEM_COMPLETED => 'Calendar item completed',
                default => 'Calendar item updated',
            },
            metadata: [
                'title' => $item->title,
                'calendar_action' => $calendarAction,
                'related_type' => $item->related_type,
                'related_id' => $item->related_id,
            ],
            isClientVisible: false,
        );
    }

    public function recordFileUploaded(ManagedFile $file, User $actor): void
    {
        if ($file->project_id === null) {
            return;
        }

        $project = $file->relationLoaded('project')
            ? $file->project
            : Project::query()->find($file->project_id);

        if ($project === null) {
            return;
        }

        $isCustomer = $actor->role === UserRole::Customer;
        $visible = $isCustomer || (bool) $file->is_client_visible;

        $this->record(
            project: $project,
            action: $isCustomer
                ? ProjectActivityAction::CUSTOMER_FILE_UPLOADED
                : ProjectActivityAction::FILE_UPLOADED,
            actor: $actor,
            entityType: 'file',
            entityId: (int) $file->id,
            description: $isCustomer ? 'Customer uploaded a file' : 'File uploaded',
            metadata: [
                'file_name' => $file->original_name,
                'is_client_visible' => (bool) $file->is_client_visible,
                'mime_type' => $file->mime_type,
                'extension' => $file->extension,
            ],
            isClientVisible: $visible,
        );
    }

    public function recordFileClientVisibilityChanged(
        ManagedFile $file,
        User $actor,
        bool $oldVisible,
        bool $newVisible,
    ): void {
        if ($file->project_id === null || $oldVisible === $newVisible) {
            return;
        }

        $project = $file->relationLoaded('project')
            ? $file->project
            : Project::query()->find($file->project_id);

        if ($project === null) {
            return;
        }

        $this->recordUserAction(
            project: $project,
            user: $actor,
            action: ProjectActivityAction::FILE_CLIENT_VISIBILITY_CHANGED,
            entityType: 'file',
            entityId: (int) $file->id,
            description: 'File client visibility changed',
            metadata: [
                'file_name' => $file->original_name,
                'old_is_client_visible' => $oldVisible,
                'new_is_client_visible' => $newVisible,
            ],
            isClientVisible: $newVisible,
        );
    }

    private function resolveProjectIdFromCalendarItem(CalendarItem $item): ?int
    {
        if ($item->related_type === 'project' && $item->related_id) {
            return (int) $item->related_id;
        }

        if ($item->related_type === 'workspace_task' && $item->related_id) {
            $taskProjectId = Task::query()->whereKey((int) $item->related_id)->value('project_id');

            return $taskProjectId !== null ? (int) $taskProjectId : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $blocked = [
            'password', 'token', 'secret', 'authorization', 'api_key', 'access_token',
            'refresh_token', 'credentials', 'path', 'disk', 'stored_name', 'content',
        ];

        $clean = [];
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $blocked, true)) {
                continue;
            }
            if (is_string($value) && strlen($value) > 2000) {
                $value = substr($value, 0, 2000);
            }
            $clean[$key] = $value;
        }

        return $clean === [] ? null : $clean;
    }
}
