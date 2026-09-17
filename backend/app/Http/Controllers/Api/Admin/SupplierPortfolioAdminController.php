<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpsertSupplierPortfolioRequest;
use App\Http\Resources\SupplierPortfolioItemResource;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPortfolioAdminController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $items = $supplier->portfolioItems()->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success([
            'items' => SupplierPortfolioItemResource::collection($items)->resolve($request),
        ]);
    }

    public function store(UpsertSupplierPortfolioRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $item = $this->management->upsertAdminPortfolio($supplier, $request->validated());

        return ApiResponse::success(SupplierPortfolioItemResource::make($item)->resolve($request), 201);
    }

    public function update(UpsertSupplierPortfolioRequest $request, Supplier $supplier, SupplierPortfolioItem $item): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $item);

        $item = $this->management->upsertAdminPortfolio($supplier, $request->validated(), $item);

        return ApiResponse::success(SupplierPortfolioItemResource::make($item)->resolve($request));
    }

    public function destroy(Supplier $supplier, SupplierPortfolioItem $item): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $item);

        $item->delete();

        return ApiResponse::success(null);
    }

    private function assertBelongsToSupplier(Supplier $supplier, SupplierPortfolioItem $item): void
    {
        if ((int) $item->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }
}
