<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Enums\ServiceCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');

        $services = Service::query()
            ->active()
            ->when(
                is_string($category) && in_array($category, ServiceCategory::values(), true),
                fn ($query) => $query->where('category', $category),
            )
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ServiceResource::collection($services)->resolve($request)
        );
    }

    public function show(Request $request, string $service): JsonResponse
    {
        $model = Service::query()
            ->active()
            ->when(
                ctype_digit($service),
                fn ($query) => $query->whereKey((int) $service),
                fn ($query) => $query->where('slug', $service),
            )
            ->firstOrFail();

        return ApiResponse::success(
            ServiceResource::make($model)->resolve($request)
        );
    }
}
