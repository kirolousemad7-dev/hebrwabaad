<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role;

        if (! $role instanceof UserRole || ! $role->usesEmployeeWorkspace()) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $page = $this->tasks->paginateAssignedTo($user, $request->query());

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

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return ApiResponse::success(
            TaskResource::make($task->load(['assignee', 'creator', 'project']))->resolve($request)
        );
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $this->authorize('updateStatus', $task);

        $status = TaskStatus::from($request->validated('status'));
        $task = $this->tasks->updateStatus($task, $status);

        return ApiResponse::success(TaskResource::make($task)->resolve($request));
    }
}
