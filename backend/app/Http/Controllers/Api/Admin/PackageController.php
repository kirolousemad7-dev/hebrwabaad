<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StorePackageRequest;
use App\Http\Requests\Catalog\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = Package::query()
            ->with(['items.service', 'tiers'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            PackageResource::collection($packages)->resolve($request)
        );
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $items = $validated['items'] ?? null;
        $tiers = $validated['tiers'] ?? null;
        unset($validated['items'], $validated['tiers']);

        $package = DB::transaction(function () use ($validated, $items, $tiers): Package {
            $package = Package::query()->create($this->attributes($validated));

            if ($items !== null) {
                $this->syncItems($package, $items);
            }

            if ($tiers !== null) {
                $this->syncTiers($package, $tiers);
            }

            return $package;
        });

        return ApiResponse::success(
            PackageResource::make($package->load(['items.service', 'tiers']))->resolve($request),
            201
        );
    }

    public function show(Request $request, Package $package): JsonResponse
    {
        return ApiResponse::success(
            PackageResource::make($package->load(['items.service', 'tiers']))->resolve($request)
        );
    }

    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        $validated = $request->validated();
        $items = array_key_exists('items', $validated) ? $validated['items'] : null;
        $tiers = array_key_exists('tiers', $validated) ? $validated['tiers'] : null;
        unset($validated['items'], $validated['tiers']);

        DB::transaction(function () use ($package, $validated, $items, $tiers): void {
            $package->update($this->attributes($validated, $package));

            if ($items !== null) {
                $this->syncItems($package, $items);
            }

            if ($tiers !== null) {
                $this->syncTiers($package, $tiers);
            }
        });

        return ApiResponse::success(
            PackageResource::make($package->fresh()->load(['items.service', 'tiers']))->resolve($request)
        );
    }

    public function destroy(Package $package): JsonResponse
    {
        $package->delete();

        return ApiResponse::success(null);
    }

    /**
     * Replace the package composition with the submitted item list.
     * Items missing from the payload are detached.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Package $package, array $items): void
    {
        $payload = [];

        foreach ($items as $index => $item) {
            $payload[$item['service_id']] = [
                'quantity' => $item['quantity'] ?? 1,
                'sort_order' => $item['sort_order'] ?? $index,
                'notes' => $item['notes'] ?? null,
            ];
        }

        $package->services()->sync($payload);
    }

    /**
     * Replace the package tiers with the submitted list. Tiers missing from the
     * payload are removed; orders pointing at a removed tier fall back to the
     * package price through the payable resolver.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     */
    private function syncTiers(Package $package, array $tiers): void
    {
        $keptSlugs = [];

        foreach ($tiers as $index => $tier) {
            $keptSlugs[] = $tier['slug'];

            $package->tiers()->updateOrCreate(
                ['slug' => $tier['slug']],
                [
                    'name' => $tier['name'],
                    'description' => $tier['description'] ?? null,
                    'price' => $tier['price'] ?? null,
                    'currency' => strtoupper($tier['currency'] ?? $package->currency ?? 'SAR'),
                    'duration_days' => $tier['duration_days'] ?? null,
                    'revision_rounds' => $tier['revision_rounds'] ?? null,
                    'deliverables' => $tier['deliverables'] ?? null,
                    'is_active' => $tier['is_active'] ?? true,
                    'sort_order' => $tier['sort_order'] ?? $index,
                ]
            );
        }

        $package->tiers()->whereNotIn('slug', $keptSlugs)->delete();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated, ?Package $package = null): array
    {
        if (array_key_exists('price', $validated) && $validated['price'] === null) {
            $validated['price'] = 0;
        }

        if (array_key_exists('discount_amount', $validated) && $validated['discount_amount'] === null) {
            $validated['discount_amount'] = 0;
        }

        if (array_key_exists('sort_order', $validated) && $validated['sort_order'] === null) {
            $validated['sort_order'] = 0;
        }

        $slugSent = array_key_exists('slug', $validated);

        if ($slugSent && $validated['slug'] !== null) {
            return $validated;
        }

        if ($package !== null && ! $slugSent) {
            return $validated;
        }

        $validated['slug'] = Package::uniqueSlug(
            $validated['name'] ?? $package?->name ?? '',
            $package?->id
        );

        return $validated;
    }
}
