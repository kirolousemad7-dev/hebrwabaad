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

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->withCount('packageItems')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ServiceResource::collection($services)->resolve($request)
        );
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::query()->create(
            $this->attributes($request->validated())
        );

        return ApiResponse::success(
            ServiceResource::make($service)->resolve($request),
            201
        );
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $service->loadCount('packageItems');

        return ApiResponse::success(
            ServiceResource::make($service)->resolve($request)
        );
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update(
            $this->attributes($request->validated(), $service)
        );

        $service->loadCount('packageItems');

        return ApiResponse::success(
            ServiceResource::make($service)->resolve($request)
        );
    }

    /**
     * Services are never hard-deleted while they are part of a package, so
     * package composition can't be destroyed by a catalog cleanup.
     */
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
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?Service $service = null): array
    {
        if (array_key_exists('base_price', $validated) && $validated['base_price'] === null) {
            $validated['base_price'] = 0;
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
