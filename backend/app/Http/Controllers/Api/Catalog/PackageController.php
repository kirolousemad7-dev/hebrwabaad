<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Enums\PackageCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');

        $packages = Package::query()
            ->active()
            ->when(
                is_string($category) && in_array($category, PackageCategory::values(), true),
                fn ($query) => $query->where('category', $category),
            )
            ->with([
                'items' => fn ($query) => $query->whereHas('service', fn ($service) => $service->active())->with('service'),
                'tiers' => fn ($query) => $query->active(),
            ])
            ->orderBy('sort_order')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            PackageResource::collection($packages)->resolve($request)
        );
    }

    public function show(Request $request, string $package): JsonResponse
    {
        $model = Package::query()
            ->active()
            ->when(
                ctype_digit($package),
                fn ($query) => $query->whereKey((int) $package),
                fn ($query) => $query->where('slug', $package),
            )
            ->with([
                'items' => fn ($query) => $query->whereHas('service', fn ($service) => $service->active())->with('service'),
                'tiers' => fn ($query) => $query->active(),
            ])
            ->firstOrFail();

        return ApiResponse::success(
            PackageResource::make($model)->resolve($request)
        );
    }
}
