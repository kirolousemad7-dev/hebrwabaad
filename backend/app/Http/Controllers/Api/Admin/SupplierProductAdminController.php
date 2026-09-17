<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpsertSupplierProductRequest;
use App\Http\Resources\SupplierProductResource;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierProductAdminController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $products = $supplier->products()->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success([
            'items' => SupplierProductResource::collection($products)->resolve($request),
        ]);
    }

    public function store(UpsertSupplierProductRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $product = $this->management->upsertAdminProduct($supplier, $request->validated());

        return ApiResponse::success(SupplierProductResource::make($product)->resolve($request), 201);
    }

    public function update(UpsertSupplierProductRequest $request, Supplier $supplier, SupplierProduct $product): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $product);

        $product = $this->management->upsertAdminProduct($supplier, $request->validated(), $product);

        return ApiResponse::success(SupplierProductResource::make($product)->resolve($request));
    }

    public function destroy(Supplier $supplier, SupplierProduct $product): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $product);

        $product->delete();

        return ApiResponse::success(null);
    }

    private function assertBelongsToSupplier(Supplier $supplier, SupplierProduct $product): void
    {
        if ((int) $product->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }
}
