<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSupplierProductResource;
use App\Http\Resources\SupplierPortfolioItemResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $specialty = $request->query('specialty');
        $service = $request->query('service');
        $featured = $request->query('featured');
        $location = $request->query('location');
        $category = $request->query('category');

        $suppliers = Supplier::query()
            ->publiclyVisible()
            ->with(['publicPortfolioItems', 'featuredPublicProducts'])
            ->withCount('publicPortfolioItems')
            ->when(
                is_string($search) && trim($search) !== '',
                function ($query) use ($search): void {
                    $term = '%'.trim($search).'%';
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('name', 'like', $term)
                            ->orWhere('short_description', 'like', $term)
                            ->orWhere('location', 'like', $term);
                    });
                },
            )
            ->when(
                is_string($specialty) && $specialty !== '',
                fn ($query) => $query->whereJsonContains('specialties', $specialty),
            )
            ->when(
                is_string($service) && $service !== '',
                fn ($query) => $query->whereJsonContains('services', $service),
            )
            ->when(
                is_string($location) && $location !== '',
                fn ($query) => $query->where('location', $location),
            )
            ->when(
                is_string($category) && $category !== '',
                fn ($query) => $query->where('category', $category),
            )
            ->when(
                $featured === '1' || $featured === 'true',
                fn ($query) => $query->where('is_featured', true),
            )
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            SupplierResource::collection($suppliers)->resolve($request)
        );
    }

    public function show(Request $request, string $supplier): JsonResponse
    {
        $model = Supplier::query()
            ->publiclyVisible()
            ->where('slug', $supplier)
            ->with(['publicPortfolioItems', 'publicProducts'])
            ->withCount('publicPortfolioItems')
            ->firstOrFail();

        return ApiResponse::success(
            SupplierResource::make($model)->resolve($request)
        );
    }

    public function portfolio(Request $request, string $supplier): JsonResponse
    {
        $model = $this->publishedSupplier($supplier)->load('publicPortfolioItems');

        return ApiResponse::success(
            SupplierPortfolioItemResource::collection($model->publicPortfolioItems)->resolve($request)
        );
    }

    public function products(Request $request, string $supplier): JsonResponse
    {
        $model = $this->publishedSupplier($supplier);
        $page = $model->publicProducts()->paginate(min(50, max(1, (int) $request->query('per_page', 24))));

        return ApiResponse::success([
            'items' => PublicSupplierProductResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function product(Request $request, string $supplier, string $productSlug): JsonResponse
    {
        $model = $this->publishedSupplier($supplier);
        $product = $model->publicProducts()->where('slug', $productSlug)->firstOrFail();

        return ApiResponse::success(
            PublicSupplierProductResource::make($product)->resolve($request)
        );
    }

    private function publishedSupplier(string $slug): Supplier
    {
        return Supplier::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
