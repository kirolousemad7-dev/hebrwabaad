<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCatalogAddonRequest;
use App\Http\Requests\Catalog\UpdateCatalogAddonRequest;
use App\Http\Resources\CatalogAddonResource;
use App\Models\CatalogAddon;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogAddonAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addons = CatalogAddon::query()
            ->with(['services', 'packages'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(CatalogAddonResource::collection($addons)->resolve($request));
    }

    public function store(StoreCatalogAddonRequest $request): JsonResponse
    {
        $addon = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['service_ids', 'package_ids']);

            if (empty($data['slug'])) {
                $data['slug'] = CatalogAddon::uniqueSlug($data['name'] ?? 'addon');
            }

            $addon = CatalogAddon::query()->create($data);
            $this->syncAssociations($addon, $request->validated());

            return $addon->load(['services', 'packages']);
        });

        return ApiResponse::success(CatalogAddonResource::make($addon)->resolve($request), 201);
    }

    public function show(Request $request, CatalogAddon $addon): JsonResponse
    {
        $addon->load(['services', 'packages']);

        return ApiResponse::success(CatalogAddonResource::make($addon)->resolve($request));
    }

    public function update(UpdateCatalogAddonRequest $request, CatalogAddon $addon): JsonResponse
    {
        $addon = DB::transaction(function () use ($request, $addon) {
            $data = $request->safe()->except(['service_ids', 'package_ids']);
            $addon->update($data);
            $this->syncAssociations($addon, $request->validated());

            return $addon->load(['services', 'packages']);
        });

        return ApiResponse::success(CatalogAddonResource::make($addon)->resolve($request));
    }

    public function destroy(CatalogAddon $addon): JsonResponse
    {
        $addon->delete();

        return ApiResponse::success(null);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncAssociations(CatalogAddon $addon, array $validated): void
    {
        if (array_key_exists('service_ids', $validated)) {
            $addon->services()->sync($validated['service_ids'] ?? []);
        }

        if (array_key_exists('package_ids', $validated)) {
            $addon->packages()->sync($validated['package_ids'] ?? []);
        }
    }
}
