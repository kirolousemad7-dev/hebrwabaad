<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReviewNotesRequest;
use App\Http\Resources\WorkSubmissionResource;
use App\Models\WorkSubmission;
use App\Services\WorkSubmissionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkReviewController extends Controller
{
    public function __construct(private readonly WorkSubmissionService $work) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkSubmission::class);
        $page = $this->work->paginateForReview($request->query());

        return ApiResponse::success([
            'items' => WorkSubmissionResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('review', $work);

        try {
            $item = $this->work->markUnderReview($request->user(), $work);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function approvePublish(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('review', $work);

        try {
            $item = $this->work->approveAndPublish($request->user(), $work);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function reject(ReviewNotesRequest $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('review', $work);

        try {
            $item = $this->work->reject($request->user(), $work, $request->validated('notes'));
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function requestChanges(ReviewNotesRequest $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('review', $work);

        try {
            $item = $this->work->requestChanges($request->user(), $work, $request->validated('notes'));
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function unpublish(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('review', $work);

        try {
            $item = $this->work->unpublish($request->user(), $work);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }

    public function archive(Request $request, WorkSubmission $work): JsonResponse
    {
        $this->authorize('review', $work);

        try {
            $item = $this->work->archive($request->user(), $work);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(WorkSubmissionResource::make($item)->resolve($request));
    }
}
