<?php

namespace Tests\Feature;

use App\Enums\PackageCategory;
use App\Enums\ServiceCategory;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_service_list_only_returns_active_services(): void
    {
        Service::factory()->create(['name' => 'Active Service', 'slug' => 'active-service']);
        Service::factory()->inactive()->create(['name' => 'Hidden Service', 'slug' => 'hidden-service']);

        $response = $this->getJson('/api/services');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'active-service');
    }

    public function test_public_service_response_hides_management_fields(): void
    {
        Service::factory()->create(['slug' => 'visible-service']);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonMissingPath('data.0.is_active')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'summary', 'description', 'category', 'base_price', 'currency', 'duration_days', 'is_featured'],
                ],
            ]);
    }

    public function test_public_service_show_works_by_slug_and_by_id(): void
    {
        $service = Service::factory()->create(['slug' => 'brand-identity']);

        $this->getJson('/api/services/brand-identity')
            ->assertOk()
            ->assertJsonPath('data.slug', 'brand-identity');

        $this->getJson('/api/services/'.$service->id)
            ->assertOk()
            ->assertJsonPath('data.id', $service->id);
    }

    public function test_public_service_show_hides_inactive_service(): void
    {
        Service::factory()->inactive()->create(['slug' => 'archived-service']);

        $this->getJson('/api/services/archived-service')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_public_service_list_can_filter_by_category(): void
    {
        Service::factory()->create(['slug' => 'printing-one', 'category' => ServiceCategory::Printing]);
        Service::factory()->create(['slug' => 'content-one', 'category' => ServiceCategory::Content]);

        $this->getJson('/api/services?category=PRINTING')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'printing-one');
    }

    public function test_public_package_list_only_returns_active_packages(): void
    {
        Package::factory()->create(['slug' => 'active-package']);
        Package::factory()->inactive()->create(['slug' => 'hidden-package']);

        $this->getJson('/api/packages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'active-package');
    }

    public function test_public_package_list_can_filter_by_category(): void
    {
        Package::factory()->create([
            'slug' => 'marketing-visible',
            'category' => PackageCategory::Marketing,
        ]);
        Package::factory()->create([
            'slug' => 'events-hidden',
            'category' => PackageCategory::Events,
        ]);
        Package::factory()->inactive()->create([
            'slug' => 'marketing-inactive',
            'category' => PackageCategory::Marketing,
        ]);

        $this->getJson('/api/packages?category=MARKETING')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'marketing-visible')
            ->assertJsonPath('data.0.category', 'MARKETING');

        $this->getJson('/api/packages?category=EVENTS')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'events-hidden')
            ->assertJsonPath('data.0.category', 'EVENTS');
    }

    public function test_public_package_includes_its_services_and_final_price(): void
    {
        $package = Package::factory()->create([
            'slug' => 'marketing-starter',
            'category' => PackageCategory::Marketing,
            'price' => 1500,
            'discount_amount' => 100,
        ]);

        $service = Service::factory()->create(['slug' => 'campaign-management']);
        $package->services()->attach($service->id, ['quantity' => 2, 'sort_order' => 1, 'notes' => 'شهرياً']);

        $this->getJson('/api/packages/marketing-starter')
            ->assertOk()
            ->assertJsonPath('data.final_price', '1400.00')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.notes', 'شهرياً')
            ->assertJsonPath('data.items.0.service.slug', 'campaign-management')
            ->assertJsonMissingPath('data.is_active');
    }

    public function test_public_package_excludes_inactive_services_from_items(): void
    {
        $package = Package::factory()->create(['slug' => 'mixed-package']);
        $active = Service::factory()->create(['slug' => 'active-item']);
        $inactive = Service::factory()->inactive()->create(['slug' => 'inactive-item']);

        $package->services()->attach([$active->id, $inactive->id]);

        $this->getJson('/api/packages/mixed-package')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.service.slug', 'active-item');
    }

    public function test_public_package_show_hides_inactive_package(): void
    {
        Package::factory()->inactive()->create(['slug' => 'draft-package']);

        $this->getJson('/api/packages/draft-package')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
