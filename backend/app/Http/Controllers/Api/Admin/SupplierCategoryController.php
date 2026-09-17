<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreSupplierCategoryRequest;
use App\Http\Requests\Content\UpdateSupplierCategoryRequest;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCategoryController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $categories = SupplierCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'items' => $categories->map(fn (SupplierCategory $category) => $this->serialize($category))->all(),
        ]);
    }

    public function store(StoreSupplierCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $category = $this->management->upsertCategory($request->validated());

        return ApiResponse::success($this->serialize($category), 201);
    }

    public function update(UpdateSupplierCategoryRequest $request, SupplierCategory $category): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $category = $this->management->upsertCategory($request->validated(), $category);

        return ApiResponse::success($this->serialize($category));
    }

    public function destroy(SupplierCategory $category): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $category->suppliers()->detach();
        $category->delete();

        return ApiResponse::success(null);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SupplierCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }
}
