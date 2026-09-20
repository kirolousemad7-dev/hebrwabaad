<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectActivityResource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Services\ProjectActivityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceProjectActivityController extends Controller
{
    public function __construct(
        private readonly ProjectActivityService $activities,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('viewAny', [ProjectActivity::class, $project]);

        $page = $this->activities->paginateForProject($project, $request->query());

        return ApiResponse::success([
            'items' => ProjectActivityResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
