<?php

namespace App\Services\Consultant;

use App\Models\Package;
use App\Models\Service;
use App\Support\PrintingCatalog;

class CatalogContext
{
    /**
     * Minimal active catalog sent to engines. Never the full database.
     *
     * @return array{packages: list<array<string, mixed>>, services: list<array<string, mixed>>, printing_slugs: list<string>}
     */
    public function snapshot(): array
    {
        $packages = Package::query()
            ->active()
            ->with(['items' => fn ($query) => $query->whereHas('service', fn ($service) => $service->active())->with('service')])
            ->orderBy('name')
            ->get()
            ->map(fn (Package $package) => [
                'id' => $package->id,
                'slug' => $package->slug,
                'name' => $package->name,
                'description' => $package->description,
                'category' => $package->category->value,
                'price' => (float) $package->price,
                'discount_amount' => (float) $package->discount_amount,
                'final_price' => (float) $package->finalPrice(),
                'currency' => $package->currency,
                'duration_days' => $package->duration_days,
                'is_active' => true,
                'items' => $package->items->map(fn ($item) => [
                    'service_id' => $item->service_id,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'service' => $item->service ? [
                        'id' => $item->service->id,
                        'slug' => $item->service->slug,
                        'name' => $item->service->name,
                        'category' => $item->service->category->value,
                    ] : null,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $services = Service::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'summary' => $service->summary,
                'category' => $service->category->value,
                'base_price' => (float) $service->base_price,
                'currency' => $service->currency,
                'duration_days' => $service->duration_days,
                'is_active' => true,
            ])
            ->values()
            ->all();

        return [
            'packages' => $packages,
            'services' => $services,
            'printing_slugs' => PrintingCatalog::slugs(),
        ];
    }
}
