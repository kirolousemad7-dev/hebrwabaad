<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PrintingProduct;
use App\Models\User;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_return_safe_defaults_without_private_fields(): void
    {
        $payload = $this->getJson('/api/platform-settings')->assertOk()->json('data');

        $this->assertSame('حبر وأبعاد', $payload['brand']['name_ar']);
        $this->assertSame('/brand/logo.png', $payload['brand']['logo_url']);
        $this->assertArrayNotHasKey('commercial_register', $payload['business']);
        $this->assertArrayNotHasKey('tax_number', $payload['business']);
        $encoded = json_encode($payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('SERVER_KEY', $encoded);
        $this->assertStringNotContainsString('smtp', strtolower($encoded));
    }

    public function test_owner_can_update_contact_and_public_payload_reflects_it(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->putJson('/api/admin/platform-settings', [
                'contact' => [
                    'phone' => '0112345678',
                    'whatsapp_country_code' => '966',
                    'whatsapp_number' => '512345678',
                    'email' => 'hello@hebr.test',
                ],
            ])
            ->assertOk();

        $public = $this->getJson('/api/platform-settings')->assertOk()->json('data');
        $this->assertSame('0112345678', $public['contact']['phone']);
        $this->assertSame('hello@hebr.test', $public['contact']['email']);
        $this->assertSame('https://wa.me/966512345678', $public['contact']['whatsapp_url']);
    }

    public function test_invalid_social_url_is_rejected_and_disabled_links_hidden(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->putJson('/api/admin/platform-settings', [
                'social' => [
                    'items' => [
                        ['platform' => 'instagram', 'url' => 'javascript:alert(1)', 'enabled' => true, 'order' => 1],
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->asUser($owner)
            ->putJson('/api/admin/platform-settings', [
                'social' => [
                    'items' => [
                        ['platform' => 'instagram', 'url' => 'https://instagram.com/hebr', 'enabled' => false, 'order' => 1],
                        ['platform' => 'linkedin', 'url' => 'https://linkedin.com/company/hebr', 'enabled' => true, 'order' => 2],
                    ],
                ],
            ])
            ->assertOk();

        $social = $this->getJson('/api/platform-settings')->assertOk()->json('data.social');
        $this->assertCount(1, $social);
        $this->assertSame('linkedin', $social[0]['platform']);
    }

    public function test_employee_cannot_manage_platform_settings(): void
    {
        $employee = User::factory()->create(['role' => UserRole::GraphicDesigner]);

        $this->asUser($employee)
            ->putJson('/api/admin/platform-settings', [
                'brand' => ['name_ar' => 'اختراق'],
            ])
            ->assertForbidden();
    }

    public function test_owner_can_upload_logo_and_cache_invalidates(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();

        Cache::put(PlatformSettingService::CACHE_KEY, ['poison' => true], 60);

        $this->asUser($owner)
            ->post('/api/admin/platform-settings/brand-assets', [
                'slot' => 'logo',
                'file' => UploadedFile::fake()->image('logo.png', 200, 80),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $cached = Cache::get(PlatformSettingService::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('brand', $cached);
        $this->assertArrayNotHasKey('poison', $cached);

        $logo = $this->getJson('/api/platform-settings')->assertOk()->json('data.brand.logo_url');
        $this->assertIsString($logo);
        $this->assertNotSame('/brand/logo.png', $logo);
    }

    public function test_navigation_visibility_hides_suppliers_publicly(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->putJson('/api/admin/platform-settings', [
                'website' => [
                    'features' => ['show_suppliers' => false],
                    'navigation' => [
                        ['id' => 'suppliers', 'enabled' => false, 'order' => 90],
                        ['id' => 'build-package', 'enabled' => true, 'order' => 40],
                    ],
                ],
            ])
            ->assertOk();

        $website = $this->getJson('/api/platform-settings')->assertOk()->json('data.website');
        $this->assertFalse($website['features']['show_suppliers']);
        $ids = collect($website['navigation'])->pluck('id')->all();
        $this->assertNotContains('suppliers', $ids);
        $this->assertContains('build-package', $ids);
    }

    public function test_printing_catalog_public_hides_private_products(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->postJson('/api/admin/printing-catalog/products', [
                'name_ar' => 'كروت عامة',
                'slug' => 'public-cards',
                'pricing_mode' => 'QUOTE',
                'is_public' => true,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->asUser($owner)
            ->postJson('/api/admin/printing-catalog/products', [
                'name_ar' => 'منتج داخلي',
                'slug' => 'private-cards',
                'pricing_mode' => 'STARTING_FROM',
                'starting_price' => 100,
                'is_public' => false,
                'is_active' => true,
            ])
            ->assertCreated();

        $products = $this->getJson('/api/printing-catalog')->assertOk()->json('data.products');
        $slugs = collect($products)->pluck('slug')->all();
        $this->assertContains('public-cards', $slugs);
        $this->assertNotContains('private-cards', $slugs);
        $this->assertSame(2, PrintingProduct::query()->count());
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
