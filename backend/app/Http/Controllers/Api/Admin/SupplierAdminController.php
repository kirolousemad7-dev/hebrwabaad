<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreAdminSupplierRequest;
use App\Http\Requests\Content\UpdateAdminSupplierRequest;
use App\Http\Resources\AdminSupplierResource;
use App\Http\Resources\SupplierProductResource;
use App\Models\Supplier;
use App\Services\SupplierAdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierAdminController extends Controller
{
    public function __construct(private readonly SupplierAdminService $suppliers) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);
        $page = $this->suppliers->paginate($request->query());

        return ApiResponse::success([
            'items' => AdminSupplierResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreAdminSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        try {
            $supplier = $this->suppliers->create($request->user(), $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request), 201);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);
        $supplier->load(['user', 'pendingProfileVersion', 'portfolioItems', 'products', 'reviews.actor'])
            ->loadCount(['portfolioItems', 'products']);

        return ApiResponse::success([
            'supplier' => AdminSupplierResource::make($supplier)->resolve($request),
            'portfolio' => $supplier->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'image' => $item->image,
                'category' => $item->category,
                'status' => $item->status?->value,
                'review_notes' => $item->review_notes,
                'is_featured' => $item->is_featured,
            ])->all(),
            'products' => SupplierProductResource::collection($supplier->products)->resolve($request),
            'reviews' => $supplier->reviews->map(fn ($review) => [
                'id' => $review->id,
                'action' => $review->action?->value ?? $review->action,
                'from_status' => $review->from_status?->value,
                'to_status' => $review->to_status?->value,
                'notes' => $review->notes,
                'actor' => $review->actor?->name,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function update(UpdateAdminSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);
        $supplier = $this->suppliers->update($request->user(), $supplier, $request->validated());

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);
        $this->suppliers->delete($supplier);

        return ApiResponse::success(null);
    }

    public function activate(Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        return ApiResponse::success(AdminSupplierResource::make($this->suppliers->setActive($supplier, true))->resolve());
    }

    public function deactivate(Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        return ApiResponse::success(AdminSupplierResource::make($this->suppliers->setActive($supplier, false))->resolve());
    }

    public function publish(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('publish', $supplier);

        try {
            $supplier = $this->suppliers->approveAndPublish($request->user(), $supplier);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function unpublish(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('publish', $supplier);

        return ApiResponse::success(AdminSupplierResource::make($this->suppliers->unpublish($request->user(), $supplier))->resolve($request));
    }
}
