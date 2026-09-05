<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
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

        $suppliers = Supplier::query()
            ->active()
            ->with('publicPortfolioItems')
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
            ->active()
            ->where('slug', $supplier)
            ->with('publicPortfolioItems')
            ->withCount('publicPortfolioItems')
            ->firstOrFail();

        return ApiResponse::success(
            SupplierResource::make($model)->resolve($request)
        );
    }
}
