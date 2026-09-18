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
            ->public()
            ->when(
                is_string($category) && in_array($category, ServiceCategory::values(), true),
                fn ($query) => $query->where('category', $category),
            )
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
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
            ->public()
            ->when(
                ctype_digit($service),
                fn ($query) => $query->whereKey((int) $service),
                fn ($query) => $query->where('slug', $service),
            )
            ->with([
                'packages' => fn ($query) => $query->active()->public()->orderBy('sort_order')->orderBy('name'),
                'addons' => fn ($query) => $query->active()->public()->orderBy('sort_order')->orderBy('name'),
                'portfolioItems' => fn ($query) => $query->published(),
                'suppliers' => fn ($query) => $query->publiclyVisible(),
                'products' => fn ($query) => $query->published(),
            ])
            ->firstOrFail();

        $related = Service::query()
            ->active()
            ->public()
            ->where('category', $model->category)
            ->whereKeyNot($model->id)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(6)
            ->get();

        $payload = ServiceResource::make($model)->resolve($request);
        $payload['related_services'] = $related->map(fn (Service $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'summary' => $item->summary,
            'category' => $item->category->value,
            'base_price' => $item->base_price,
            'currency' => $item->currency,
            'pricing_mode' => $item->pricingMode()->value,
            'pricing_label' => $item->pricingMode()->label(),
            'is_chargeable' => $item->isChargeable(),
            'duration_days' => $item->duration_days,
        ])->values()->all();

        return ApiResponse::success($payload);
    }
}
