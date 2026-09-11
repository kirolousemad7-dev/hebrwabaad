<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreSectorRequest;
use App\Http\Requests\Catalog\UpdateSectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\Sector;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectorAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sectors = Sector::query()
            ->with(['services', 'packages', 'portfolioItems'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(SectorResource::collection($sectors)->resolve($request));
    }

    public function store(StoreSectorRequest $request): JsonResponse
    {
        $sector = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['service_ids', 'package_ids', 'portfolio_item_ids']);

            if (empty($data['slug'])) {
                $data['slug'] = Sector::uniqueSlug($data['name_ar'] ?? $data['name_en'] ?? 'sector');
            }

            $sector = Sector::query()->create($data);
            $this->syncAssociations($sector, $request->validated());

            return $sector->load(['services', 'packages', 'portfolioItems']);
        });

        return ApiResponse::success(SectorResource::make($sector)->resolve($request), 201);
    }

    public function show(Request $request, Sector $sector): JsonResponse
    {
        $sector->load(['services', 'packages', 'portfolioItems']);

        return ApiResponse::success(SectorResource::make($sector)->resolve($request));
    }

    public function update(UpdateSectorRequest $request, Sector $sector): JsonResponse
    {
        $sector = DB::transaction(function () use ($request, $sector) {
            $data = $request->safe()->except(['service_ids', 'package_ids', 'portfolio_item_ids']);
            $sector->update($data);
            $this->syncAssociations($sector, $request->validated());

            return $sector->load(['services', 'packages', 'portfolioItems']);
        });

        return ApiResponse::success(SectorResource::make($sector)->resolve($request));
    }

    public function destroy(Sector $sector): JsonResponse
    {
        $sector->delete();

        return ApiResponse::success(null);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncAssociations(Sector $sector, array $validated): void
    {
        if (array_key_exists('service_ids', $validated)) {
            $sector->services()->sync($validated['service_ids'] ?? []);
        }

        if (array_key_exists('package_ids', $validated)) {
            $sector->packages()->sync($validated['package_ids'] ?? []);
        }

        if (array_key_exists('portfolio_item_ids', $validated)) {
            $sector->portfolioItems()->sync($validated['portfolio_item_ids'] ?? []);
        }
    }
}
