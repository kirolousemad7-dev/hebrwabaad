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
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierAdminController extends Controller
{
    public function __construct(
        private readonly SupplierAdminService $suppliers,
        private readonly SupplierManagementService $management,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);
        $page = $this->management->paginate($request->query());

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
            $supplier = $this->management->create($request->user(), $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request), 201);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);
        $supplier->load([
            'user',
            'pendingProfileVersion',
            'portfolioItems',
            'products',
            'reviews.actor',
            'contacts',
            'offeredServices',
            'documents',
            'categories',
            'tags',
        ])->loadCount(['portfolioItems', 'products', 'contacts', 'offeredServices', 'documents']);

        return ApiResponse::success([
            'supplier' => AdminSupplierResource::make($supplier)->resolve($request),
            'contacts' => $supplier->contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'position' => $contact->position,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'whatsapp' => $contact->whatsapp,
                'is_primary' => $contact->is_primary,
                'notes' => $contact->notes,
            ])->all(),
            'services' => $supplier->offeredServices->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'pricing_model' => $service->pricing_model?->value,
                'minimum_price' => $service->minimum_price,
                'maximum_price' => $service->maximum_price,
                'currency' => $service->currency,
                'delivery_time' => $service->delivery_time,
                'is_active' => $service->is_active,
                'sort_order' => $service->sort_order,
            ])->all(),
            'documents' => $supplier->documents->map(fn ($document) => [
                'id' => $document->id,
                'title' => $document->title,
                'category' => $document->category,
                'path' => $document->path,
                'visibility' => $document->visibility?->value,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
            ])->all(),
            'categories' => $supplier->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->all(),
            'tags' => $supplier->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])->all(),
            'portfolio' => $supplier->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'image' => $item->image,
                'category' => $item->category,
                'status' => $item->status?->value,
                'visibility' => $item->visibility?->value,
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
        $supplier = $this->management->update($request->user(), $supplier, $request->validated());

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
