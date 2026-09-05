<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceProjectRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceProjectRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Services\ProjectService;
use App\Services\TaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly TaskService $tasks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $page = $this->projects->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => ProjectResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreWorkspaceProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projects->create($request->user(), $request->validated());

        return ApiResponse::success(ProjectResource::make($project)->resolve($request), 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::success(
            ProjectResource::make($this->projects->load($project))->resolve($request)
        );
    }

    public function update(UpdateWorkspaceProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project = $this->projects->update($project, $request->validated());

        return ApiResponse::success(ProjectResource::make($project)->resolve($request));
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
            'project' => ProjectResource::make($this->projects->load($project))->resolve($request),
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $search = is_string($request->query('q')) ? $request->query('q') : null;

        return ApiResponse::success(
            EmployeeResource::collection($this->projects->customers($search))->resolve($request)
        );
    }
}
