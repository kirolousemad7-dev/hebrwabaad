<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PlatformSetting;
use App\Models\PrintingProduct;
use App\Models\Sector;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Database\Seeders\OfficialCatalogSeeder;
use Database\Seeders\OfficialPrintingCatalogSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SultanPrintSupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function officialPrintingSlugs(): array
    {
        return [
            'luxury-business-cards',
            'company-brochure-printing',
            'corporate-stationery-printing',
            'custom-paper-bags',
            'printed-plastic-bags',
            'ecommerce-shipping-boxes',
            'custom-product-boxes',
            'luxury-gift-boxes',
            'food-packaging-boxes',
            'printed-paper-cups',
            'product-stickers-labels',
            'roll-label-printing',
            'restaurant-menu-printing',
            'rollup-banner-printing',
            'promotional-gifts-printing',
        ];
    }

    private function seedOfficialCatalog(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(PackageSeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(OfficialCatalogSeeder::class);
        $this->seed(OfficialPrintingCatalogSeeder::class);
        $this->seed(SultanPrintSupplierSeeder::class);
    }

    public function test_official_catalog_seed_is_idempotent_and_keeps_platform_and_supplier_separate(): void
    {
        $this->seedOfficialCatalog();
        $this->seedOfficialCatalog();

        $this->assertSame(8, Package::query()->count());
        $this->assertSame(12, Sector::query()->count());
        $this->assertSame(15, PrintingProduct::query()->publiclyVisible()->count());
        $this->assertSame(1, Supplier::query()->where('slug', SultanPrintSupplierSeeder::SLUG)->count());
        $this->assertSame(
            15,
            SupplierProduct::query()
                ->where('supplier_id', Supplier::query()->where('slug', SultanPrintSupplierSeeder::SLUG)->value('id'))
                ->count(),
        );

        foreach ($this->officialPrintingSlugs() as $slug) {
            $this->assertTrue(
                PrintingProduct::query()->publiclyVisible()->where('slug', $slug)->exists(),
                "Missing official printing product {$slug}",
            );
        }

        $this->assertFalse(
            PrintingProduct::query()->whereIn('slug', ['standard-business-cards', 'premium-business-cards', 'a5-flyers'])
                ->publiclyVisible()
                ->exists(),
        );

        $sultanSkus = SupplierProduct::query()
            ->whereHas('supplier', fn ($query) => $query->where('slug', SultanPrintSupplierSeeder::SLUG))
            ->pluck('sku')
            ->all();

        $this->assertCount(15, $sultanSkus);
        $this->assertEmpty(array_intersect($sultanSkus, $this->officialPrintingSlugs()));
        $this->assertTrue(
            SupplierProduct::query()
                ->whereHas('supplier', fn ($query) => $query->where('slug', SultanPrintSupplierSeeder::SLUG))
                ->where('contact_for_price', true)
                ->whereNull('price')
                ->count() === 15,
        );
    }

    public function test_public_apis_expose_official_printing_sultan_and_solution_aliases(): void
    {
        $this->seedOfficialCatalog();

        $printing = $this->getJson('/api/printing-catalog')->assertOk()->json('data.products');
        $this->assertCount(15, $printing);
        $this->assertEqualsCanonicalizing(
            $this->officialPrintingSlugs(),
            collect($printing)->pluck('slug')->all(),
        );

        $this->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonFragment(['slug' => SultanPrintSupplierSeeder::SLUG]);

        $supplier = $this->getJson('/api/suppliers/'.SultanPrintSupplierSeeder::SLUG)
            ->assertOk()
            ->json('data');

        $this->assertSame('مطبعة السلطان للدعاية والإعلان والطباعة الرقمية', $supplier['name']);
        $this->assertCount(15, $supplier['products']);

        $this->getJson('/api/suppliers/'.SultanPrintSupplierSeeder::SLUG.'/products')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 15)
            ->assertJsonCount(15, 'data.items');

        $this->getJson('/api/sectors/b2b-industrial')
            ->assertOk()
            ->assertJsonPath('data.slug', 'industry-b2b');

        $this->getJson('/api/sectors/health-beauty')
            ->assertOk()
            ->assertJsonPath('data.slug', 'health-clinics');

        $this->getJson('/api/services?subcategory=diagnosis-strategy')
            ->assertOk()
            ->assertJsonPath('success', true);

        $diagnosis = collect($this->getJson('/api/services?subcategory=diagnosis-strategy')->json('data'));
        $this->assertGreaterThanOrEqual(8, $diagnosis->count());
        $this->assertTrue($diagnosis->contains(fn (array $row) => $row['slug'] === 'marketing-business-diagnosis'));

        $brand = PlatformSetting::query()->where('key', 'brand')->value('value');
        $decoded = is_array($brand) && array_key_exists('value', $brand) ? $brand['value'] : $brand;
        $this->assertSame('نمنح أعمالك أبعادًا للنمو', $decoded['tagline'] ?? null);

        $this->assertTrue(Service::query()->where('slug', 'social-media-management')->where('is_public', true)->exists());
        $this->assertTrue(Service::query()->where('slug', 'store-branch-opening')->exists());
        $this->assertSame(
            'باقة إطلاق مشروع',
            Package::query()->where('slug', 'foundation-package')->value('name'),
        );
    }
}
