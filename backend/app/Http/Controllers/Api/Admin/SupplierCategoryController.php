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
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $flat = SupplierCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'items' => $flat->map(fn (SupplierCategory $category) => $this->serialize($category))->all(),
            'tree' => $categories->map(fn (SupplierCategory $category) => $this->serialize($category, withChildren: true))->all(),
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
    private function serialize(SupplierCategory $category, bool $withChildren = false): array
    {
        $payload = [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];

        if ($withChildren) {
            $payload['children'] = $category->children
                ->map(fn (SupplierCategory $child) => $this->serialize($child))
                ->all();
        }

        return $payload;
    }
}
