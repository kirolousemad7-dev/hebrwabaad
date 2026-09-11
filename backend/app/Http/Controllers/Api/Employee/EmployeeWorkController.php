<?php

namespace App\Http\Controllers\Api\Employee;

use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpsertWorkSubmissionRequest;
use App\Http\Resources\WorkSubmissionResource;
use App\Models\WorkSubmission;
use App\Services\WorkSubmissionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeWorkController extends Controller
{
    public function __construct(private readonly WorkSubmissionService $work) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkSubmission::class);
        $page = $this->work->paginateForEmployee($request->user(), $request->query());

        return ApiResponse::success([
            'items' => WorkSubmissionResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'counts' => $this->work->countsForEmployee($request->user()),
        ]);
    }

    public function store(UpsertWorkSubmissionRequest $request): JsonResponse
    {
        $this->authorize('create', WorkSubmission::class);

        try {
            $item = $this->work->create($request->user(), $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request), 201);
    }

    public function show(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('view', $work);

        return ApiResponse::success(WorkSubmissionResource::make($work->load(['coverMedia', 'service']))->resolve($request));
    }

    public function update(UpsertWorkSubmissionRequest $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('update', $work);

        try {
            $item = $this->work->update($request->user(), $work, $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function submit(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('submit', $work);

        try {
            $item = $this->work->submit($request->user(), $work);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function destroy(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('delete', $work);

        try {
            $this->work->delete($request->user(), $work);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(null);
    }
}
