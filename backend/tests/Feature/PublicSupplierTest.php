<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_supplier_listing_works_without_authentication(): void
    {
        Supplier::factory()->create(['name' => 'مطبعة ظاهرة', 'slug' => 'visible-print']);

        $this->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'visible-print')
            ->assertJsonMissingPath('data.0.is_active')
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_public_listing_returns_only_active_suppliers(): void
    {
        Supplier::factory()->create(['slug' => 'live-supplier']);
        Supplier::factory()->inactive()->create(['slug' => 'hidden-supplier']);

        $response = $this->getJson('/api/suppliers');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'live-supplier');
    }

    public function test_supplier_detail_works_by_slug_and_includes_portfolio(): void
    {
        $supplier = Supplier::factory()->create([
            'slug' => 'golden-pack',
            'specialties' => ['التغليف'],
            'services' => ['علب'],
        ]);
        SupplierPortfolioItem::factory()->create([
            'supplier_id' => $supplier->id,
            'title' => 'علب منتجات',
            'sort_order' => 1,
        ]);
        SupplierPortfolioItem::factory()->inactive()->create([
            'supplier_id' => $supplier->id,
            'title' => 'عمل مخفي',
            'sort_order' => 0,
        ]);

        $this->getJson('/api/suppliers/golden-pack')
            ->assertOk()
            ->assertJsonPath('data.slug', 'golden-pack')
            ->assertJsonPath('data.portfolio_count', 1)
            ->assertJsonPath('data.portfolio.0.title', 'علب منتجات')
            ->assertJsonPath('data.portfolio.0.category', 'كروت شخصية');
    }

    public function test_invalid_supplier_slug_returns_not_found(): void
    {
        $this->getJson('/api/suppliers/non-existing-supplier')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Not found.');
    }

    public function test_portfolio_belongs_to_the_requested_supplier_only(): void
    {
        $first = Supplier::factory()->create(['slug' => 'first-print']);
        $second = Supplier::factory()->create(['slug' => 'second-print']);
        SupplierPortfolioItem::factory()->create([
            'supplier_id' => $first->id,
            'title' => 'عمل الأول',
        ]);
        SupplierPortfolioItem::factory()->create([
            'supplier_id' => $second->id,
            'title' => 'عمل الثاني',
        ]);

        $this->getJson('/api/suppliers/first-print')
            ->assertOk()
            ->assertJsonCount(1, 'data.portfolio')
            ->assertJsonPath('data.portfolio.0.title', 'عمل الأول');
    }

    public function test_specialty_and_service_filters_work(): void
    {
        Supplier::factory()->create([
            'slug' => 'sticker-house',
            'specialties' => ['الاستيكرات'],
            'services' => ['استيكرات'],
        ]);
        Supplier::factory()->create([
            'slug' => 'box-house',
            'specialties' => ['العلب'],
            'services' => ['علب'],
        ]);

        $this->getJson('/api/suppliers?specialty='.urlencode('الاستيكرات'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'sticker-house');

        $this->getJson('/api/suppliers?service='.urlencode('علب'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'box-house');
    }

    public function test_featured_filter_and_featured_suppliers_appear_first(): void
    {
        Supplier::factory()->create(['slug' => 'zeta-print', 'name' => 'زيتا', 'is_featured' => false]);
        Supplier::factory()->featured()->create(['slug' => 'alpha-print', 'name' => 'ألفا']);

        $this->getJson('/api/suppliers')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'alpha-print')
            ->assertJsonPath('data.0.featured', true);

        $this->getJson('/api/suppliers?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-print');
    }

    public function test_existing_auth_endpoints_remain_protected(): void
    {
        $this->getJson('/api/admin/test')
            ->assertUnauthorized();

        $token = User::factory()->create()->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/test')
            ->assertForbidden();
    }
}
