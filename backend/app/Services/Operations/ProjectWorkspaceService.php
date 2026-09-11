<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use App\Services\Catalog\CustomPackageProjectOverviewService;
use App\Services\Operations\Work\UnifiedWorkService;
use App\Services\ProjectService;
use Carbon\Carbon;

class ProjectWorkspaceService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectHealthService $health,
        private readonly CalendarService $calendar,
        private readonly ProjectTimelineService $projectTimeline,
        private readonly ProjectMilestoneService $milestones,
        private readonly UnifiedWorkService $unifiedWork,
        private readonly CustomPackageProjectOverviewService $customPackageOverview,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, Project $project): array
    {
        $project = $this->projects->load($project)->load(['members.user:id,name,role', 'accountManager:id,name', 'customer:id,name']);

        $calendarTasks = CalendarItem::query()
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('type', CalendarItemType::Task->value)
            ->get();

        $calendarCounts = [
            'total' => $calendarTasks->count(),
            'open' => $calendarTasks->filter(fn (CalendarItem $item) => $item->isOpen())->count(),
            'completed' => $calendarTasks->filter(function (CalendarItem $item): bool {
                $status = $item->status instanceof CalendarItemStatus ? $item->status->value : (string) $item->status;

                return $status === CalendarItemStatus::Completed->value;
            })->count(),
            'overdue' => $calendarTasks->filter(function (CalendarItem $item): bool {
                $status = $item->status instanceof CalendarItemStatus ? $item->status->value : (string) $item->status;

                return $status === CalendarItemStatus::Overdue->value;
            })->count(),
        ];

        $unifiedScope = ($actor->role instanceof UserRole && $actor->role->canManageTeamWork()) ? 'team' : 'mine';
        $unifiedOpen = $this->unifiedWork->list($actor, [
            'scope' => $unifiedScope,
            'bucket' => 'open',
            'project_id' => $project->id,
            'page' => 1,
            'per_page' => 1,
            'sort' => 'overdue_first',
        ]);
        $unifiedOverdue = $this->unifiedWork->list($actor, [
            'scope' => $unifiedScope,
            'bucket' => 'overdue',
            'project_id' => $project->id,
            'page' => 1,
            'per_page' => 1,
            'sort' => 'overdue_first',
        ]);
        $unifiedCounts = [
            'open' => (int) ($unifiedOpen['meta']['total'] ?? 0),
            'overdue' => (int) ($unifiedOverdue['meta']['total'] ?? 0),
        ];

        $upcoming = CalendarItem::query()
            ->with(['assignees:id,name', 'creator:id,name'])
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('starts_at', '>=', now()->startOfDay())
            ->whereNotIn('status', [CalendarItemStatus::Cancelled->value, CalendarItemStatus::Completed->value])
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (CalendarItem $item) => $this->calendar->serialize($item))
            ->all();

        $members = $project->members->map(fn ($member) => [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'role' => $member->role,
            'user' => $member->user ? [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'role' => $member->user->role?->value ?? $member->user->role,
            ] : null,
        ])->values()->all();

        return [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'status' => $project->status instanceof ProjectStatus
                    ? $project->status->value
                    : $project->status,
                'deadline' => $project->deadline?->toDateString(),
                'started_at' => $project->started_at?->toDateString(),
                'customer' => $project->customer ? [
                    'id' => $project->customer->id,
                    'name' => $project->customer->name,
                ] : null,
                'account_manager' => $project->accountManager ? [
                    'id' => $project->accountManager->id,
                    'name' => $project->accountManager->name,
                ] : null,
            ],
            'progress' => $project->progress(),
            'required_services' => $this->customPackageOverview->serviceLines($project, forCustomer: false),
            'calendar_tasks' => $calendarCounts,
            'unified_work' => $unifiedCounts,
            'members' => $members,
            'files_count' => ManagedFile::query()->where('project_id', $project->id)->count(),
            'health' => $this->health->evaluate($project),
            'upcoming_calendar_items' => $upcoming,
            'timeline_preview' => $this->projectTimeline->forProject($project, 'all', 10),
            'milestones' => $this->milestones->list($project),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(Project $project, string $filter = 'all'): array
    {
        return $this->projectTimeline->forProject($project, $filter, 100);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendarItems(User $actor, Project $project, Carbon $from, Carbon $to): array
    {
        $items = CalendarItem::query()
            ->with(['creator:id,name', 'assignees:id,name,role', 'reminders'])
            ->withCount(['comments', 'files'])
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('starts_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->whereNotNull('ends_at')
                            ->where('starts_at', '<=', $to)
                            ->where('ends_at', '>=', $from);
                    });
            })
            ->orderBy('starts_at')
            ->get();

        return $items->map(fn (CalendarItem $item) => $this->calendar->serialize($item))->all();
    }
}
