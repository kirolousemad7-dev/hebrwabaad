<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SupplierContentType;
use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReviewNotesRequest;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProfileVersion;
use App\Services\SupplierContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierReviewController extends Controller
{
    public function __construct(private readonly SupplierContentService $content) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);
        $page = $this->content->reviewInbox($request->query());

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function approvePublish(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        try {
            $result = match ($type) {
                SupplierContentType::Profile->value, SupplierContentType::ProfileVersion->value => $this->content->approveProfile(
                    $request->user(),
                    $type === SupplierContentType::ProfileVersion->value
                        ? SupplierProfileVersion::query()->findOrFail($id)->supplier
                        : Supplier::query()->findOrFail($id),
                ),
                SupplierContentType::Portfolio->value => $this->content->approvePortfolioItem(
                    $request->user(),
                    SupplierPortfolioItem::query()->findOrFail($id),
                ),
                SupplierContentType::Product->value => $this->content->approveProduct(
                    $request->user(),
                    SupplierProduct::query()->findOrFail($id),
                ),
                default => throw new ContentWorkflowException('نوع المحتوى غير معروف.', 404),
            };
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(['status' => 'published', 'id' => $result->id]);
    }

    public function reject(ReviewNotesRequest $request, string $type, int $id): JsonResponse
    {
        return $this->moderate($request, $type, $id, 'reject');
    }

    public function requestChanges(ReviewNotesRequest $request, string $type, int $id): JsonResponse
    {
        return $this->moderate($request, $type, $id, 'changes');
    }

    private function moderate(ReviewNotesRequest $request, string $type, int $id, string $mode): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);
        $notes = $request->validated('notes');

        try {
            match ($type) {
                SupplierContentType::Profile->value, SupplierContentType::ProfileVersion->value => $mode === 'reject'
                    ? $this->content->rejectProfile($request->user(), $this->supplierFromReview($type, $id), $notes)
                    : $this->content->requestProfileChanges($request->user(), $this->supplierFromReview($type, $id), $notes),
                SupplierContentType::Portfolio->value => $mode === 'reject'
                    ? $this->content->rejectPortfolioItem($request->user(), SupplierPortfolioItem::query()->findOrFail($id), $notes)
                    : $this->content->requestPortfolioChanges($request->user(), SupplierPortfolioItem::query()->findOrFail($id), $notes),
                SupplierContentType::Product->value => $mode === 'reject'
                    ? $this->content->rejectProduct($request->user(), SupplierProduct::query()->findOrFail($id), $notes)
                    : $this->content->requestProductChanges($request->user(), SupplierProduct::query()->findOrFail($id), $notes),
                default => throw new ContentWorkflowException('نوع المحتوى غير معروف.', 404),
            };
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(['status' => $mode === 'reject' ? 'rejected' : 'changes_requested']);
    }

    private function supplierFromReview(string $type, int $id): Supplier
    {
        if ($type === SupplierContentType::ProfileVersion->value) {
            return SupplierProfileVersion::query()->findOrFail($id)->supplier;
        }

        return Supplier::query()->findOrFail($id);
    }
}
