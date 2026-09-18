<?php

namespace Tests\Feature;

use App\Enums\CatalogPricingMode;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Models\CatalogAddon;
use App\Models\EventRequest;
use App\Models\Package;
use App\Models\PackageTier;
use App\Models\PortfolioItem;
use App\Models\PrintingRequest;
use App\Models\RecommendationGoal;
use App\Models\Sector;
use App\Models\Service;
use App\Models\User;
use App\Services\Catalog\CatalogReadinessService;
use App\Services\Catalog\GoalRecommendationService;
use Database\Seeders\CatalogAddonSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RecommendationGoalSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformStructureCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlatformCatalog(): void
    {
        $this->seed(ServiceSeeder::class);
        $this->seed(PackageSeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CatalogAddonSeeder::class);
        $this->seed(RecommendationGoalSeeder::class);
    }

    private function tokenFor(UserRole $role): string
    {
        return User::factory()->create(['role' => $role])->createToken('auth')->plainTextToken;
    }

    public function test_pdf_catalog_seed_counts_and_required_services(): void
    {
        $this->seedPlatformCatalog();

        $this->assertSame(12, Sector::query()->count());
        $this->assertTrue(Service::query()->where('slug', 'social-media-plan')->exists());
        $this->assertTrue(Service::query()->where('slug', 'video-editing')->exists());
        $this->assertSame(8, Package::query()->count());
        $this->assertSame(24, PackageTier::query()->count());
        $this->assertSame(12, CatalogAddon::query()->count());
        $this->assertSame(8, RecommendationGoal::query()->count());

        foreach (Package::query()->get() as $package) {
            $this->assertSame(3, $package->tiers()->count(), "Package {$package->slug} must have 3 tiers");
        }
    }

    public function test_recommendation_goals_resolve_only_real_catalog_items(): void
    {
        $this->seedPlatformCatalog();

        $service = app(GoalRecommendationService::class);
        $result = $service->recommendForSlugs(['launch-project']);

        $this->assertNotNull($result);
        $this->assertTrue($result['matched']);
        $this->assertNotNull($result['package']);
        $this->assertTrue(Package::query()->where('slug', $result['package']['slug'])->exists());

        foreach ($result['services'] as $row) {
            $this->assertTrue(Service::query()->where('slug', $row['slug'])->exists());
        }

        foreach ($result['addons'] as $row) {
            $this->assertTrue(CatalogAddon::query()->where('slug', $row['slug'])->exists());
        }

        foreach (RecommendationGoal::query()->with('items')->get() as $goal) {
            foreach ($goal->items as $item) {
                $exists = match ($item->item_type) {
                    'service' => Service::query()->where('slug', $item->item_slug)->exists(),
                    'package' => Package::query()->where('slug', $item->item_slug)->exists(),
                    'addon' => CatalogAddon::query()->where('slug', $item->item_slug)->exists(),
                    default => false,
                };
                $this->assertTrue($exists, "Goal {$goal->slug} item {$item->item_slug} missing from catalog");
            }
        }
    }

    public function test_incomplete_price_is_not_purchasable(): void
    {
        $quote = Service::factory()->create([
            'pricing_mode' => CatalogPricingMode::Quote,
            'base_price' => 0,
            'description' => 'وصف',
            'scope' => 'نطاق',
            'duration_days' => 7,
            'revision_rounds' => 2,
        ]);

        $starting = Service::factory()->create([
            'pricing_mode' => CatalogPricingMode::StartingFrom,
            'base_price' => 1500,
            'description' => 'وصف',
            'scope' => 'نطاق',
            'duration_days' => 7,
            'revision_rounds' => 1,
        ]);

        $fixed = Service::factory()->create([
            'pricing_mode' => CatalogPricingMode::Fixed,
            'base_price' => 2000,
            'description' => 'وصف',
            'scope' => 'نطاق',
            'duration_days' => 5,
            'revision_rounds' => 1,
        ]);

        $readiness = app(CatalogReadinessService::class);

        $this->assertFalse($readiness->for($quote)['is_purchasable']);
        $this->assertFalse($readiness->for($quote)['is_price_complete']);
        $this->assertFalse($readiness->for($starting)['is_purchasable']);
        $this->assertTrue($readiness->for($starting)['is_price_complete']);
        $this->assertTrue($readiness->for($fixed)['is_purchasable']);
    }

    public function test_urgent_addon_hidden_when_capacity_unavailable(): void
    {
        $this->seed(CatalogAddonSeeder::class);

        $urgent = CatalogAddon::query()->where('slug', 'urgent-service')->firstOrFail();
        $this->assertTrue($urgent->requires_capacity);
        $this->assertFalse($urgent->capacity_available);
        $this->assertFalse($urgent->isAvailable());

        $this->getJson('/api/addons')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'urgent-service']);
    }

    public function test_printing_reorder_copies_specs_not_price(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $source = PrintingRequest::factory()->completed()->create([
            'user_id' => $customer->id,
            'product_slug' => 'standard-business-cards',
            'quantity' => 500,
            'material' => 'ورق كوشيه',
            'estimated_price' => '999.00',
            'quoted_price' => '1200.00',
            'pricing_notes' => 'سعر قديم',
        ]);

        $token = $customer->createToken('auth')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/printing-requests/'.$source->id.'/reorder', ['quantity' => 1000])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $newId = $response->json('data.id');
        $reordered = PrintingRequest::query()->findOrFail($newId);

        $this->assertSame($source->id, $reordered->reordered_from_id);
        $this->assertSame($source->product_slug, $reordered->product_slug);
        $this->assertSame($source->material, $reordered->material);
        $this->assertSame(1000, $reordered->quantity);
        $this->assertSame(PrintingRequestStatus::Pending, $reordered->status);
        $this->assertNotSame('999.00', (string) $reordered->estimated_price);
        $this->assertNull($reordered->quoted_price);
    }

    public function test_event_request_creates_row_and_optional_project(): void
    {
        User::factory()->create(['role' => UserRole::AccountManager]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $token = $customer->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/event-requests', [
                'event_type' => 'افتتاح فرع',
                'city' => 'جدة',
                'attendance' => 200,
                'create_project' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.event_type', 'افتتاح فرع')
            ->assertJsonPath('data.status', EventRequest::STATUS_IN_REVIEW);

        $this->assertDatabaseHas('event_requests', [
            'user_id' => $customer->id,
            'event_type' => 'افتتاح فرع',
        ]);

        $event = EventRequest::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertNotNull($event->project_id);
    }

    public function test_portfolio_case_study_fields_are_exposed_when_set(): void
    {
        $item = PortfolioItem::factory()->create([
            'brand_name' => 'براند تجريبي',
            'challenge' => 'التحدي',
            'solution' => 'الحل',
            'execution' => 'التنفيذ',
            'deliverables' => ['هوية', 'محتوى'],
            'results' => 'النتائج',
            'is_published' => true,
        ]);

        $this->getJson('/api/portfolio/'.$item->slug)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $item->id,
                'brand_name' => 'براند تجريبي',
                'challenge' => 'التحدي',
                'solution' => 'الحل',
                'execution' => 'التنفيذ',
                'results' => 'النتائج',
            ]);
    }

    public function test_unpublished_sector_is_hidden_from_public(): void
    {
        $this->seed(SectorSeeder::class);

        $sector = Sector::query()->firstOrFail();
        $sector->update(['is_public' => false]);

        $this->getJson('/api/sectors')
            ->assertOk()
            ->assertJsonMissing(['slug' => $sector->slug]);

        $this->getJson('/api/sectors/'.$sector->slug)
            ->assertNotFound();

        $this->getJson('/api/sectors')
            ->assertJsonCount(11, 'data');
    }

    public function test_public_sectors_and_goals_endpoints(): void
    {
        $this->seedPlatformCatalog();

        $this->getJson('/api/sectors')
            ->assertOk()
            ->assertJsonCount(12, 'data');

        $this->getJson('/api/sectors/restaurants-cafes')
            ->assertOk()
            ->assertJsonPath('data.slug', 'restaurants-cafes');

        $this->getJson('/api/recommendation-goals')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    public function test_owner_can_view_catalog_readiness_report(): void
    {
        Service::factory()->create();

        $this->withToken($this->tokenFor(UserRole::Owner))
            ->getJson('/api/admin/catalog-readiness')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'summary']]);
    }
}
