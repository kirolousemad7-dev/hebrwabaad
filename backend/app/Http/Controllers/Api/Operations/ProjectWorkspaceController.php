<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Operations\ProjectWorkspaceService;
use App\Services\ProjectService;
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
}
