<?php

namespace App\Services\Catalog;

use App\Enums\CatalogPricingMode;
use App\Models\Package;
use App\Models\PackageTier;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CatalogReadinessService
{
    /**
     * @return array{
     *     type: string,
     *     id: int,
     *     slug: string|null,
     *     name: string,
     *     checklist: array<string, bool>,
     *     missing: list<string>,
     *     is_price_complete: bool,
     *     is_purchasable: bool,
     *     pricing_mode: string|null
     * }
     */
    public function for(Model $item): array
    {
        return match (true) {
            $item instanceof Service => $this->forService($item),
            $item instanceof Package => $this->forPackage($item),
            $item instanceof PackageTier => $this->forTier($item),
            default => throw new \InvalidArgumentException('Unsupported catalog item type.'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function report(): array
    {
        $rows = [];

        foreach (Service::query()->orderBy('slug')->get() as $service) {
            $rows[] = $this->forService($service);
        }

        foreach (Package::query()->with('tiers')->orderBy('slug')->get() as $package) {
            $rows[] = $this->forPackage($package);

            foreach ($package->tiers as $tier) {
                $rows[] = $this->forTier($tier);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function forService(Service $service): array
    {
        $mode = $service->pricingMode();
        $checklist = [
            'description' => filled($service->description) || filled($service->summary),
            'scope' => filled($service->scope),
            'price' => $this->isPriceComplete($mode, $service->base_price),
            'duration' => $service->duration_days !== null && (int) $service->duration_days > 0,
            'revision' => $service->revision_rounds !== null,
        ];

        $isPriceComplete = $checklist['price'];
        $isPurchasable = $mode === CatalogPricingMode::Fixed
            && $isPriceComplete
            && $service->is_active
            && $service->is_public;

        return $this->payload('service', $service->id, $service->slug, $service->name, $checklist, $isPriceComplete, $isPurchasable, $mode->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function forPackage(Package $package): array
    {
        $mode = $package->pricingMode();
        $checklist = [
            'description' => filled($package->description),
            'scope' => filled($package->audience) || ! empty($package->deliverables),
            'price' => $this->isPriceComplete($mode, $package->finalPrice()),
            'duration' => $package->duration_days !== null && (int) $package->duration_days > 0,
            'revision' => $package->revision_rounds !== null,
        ];

        $isPriceComplete = $checklist['price'];
        $isPurchasable = $mode === CatalogPricingMode::Fixed
            && $isPriceComplete
            && $package->is_active
            && $package->is_public;

        return $this->payload('package', $package->id, $package->slug, $package->name, $checklist, $isPriceComplete, $isPurchasable, $mode->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function forTier(PackageTier $tier): array
    {
        $hasPrice = $tier->price !== null && (float) $tier->price > 0;
        $checklist = [
            'description' => filled($tier->description) || ! empty($tier->deliverables),
            'scope' => ! empty($tier->deliverables),
            'price' => $hasPrice,
            'duration' => $tier->duration_days !== null && (int) $tier->duration_days > 0,
            'revision' => $tier->revision_rounds !== null,
        ];

        $isPurchasable = $hasPrice && $tier->is_active;

        return $this->payload(
            'package_tier',
            $tier->id,
            $tier->slug,
            $tier->name,
            $checklist,
            $hasPrice,
            $isPurchasable,
            $hasPrice ? CatalogPricingMode::Fixed->value : CatalogPricingMode::Quote->value,
        );
    }

    private function isPriceComplete(CatalogPricingMode $mode, mixed $amount): bool
    {
        $value = (float) $amount;

        return match ($mode) {
            CatalogPricingMode::Fixed => $value > 0,
            CatalogPricingMode::StartingFrom => $value > 0,
            CatalogPricingMode::Quote => false,
        };
    }

    /**
     * @param  array<string, bool>  $checklist
     * @return array<string, mixed>
     */
    private function payload(
        string $type,
        int $id,
        ?string $slug,
        string $name,
        array $checklist,
        bool $isPriceComplete,
        bool $isPurchasable,
        ?string $pricingMode,
    ): array {
        /** @var Collection<string, bool> $collection */
        $collection = collect($checklist);

        return [
            'type' => $type,
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'checklist' => $checklist,
            'missing' => $collection->filter(fn (bool $ok) => ! $ok)->keys()->values()->all(),
            'is_price_complete' => $isPriceComplete,
            'is_purchasable' => $isPurchasable,
            'pricing_mode' => $pricingMode,
        ];
    }
}
