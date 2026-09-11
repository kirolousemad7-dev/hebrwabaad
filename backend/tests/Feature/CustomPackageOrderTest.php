<?php

namespace Tests\Feature;

use App\Enums\CatalogPricingMode;
use App\Enums\OrderStatus;
use App\Enums\ServiceCategory;
use App\Models\CatalogAddon;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPackageOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->accountManager()->create();
    }

    public function test_customer_can_create_multi_service_custom_package_order(): void
    {
        $customer = User::factory()->create();
        $a = $this->pricedService('content-strategy', 'استراتيجية محتوى', 500);
        $b = $this->pricedService('reels', 'ريلز', 800);

        $payload = $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [
                    ['service_id' => $a->id, 'quantity' => 1],
                    ['service_id' => $b->id, 'quantity' => 8],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_custom_package', true)
            ->assertJsonPath('data.requires_quote', false)
            ->assertJsonPath('data.payable.available', true)
            ->assertJsonPath('data.payable.amount', '6900.00')
            ->json('data');

        $this->assertCount(2, $payload['items']);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame(OrderStatus::Received, Order::query()->findOrFail($payload['id'])->status);
    }

    public function test_mixed_quote_service_marks_requires_quote_and_blocks_payable(): void
    {
        $customer = User::factory()->create();
        $fixed = $this->pricedService('social-designs', 'تصاميم', 300);
        $quote = Service::factory()->create([
            'name' => 'تصوير منتجات',
            'slug' => 'product-photography-custom',
            'category' => ServiceCategory::Production,
            'pricing_mode' => CatalogPricingMode::Quote,
            'base_price' => 0,
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [
                    ['service_id' => $fixed->id, 'quantity' => 2],
                    ['service_id' => $quote->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.requires_quote', true)
            ->assertJsonPath('data.payable.available', false)
            ->assertJsonPath('data.payable.reason', 'awaiting_owner_quote');
    }

    public function test_incompatible_service_addon_is_rejected(): void
    {
        $customer = User::factory()->create();
        $photo = $this->pricedService('product-photography', 'تصوير منتجات', 1000);
        $design = $this->pricedService('logo-design', 'شعار', 700);

        $addon = CatalogAddon::query()->create([
            'slug' => 'extra-photography',
            'name' => 'تصوير إضافي',
            'summary' => 'صور إضافية',
            'pricing_mode' => CatalogPricingMode::Quote,
            'is_active' => true,
            'is_public' => true,
        ]);
        $addon->services()->sync([$photo->id]);

        $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', [
                'items' => [
                    [
                        'service_id' => $design->id,
                        'quantity' => 1,
                        'addon_slugs' => ['extra-photography'],
                    ],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_empty_items_rejected(): void
    {
        $customer = User::factory()->create();

        $this->asUser($customer)
            ->postJson('/api/customer/orders/custom-package', ['items' => []])
            ->assertUnprocessable();
    }

    private function pricedService(string $slug, string $name, float $price): Service
    {
        return Service::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'category' => ServiceCategory::Production,
            'pricing_mode' => CatalogPricingMode::Fixed,
            'base_price' => $price,
            'currency' => 'SAR',
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($this->tokenFor($user));
    }
}
