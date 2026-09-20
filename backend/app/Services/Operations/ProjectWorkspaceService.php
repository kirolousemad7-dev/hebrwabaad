<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use App\Services\Catalog\CustomPackageProjectOverviewService;
use App\Services\Operations\Work\UnifiedWorkService;
use App\Services\ProjectService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class ProjectWorkspaceService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectHealthService $health,
        private readonly CalendarService $calendar,
        private readonly ProjectTimelineService $projectTimeline,
        private readonly ProjectMilestoneService $milestones,
        private readonly ProjectPhaseService $phases,
        private readonly ProjectBriefService $brief,
        private readonly UnifiedWorkService $unifiedWork,
        private readonly CustomPackageProjectOverviewService $customPackageOverview,
        private readonly ProjectExecutionService $execution,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, Project $project): array
    {
        $project = $this->projects->load($project)->load([
            'members.user:id,name,role',
            'accountManager:id,name,role',
            'customer:id,name,email',
            'phases.responsible:id,name',
        ]);

        $taskIds = Task::query()->where('project_id', $project->id)->pluck('id');

        $calendarTasks = CalendarItem::query()
            ->where(function ($query) use ($project, $taskIds): void {
                $query->where(function ($inner) use ($project): void {
                    $inner->where('related_type', 'project')
                        ->where('related_id', $project->id);
                })->orWhere(function ($inner) use ($taskIds): void {
                    $inner->where('related_type', 'workspace_task')
                        ->whereIn('related_id', $taskIds);
                });
            })
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
            ->where(function ($query) use ($project, $taskIds): void {
                $query->where(function ($inner) use ($project): void {
                    $inner->where('related_type', 'project')
                        ->where('related_id', $project->id);
                })->orWhere(function ($inner) use ($taskIds): void {
                    $inner->where('related_type', 'workspace_task')
                        ->whereIn('related_id', $taskIds);
                });
            })
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

        $metadata = $this->brief->metadata($project);
        $structure = $this->phases->structure($project);
        $snapshot = $this->operationalSnapshot($project);
        $teamStats = $this->teamStats($project, $members);
        $attention = $this->execution->attention($project);
        $recentActivity = $this->projectTimeline->forProject($project, 'all', 12);
        $executionSummary = $this->execution->executionSummary(
            $project,
            $attention,
            $snapshot['next_deadline'],
        );
        $executionSummary['recent_activity_count'] = count($recentActivity);

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
                    'email' => $project->customer->email,
                ] : null,
                'account_manager' => $project->accountManager ? [
                    'id' => $project->accountManager->id,
                    'name' => $project->accountManager->name,
                ] : null,
            ],
            'brief' => $metadata['brief'],
            'client_profile' => $metadata['client_profile'],
            'requirements' => $metadata['requirements'],
            'scope' => $metadata['scope'],
            'references' => $this->brief->listReferences($project),
            'phases' => $this->phases->list($project),
            'structure' => $structure,
            'current_phase' => $snapshot['current_phase'],
            'next_milestone' => $snapshot['next_milestone'],
            'next_task' => $snapshot['next_task'],
            'next_deadline' => $snapshot['next_deadline'],
            'risks' => $snapshot['risks'],
            'progress' => $project->progress(),
            'team_stats' => $teamStats,
            'required_services' => $this->customPackageOverview->serviceLines($project, forCustomer: false),
            'calendar_tasks' => $calendarCounts,
            'unified_work' => $unifiedCounts,
            'members' => $members,
            'files_count' => ManagedFile::query()->where('project_id', $project->id)->count(),
            'health' => $this->health->evaluate($project),
            'upcoming_calendar_items' => $upcoming,
            'timeline_preview' => $recentActivity,
            'recent_activity' => $recentActivity,
            'milestones' => $this->milestones->list($project),
            'execution_summary' => $executionSummary,
            'attention' => $attention,
            'deliverables' => $this->execution->deliverables($project, forCustomer: false),
            'pending_approvals' => $this->execution->pendingApprovals($project),
            'closure_readiness' => $executionSummary['closure'],
        ];
    }

    /**
     * Client-facing progress payload (authorization enforced by caller).
     *
     * @return array<string, mixed>
     */
    public function clientProgress(Project $project): array
    {
        $project->loadMissing(['accountManager:id,name', 'phases', 'milestones']);
        $progress = $project->progress(clientVisibleOnly: true);
        $metadata = $this->brief->clientVisibleMetadata($project);

        $phases = ProjectPhase::query()
            ->where('project_id', $project->id)
            ->where('is_client_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectPhase $phase) => [
                'id' => $phase->id,
                'title' => $phase->title,
                'status' => $phase->status,
                'starts_at' => $phase->starts_at?->toDateString(),
                'ends_at' => $phase->ends_at?->toDateString(),
            ])
            ->all();

        $milestones = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('is_client_visible', true)
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $completedMilestones = $milestones
            ->where('status', ProjectMilestone::STATUS_DONE)
            ->values()
            ->map(fn (ProjectMilestone $row) => $this->clientMilestone($row))
            ->all();

        $upcomingMilestones = $milestones
            ->where('status', '!=', ProjectMilestone::STATUS_DONE)
            ->values()
            ->map(fn (ProjectMilestone $row) => $this->clientMilestone($row))
            ->all();

        $currentPhase = collect($phases)->first(
            fn (array $phase) => ($phase['status'] ?? null) === ProjectPhase::STATUS_IN_PROGRESS
        ) ?? collect($phases)->first(
            fn (array $phase) => ($phase['status'] ?? null) === ProjectPhase::STATUS_PENDING
        );

        $nextMilestone = $upcomingMilestones[0] ?? null;

        $actionItems = Task::query()
            ->where('project_id', $project->id)
            ->where('is_client_visible', true)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->orderBy('deadline')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status,
                'deadline' => $task->deadline?->toDateString(),
            ])
            ->all();

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status instanceof ProjectStatus
                ? $project->status->value
                : $project->status,
            'started_at' => $project->started_at?->toDateString(),
            'deadline' => $project->deadline?->toDateString(),
            'account_manager' => $project->accountManager ? [
                'id' => $project->accountManager->id,
                'name' => $project->accountManager->name,
            ] : null,
            'progress' => [
                'total' => $progress['total'],
                'completed' => $progress['completed'],
                'in_progress' => $progress['in_progress'],
                'review' => $progress['review'],
                'percent' => $progress['percent'],
            ],
            'current_phase' => $currentPhase,
            'phases' => $phases,
            'completed_milestones' => $completedMilestones,
            'upcoming_milestones' => $upcomingMilestones,
            'next_milestone' => $nextMilestone,
            'client_action_items' => $actionItems,
            'references' => collect($this->brief->listReferences($project, clientVisibleOnly: true))
                ->map(fn (array $reference): array => [
                    'id' => $reference['id'],
                    'title' => $reference['title'],
                    'description' => $reference['description'] ?? null,
                    'url' => $reference['url'] ?? null,
                    'type' => $reference['type'],
                    'is_client_visible' => (bool) ($reference['is_client_visible'] ?? false),
                ])
                ->values()
                ->all(),
            'deliverables' => $this->execution->deliverables($project, forCustomer: true),
            'client_profile' => $metadata['client_profile'],
            'brief' => $metadata['brief'],
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
     * @return array<string, mixed>
     */
    public function structure(Project $project): array
    {
        return $this->phases->structure($project);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendarItems(User $actor, Project $project, Carbon $from, Carbon $to): array
    {
        $taskIds = Task::query()->where('project_id', $project->id)->pluck('id');

        $items = CalendarItem::query()
            ->with(['creator:id,name', 'assignees:id,name,role', 'reminders'])
            ->withCount(['comments', 'files'])
            ->where(function ($query) use ($project, $taskIds): void {
                $query->where(function ($inner) use ($project): void {
                    $inner->where('related_type', 'project')
                        ->where('related_id', $project->id);
                })->orWhere(function ($inner) use ($taskIds): void {
                    $inner->where('related_type', 'workspace_task')
                        ->whereIn('related_id', $taskIds);
                });
            })
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('starts_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to): void {
                        $inner->whereNotNull('ends_at')
                            ->where('starts_at', '<=', $to)
                            ->where('ends_at', '>=', $from);
                    });
            })
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (CalendarItem $item): bool => Gate::forUser($actor)->allows('view', $item))
            ->values();

        return $items->map(fn (CalendarItem $item) => $this->calendar->serialize($item))->all();
    }

    /**
     * @return array{current_phase: ?array<string, mixed>, next_milestone: ?array<string, mixed>, next_task: ?array<string, mixed>, next_deadline: ?string, risks: array<string, mixed>}
     */
    private function operationalSnapshot(Project $project): array
    {
        $currentPhase = ProjectPhase::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectPhase::STATUS_IN_PROGRESS)
            ->orderBy('sort_order')
            ->first()
            ?? ProjectPhase::query()
                ->where('project_id', $project->id)
                ->where('status', ProjectPhase::STATUS_PENDING)
                ->orderBy('sort_order')
                ->first();

        $nextMilestone = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectMilestone::STATUS_PENDING)
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $nextTask = Task::query()
            ->with(['assignee:id,name'])
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline')
            ->orderBy('id')
            ->first();

        $progress = $project->progress();
        $overdueMilestones = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', ProjectMilestone::STATUS_DONE)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $deadlineCandidates = array_values(array_filter([
            $nextTask?->deadline?->toDateString(),
            $nextMilestone?->due_date?->toDateString(),
            $project->deadline?->toDateString(),
        ]));
        sort($deadlineCandidates);
        $nextDeadline = $deadlineCandidates[0] ?? null;

        return [
            'current_phase' => $currentPhase ? $this->phases->serialize($currentPhase) : null,
            'next_milestone' => $nextMilestone ? $this->milestones->serialize($nextMilestone) : null,
            'next_task' => $nextTask ? [
                'id' => $nextTask->id,
                'title' => $nextTask->title,
                'status' => $nextTask->status instanceof TaskStatus
                    ? $nextTask->status->value
                    : (string) $nextTask->status,
                'deadline' => $nextTask->deadline?->toDateString(),
                'assignee' => $nextTask->assignee
                    ? ['id' => $nextTask->assignee->id, 'name' => $nextTask->assignee->name]
                    : null,
            ] : null,
            'next_deadline' => $nextDeadline,
            'risks' => [
                'overdue_tasks' => $progress['overdue'],
                'overdue_milestones' => $overdueMilestones,
                'due_soon' => $nextDeadline !== null
                    && $nextDeadline <= now()->addDays(7)->toDateString()
                    && $nextDeadline >= now()->toDateString(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return list<array<string, mixed>>
     */
    private function teamStats(Project $project, array $members): array
    {
        $userIds = collect($members)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($project->account_manager_id) {
            $userIds[] = (int) $project->account_manager_id;
            $userIds = array_values(array_unique($userIds));
        }

        if ($userIds === []) {
            return [];
        }

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->whereIn('assigned_to', $userIds)
            ->get(['id', 'assigned_to', 'status', 'deadline']);

        $today = now()->toDateString();
        $byAssignee = $tasks->groupBy('assigned_to');

        return collect($userIds)->map(function (int $userId) use ($byAssignee, $members, $project, $today): array {
            $member = collect($members)->firstWhere('user_id', $userId);
            $name = $member['user']['name']
                ?? ($project->account_manager_id === $userId ? $project->accountManager?->name : null)
                ?? ('#'.$userId);
            $role = $member['role']
                ?? ($project->account_manager_id === $userId ? 'account_manager' : 'member');

            $assigned = $byAssignee->get($userId) ?? collect();
            $completed = $assigned->filter(function (Task $task): bool {
                $status = $task->status instanceof TaskStatus
                    ? $task->status
                    : TaskStatus::tryFrom((string) $task->status);

                return $status === TaskStatus::Completed;
            })->count();
            $open = $assigned->count() - $completed;
            $overdue = $assigned->filter(function (Task $task) use ($today): bool {
                $status = $task->status instanceof TaskStatus
                    ? $task->status
                    : TaskStatus::tryFrom((string) $task->status);

                return $status !== TaskStatus::Completed
                    && $task->deadline !== null
                    && $task->deadline->toDateString() < $today;
            })->count();

            return [
                'user_id' => $userId,
                'name' => $name,
                'role' => $role,
                'assigned' => $assigned->count(),
                'completed' => $completed,
                'open' => $open,
                'overdue' => $overdue,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function clientMilestone(ProjectMilestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'title' => $milestone->title,
            'status' => $milestone->status,
            'due_date' => $milestone->due_date?->toDateString(),
            'phase_id' => $milestone->phase_id,
        ];
    }
}
