<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreServiceRequest;
use App\Http\Requests\Catalog\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->withCount('packageItems')
            ->with(['addons', 'sectors', 'portfolioItems', 'suppliers', 'products', 'projects', 'quotations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ServiceResource::collection($services)->resolve($request)
        );
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = DB::transaction(function () use ($request) {
            $service = Service::query()->create(
                $this->attributes($request->safe()->except($this->associationKeys()))
            );
            $this->syncAssociations($service, $request->validated());

            return $service->loadCount('packageItems')
                ->load(['addons', 'sectors', 'portfolioItems', 'suppliers', 'products', 'projects', 'quotations']);
        });

        return ApiResponse::success(
            ServiceResource::make($service)->resolve($request),
            201
        );
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $service->loadCount('packageItems')
            ->load(['addons', 'sectors', 'portfolioItems', 'suppliers', 'products', 'projects', 'quotations']);

        return ApiResponse::success(
            ServiceResource::make($service)->resolve($request)
        );
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service = DB::transaction(function () use ($request, $service) {
            $service->update(
                $this->attributes($request->safe()->except($this->associationKeys()), $service)
            );
            $this->syncAssociations($service, $request->validated());

            return $service->loadCount('packageItems')
                ->load(['addons', 'sectors', 'portfolioItems', 'suppliers', 'products', 'projects', 'quotations']);
        });

        return ApiResponse::success(
            ServiceResource::make($service)->resolve($request)
        );
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->packageItems()->exists()) {
            return ApiResponse::error(
                'This service belongs to one or more packages. Deactivate it or remove it from those packages first.',
                422
            );
        }

        $service->delete();

        return ApiResponse::success(null);
    }

    /**
     * @return list<string>
     */
    private function associationKeys(): array
    {
        return [
            'addon_ids',
            'sector_ids',
            'portfolio_item_ids',
            'supplier_ids',
            'product_ids',
            'project_ids',
            'quotation_ids',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncAssociations(Service $service, array $validated): void
    {
        if (array_key_exists('addon_ids', $validated)) {
            $service->addons()->sync($validated['addon_ids'] ?? []);
        }

        if (array_key_exists('sector_ids', $validated)) {
            $service->sectors()->sync($validated['sector_ids'] ?? []);
        }

        if (array_key_exists('portfolio_item_ids', $validated)) {
            $service->portfolioItems()->sync($validated['portfolio_item_ids'] ?? []);
        }

        if (array_key_exists('supplier_ids', $validated)) {
            $service->suppliers()->sync($validated['supplier_ids'] ?? []);
        }

        if (array_key_exists('product_ids', $validated)) {
            $service->products()->sync($validated['product_ids'] ?? []);
        }

        if (array_key_exists('project_ids', $validated)) {
            $service->projects()->sync($validated['project_ids'] ?? []);
        }

        if (array_key_exists('quotation_ids', $validated)) {
            $service->quotations()->sync($validated['quotation_ids'] ?? []);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?Service $service = null): array
    {
        if (array_key_exists('base_price', $validated) && $validated['base_price'] === null) {
            $validated['base_price'] = 0;
        }

        if (array_key_exists('short_description', $validated)) {
            $validated['summary'] = $validated['short_description'];
            unset($validated['short_description']);
        }

        $slugSent = array_key_exists('slug', $validated);

        if ($slugSent && $validated['slug'] !== null) {
            return $validated;
        }

        if ($service !== null && ! $slugSent) {
            return $validated;
        }

        $validated['slug'] = Service::uniqueSlug(
            $validated['name'] ?? $service?->name ?? '',
            $service?->id
        );

        return $validated;
    }
}
