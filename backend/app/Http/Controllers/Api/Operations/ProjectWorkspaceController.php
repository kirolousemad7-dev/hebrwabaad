<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceTaskRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\Task;
use App\Services\Operations\ProjectWorkspaceService;
use App\Services\Operations\Work\TaskCalendarLinkService;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectWorkspaceController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectWorkspaceService $workspace,
        private readonly TaskService $tasks,
        private readonly TaskCalendarLinkService $calendarLinks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole) || ! $user->role->canOverseeProjects()) {
            abort(403);
        }

        $this->authorize('viewAny', Project::class);

        $page = $this->projects->paginateFor($user, $request->query());

        $items = collect($page->items())->map(function (Project $project) {
            $health = $this->projects->health($project);

            return [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status instanceof ProjectStatus
                    ? $project->status->value
                    : $project->status,
                'deadline' => $project->deadline?->toDateString(),
                'progress' => $project->progress(),
                'health' => $health,
                'account_manager' => $project->accountManager ? [
                    'id' => $project->accountManager->id,
                    'name' => $project->accountManager->name,
                ] : null,
                'customer' => $project->customer ? [
                    'id' => $project->customer->id,
                    'name' => $project->customer->name,
                ] : null,
            ];
        })->all();

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function workspace(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::success($this->workspace->show($request->user(), $project));
    }

    public function syncMembers(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'members' => ['required', 'array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role' => ['nullable', 'string', 'in:manager,member'],
        ]);

        $project = $this->projects->syncMembers($project, $data['members']);

        return ApiResponse::success([
            'members' => $project->members->map(fn ($member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'user' => $member->user ? [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'role' => $member->user->role?->value ?? $member->user->role,
                ] : null,
            ])->values()->all(),
        ]);
    }

    public function calendarItems(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        if ($from->diffInDays($to) > 93) {
            throw ValidationException::withMessages([
                'to' => ['Date range must not exceed 93 days.'],
            ]);
        }

        return ApiResponse::success([
            'items' => $this->workspace->calendarItems($request->user(), $project, $from, $to),
        ]);
    }

    public function timeline(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'filter' => ['nullable', 'string', 'in:all,tasks,files,team,approvals,automation'],
        ]);

        return ApiResponse::success([
            'items' => $this->workspace->timeline($project, $data['filter'] ?? 'all'),
        ]);
    }

    public function structure(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::success($this->workspace->structure($project));
    }

    public function tasks(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $page = $this->tasks->paginateForProject($request->user(), $project->id, $request->query());

        return ApiResponse::success([
            'items' => TaskResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function storeTask(StoreWorkspaceTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', Task::class);
        $this->authorize('update', $project);

        $validated = $request->validated();
        $validated['project_id'] = $project->id;

        $task = $this->tasks->create($request->user(), $validated);

        $calendarItem = null;
        $linkToCalendar = (bool) $request->boolean('link_to_calendar');
        if ($linkToCalendar) {
            $startsAt = $validated['start_at'] ?? $validated['deadline'] ?? null;
            if ($startsAt !== null) {
                $this->authorize('create', CalendarItem::class);
                $endsAt = $validated['due_at'] ?? null;
                $calendarItem = $this->calendarLinks->linkFromTask($request->user(), $task, [
                    'starts_at' => is_string($startsAt) ? $startsAt : Carbon::parse($startsAt)->toIso8601String(),
                    'ends_at' => $endsAt !== null
                        ? (is_string($endsAt) ? $endsAt : Carbon::parse($endsAt)->toIso8601String())
                        : null,
                    'all_day' => ! isset($validated['start_at']),
                ]);
                $task = $task->fresh(['assignee', 'creator', 'project', 'supplier']) ?? $task;
            }
        }

        return ApiResponse::success([
            'task' => TaskResource::make($task)->resolve($request),
            'calendar_item_id' => $calendarItem?->id ?? $task->calendar_item_id,
        ], 201);
    }

    public function updateTask(UpdateWorkspaceTaskRequest $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('update', $task);
        $this->assertTaskBelongsToProject($project, $task);

        $validated = $request->validated();
        $validated['project_id'] = $project->id;

        $task = $this->tasks->update($request->user(), $task, $validated);

        $calendarItemId = $task->calendar_item_id;
        if ($request->boolean('link_to_calendar') && $calendarItemId === null) {
            $startsAt = $validated['start_at'] ?? $validated['deadline'] ?? null;
            if ($startsAt !== null) {
                $this->authorize('create', CalendarItem::class);
                $endsAt = $validated['due_at'] ?? null;
                $item = $this->calendarLinks->linkFromTask($request->user(), $task, [
                    'starts_at' => is_string($startsAt) ? $startsAt : Carbon::parse($startsAt)->toIso8601String(),
                    'ends_at' => $endsAt !== null
                        ? (is_string($endsAt) ? $endsAt : Carbon::parse($endsAt)->toIso8601String())
                        : null,
                    'all_day' => ! isset($validated['start_at']),
                ]);
                $calendarItemId = $item->id;
                $task = $task->fresh(['assignee', 'creator', 'project', 'supplier']) ?? $task;
            }
        }

        return ApiResponse::success([
            'task' => TaskResource::make($task)->resolve($request),
            'calendar_item_id' => $calendarItemId,
        ]);
    }

    public function linkTaskCalendar(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('update', $task);
        $this->authorize('create', CalendarItem::class);
        $this->assertTaskBelongsToProject($project, $task);

        $data = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['sometimes', 'boolean'],
        ]);

        $startsAt = $data['starts_at']
            ?? ($task->start_at?->toIso8601String())
            ?? ($task->deadline?->toDateString());

        if ($startsAt === null) {
            throw ValidationException::withMessages([
                'starts_at' => ['A start date is required to link the calendar.'],
            ]);
        }

        $item = $this->calendarLinks->linkFromTask($request->user(), $task, [
            'starts_at' => is_string($startsAt) ? $startsAt : Carbon::parse($startsAt)->toIso8601String(),
            'ends_at' => $data['ends_at'] ?? ($task->due_at?->toIso8601String()),
            'all_day' => (bool) ($data['all_day'] ?? $task->start_at === null),
        ]);

        $task = $task->fresh(['assignee', 'creator', 'project', 'supplier']) ?? $task;

        return ApiResponse::success([
            'task' => TaskResource::make($task)->resolve($request),
            'calendar_item_id' => $item->id,
        ]);
    }

    private function assertTaskBelongsToProject(Project $project, Task $task): void
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(404);
        }
    }
}
