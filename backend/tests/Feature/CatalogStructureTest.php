<?php

namespace Tests\Feature;

use App\Enums\CatalogPricingMode;
use App\Models\Package;
use App\Models\PackageTier;
use App\Models\Service;
use Database\Seeders\PackageSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogStructureTest extends TestCase
{
    use RefreshDatabase;

    private const PACKAGE_NAMES = [
        'foundation-package' => 'باقة إطلاق مشروع',
        'brand-building' => 'باقة بناء البراند',
        'digital-marketing-package' => 'باقة السوشيال الشهرية',
        'product-launch' => 'باقة إطلاق منتج',
        'ecommerce-launch-package' => 'باقة المتجر الإلكتروني',
        'restaurants' => 'باقة المطاعم',
        'b2b-companies' => 'باقة الشركات B2B',
        'events-package' => 'باقة الفعالية',
    ];

    public function test_seeder_aligns_the_eight_documented_packages_with_three_levels_each(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->assertSame(8, Package::query()->count());

        foreach (self::PACKAGE_NAMES as $slug => $name) {
            $package = Package::query()->where('slug', $slug)->first();

            $this->assertNotNull($package, "Missing package {$slug}");
            $this->assertSame($name, $package->name);
            $this->assertNotNull($package->audience);
            $this->assertNotEmpty($package->deliverables);

            $tiers = PackageTier::query()->where('package_id', $package->id)->orderBy('sort_order')->get();

            $this->assertSame(['basic', 'professional', 'advanced'], $tiers->pluck('slug')->all());
            $this->assertSame(['أساسية', 'احترافية', 'متقدمة'], $tiers->pluck('name')->all());
        }
    }

    public function test_seeder_is_idempotent_and_keeps_owner_pricing(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(PackageSeeder::class);

        $package = Package::query()->where('slug', 'brand-building')->firstOrFail();
        $package->update(['price' => '21000.00', 'pricing_mode' => CatalogPricingMode::Fixed]);

        $tier = PackageTier::query()->where('package_id', $package->id)->where('slug', 'professional')->firstOrFail();
        $tier->update(['price' => '18000.00']);

        $this->seed(ServiceSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->assertSame(8, Package::query()->count());
        $this->assertSame(24, PackageTier::query()->count());
        $this->assertSame('21000.00', $package->fresh()->price);
        $this->assertSame(CatalogPricingMode::Fixed, $package->fresh()->pricingMode());
        $this->assertSame('18000.00', $tier->fresh()->price);
    }

    public function test_service_catalog_covers_the_documented_categories_without_invented_prices(): void
    {
        $this->seed(ServiceSeeder::class);

        $services = Service::query()->get();

        $this->assertGreaterThanOrEqual(40, $services->count());
        $this->assertSame(
            ['CAMPAIGNS', 'CONTENT', 'OTHER', 'PRINTING', 'PRODUCTION', 'STORES', 'STRATEGY'],
            $services->pluck('category')->map(fn ($category) => $category->value)->unique()->sort()->values()->all(),
        );

        foreach ($services as $service) {
            if ($service->pricingMode() === CatalogPricingMode::Quote) {
                $this->assertSame('0.00', $service->base_price, "Quote service {$service->slug} must not carry a price");
                $this->assertFalse($service->isChargeable());
            } else {
                $this->assertTrue((float) $service->base_price > 0, "Priced service {$service->slug} needs a price");
            }
        }
    }

    public function test_quote_mode_catalog_items_are_not_directly_chargeable(): void
    {
        $quotePackage = Package::factory()->quote()->create(['price' => '0.00']);
        $fixedPackage = Package::factory()->create(['price' => '5000.00', 'discount_amount' => '0.00']);

        $this->assertFalse($quotePackage->isChargeable());
        $this->assertTrue($fixedPackage->isChargeable());

        $unpricedTier = PackageTier::factory()->for($fixedPackage)->create(['price' => null]);
        $pricedTier = PackageTier::factory()->for($fixedPackage)->priced(7000)->create(['slug' => 'priced-tier']);

        $this->assertFalse($unpricedTier->isPriced());
        $this->assertTrue($pricedTier->isPriced());
    }
}
