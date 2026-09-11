<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_suppliers_endpoint_hides_unpublished_suppliers(): void
    {
        Supplier::factory()->create([
            'name' => 'مورد عام',
            'slug' => 'public-supplier',
            'is_active' => true,
            'is_published' => true,
            'profile_status' => ContentStatus::Published,
        ]);
        Supplier::factory()->unpublished()->create([
            'name' => 'مورد خاص',
            'slug' => 'private-supplier',
        ]);

        $response = $this->getJson('/api/suppliers')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('public-supplier', $slugs);
        $this->assertNotContains('private-supplier', $slugs);

        $first = collect($response->json('data'))->firstWhere('slug', 'public-supplier');
        $this->assertIsArray($first);
        $this->assertArrayNotHasKey('internal_notes', $first);
        $this->assertArrayNotHasKey('cost', $first);
        $this->assertArrayNotHasKey('payment_terms', $first);
    }

    public function test_build_package_custom_order_route_requires_auth(): void
    {
        $this->postJson('/api/customer/orders/custom-package', [
            'items' => [['service_id' => 1, 'quantity' => 1]],
        ])->assertUnauthorized();
    }
}
