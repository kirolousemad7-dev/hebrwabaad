<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceTaskRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceTaskRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountManagerTaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = $this->tasks->paginateCreatedBy($user, $request->query());

        return ApiResponse::success([
            'items' => TaskResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'summary' => $this->tasks->summaryForCreator($user),
        ]);
    }

    public function store(StoreWorkspaceTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = $this->tasks->create($request->user(), $request->validated());

        return ApiResponse::success(TaskResource::make($task)->resolve($request), 201);
    }

    public function update(UpdateWorkspaceTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->tasks->update($request->user(), $task, $request->validated());

        return ApiResponse::success(TaskResource::make($task)->resolve($request));
    }

    public function assignees(Request $request): JsonResponse
    {
        return ApiResponse::success(
            EmployeeResource::collection($this->tasks->assignees())->resolve($request)
        );
    }
}
