<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpsertSupplierPortfolioRequest;
use App\Http\Requests\Content\UpsertSupplierProductRequest;
use App\Http\Requests\Content\UpsertSupplierProfileRequest;
use App\Http\Resources\AdminSupplierResource;
use App\Http\Resources\SupplierProductResource;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Services\SupplierContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierWorkspaceController extends Controller
{
    public function __construct(private readonly SupplierContentService $content) {}

    public function profile(Request $request): JsonResponse
    {
        try {
            $supplier = $this->content->supplierFor($request->user())->load(['pendingProfileVersion', 'user']);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        $this->authorize('view', $supplier);

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function updateProfile(UpsertSupplierProfileRequest $request): JsonResponse
    {
        try {
            $supplier = $this->content->updateProfile($request->user(), $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        $this->authorize('update', $supplier);

        return ApiResponse::success(AdminSupplierResource::make($supplier->load('pendingProfileVersion'))->resolve($request));
    }

    public function submitProfile(Request $request): JsonResponse
    {
        try {
            $supplier = $this->content->submitProfile($request->user());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function content(Request $request): JsonResponse
    {
        try {
            $data = $this->content->dashboard($request->user(), $request->query());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success($data);
    }

    public function storePortfolio(UpsertSupplierPortfolioRequest $request): JsonResponse
    {
        try {
            $item = $this->content->createPortfolioItem($request->user(), $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success([
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'image' => $item->image,
            'category' => $item->category,
            'status' => $item->status?->value,
        ], 201);
    }

    public function updatePortfolio(UpsertSupplierPortfolioRequest $request, SupplierPortfolioItem $item): JsonResponse
    {
        try {
            $item = $this->content->updatePortfolioItem($request->user(), $item, $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success([
            'id' => $item->id,
            'title' => $item->title,
            'status' => $item->status?->value,
        ]);
    }

    public function submitPortfolio(Request $request, SupplierPortfolioItem $item): JsonResponse
    {
        try {
            $item = $this->content->submitPortfolioItem($request->user(), $item);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(['id' => $item->id, 'status' => $item->status?->value]);
    }

    public function storeProduct(UpsertSupplierProductRequest $request): JsonResponse
    {
        try {
            $product = $this->content->createProduct($request->user(), $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(SupplierProductResource::make($product)->resolve($request), 201);
    }

    public function updateProduct(UpsertSupplierProductRequest $request, SupplierProduct $product): JsonResponse
    {
        try {
            $product = $this->content->updateProduct($request->user(), $product, $request->validated());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(SupplierProductResource::make($product)->resolve($request));
    }

    public function submitProduct(Request $request, SupplierProduct $product): JsonResponse
    {
        try {
            $product = $this->content->submitProduct($request->user(), $product);
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success(SupplierProductResource::make($product)->resolve($request));
    }
}
