<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CatalogAddon;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogAddonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addons = CatalogAddon::query()
            ->active()
            ->public()
            ->with('services:id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (CatalogAddon $addon) => $addon->isAvailable())
            ->map(fn (CatalogAddon $addon) => [
                'id' => $addon->id,
                'slug' => $addon->slug,
                'name' => $addon->name,
                'summary' => $addon->summary,
                'description' => $addon->description,
                'pricing_mode' => $addon->pricingMode()->value,
                'price' => $addon->isChargeable() || $addon->pricingMode()->value === 'STARTING_FROM'
                    ? $addon->price
                    : null,
                'currency' => $addon->currency,
                'is_chargeable' => $addon->isChargeable(),
                'is_urgent' => $addon->is_urgent,
                'requires_capacity' => $addon->requires_capacity,
                'capacity_available' => $addon->capacity_available,
                'is_available' => $addon->isAvailable(),
                'min_qty' => $addon->min_qty,
                'max_qty' => $addon->max_qty,
                'service_ids' => $addon->services->pluck('id')->values()->all(),
            ]);

        return ApiResponse::success($addons->values()->all());
    }
}
