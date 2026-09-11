<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintingProduct;
use App\Models\PrintingProductCategory;
use App\Models\PrintingProductOption;
use App\Services\Catalog\PrintingCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintingCatalogAdminController extends Controller
{
    public function __construct(
        private readonly PrintingCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->catalog->manageIndex($request->user()));
    }

    public function storeProduct(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->catalog->upsertProduct($request->user(), $request->all()),
            status: 201,
        );
    }

    public function updateProduct(Request $request, PrintingProduct $printingProduct): JsonResponse
    {
        return ApiResponse::success(
            $this->catalog->upsertProduct($request->user(), $request->all(), $printingProduct)
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->catalog->upsertCategory($request->user(), $request->all()),
            status: 201,
        );
    }

    public function updateCategory(Request $request, PrintingProductCategory $printingProductCategory): JsonResponse
    {
        return ApiResponse::success(
            $this->catalog->upsertCategory($request->user(), $request->all(), $printingProductCategory)
        );
    }

    public function storeOption(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->catalog->upsertOption($request->user(), $request->all()),
            status: 201,
        );
    }

    public function updateOption(Request $request, PrintingProductOption $printingProductOption): JsonResponse
    {
        return ApiResponse::success(
            $this->catalog->upsertOption($request->user(), $request->all(), $printingProductOption)
        );
    }
}
