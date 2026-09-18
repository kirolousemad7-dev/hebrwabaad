<?php

namespace Tests\Feature;

use App\Enums\ServiceCategory;
use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementPhase9Test extends TestCase
{
    use RefreshDatabase;

    private function asRole(UserRole $role): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(
            User::factory()->create(['role' => $role])->createToken('auth')->plainTextToken
        );
    }

    public function test_owner_can_create_and_update_service_marketing_fields(): void
    {
        $created = $this->asRole(UserRole::Owner)
            ->postJson('/api/admin/services', [
                'name' => 'هوية بصرية متكاملة',
                'category' => ServiceCategory::Content->value,
                'subcategory' => 'branding',
                'short_description' => 'وصف مختصر',
                'description' => 'وصف تفصيلي للخدمة',
                'features' => ['تحليل', 'تصميم'],
                'process_steps' => [
                    ['title' => 'اكتشاف', 'description' => 'جلسات فهم'],
                    ['title' => 'تنفيذ', 'description' => 'تسليم'],
                ],
                'faq' => [
                    ['question' => 'كم المدة؟', 'answer' => 'من أسبوعين'],
                ],
                'tags' => ['هوية', 'تصميم'],
                'hero_image' => '/storage/hero.jpg',
                'gallery' => ['/storage/a.jpg', '/storage/b.jpg'],
                'base_price' => 4500,
                'pricing_mode' => 'FIXED',
                'is_public' => true,
                'sort_order' => 3,
                'seo_title' => 'هوية بصرية | حبر وأبعاد',
                'seo_description' => 'خدمة هوية بصرية احترافية',
                'og_title' => 'هوية بصرية',
                'og_description' => 'وصف OG',
                'og_image' => '/storage/og.jpg',
                'canonical_url' => 'https://example.test/services/brand-identity-x',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subcategory', 'branding')
            ->assertJsonPath('data.features.0', 'تحليل')
            ->assertJsonPath('data.seo.title', 'هوية بصرية | حبر وأبعاد')
            ->assertJsonPath('data.is_public', true)
            ->json('data');

        $this->asRole(UserRole::Owner)
            ->putJson('/api/admin/services/'.$created['id'], [
                'name' => 'هوية بصرية متكاملة',
                'category' => ServiceCategory::Content->value,
                'faq' => [
                    ['question' => 'هل يشمل الشعار؟', 'answer' => 'نعم'],
                ],
                'seo_title' => 'عنوان محدّث',
            ])
            ->assertOk()
            ->assertJsonPath('data.faq.0.question', 'هل يشمل الشعار؟')
            ->assertJsonPath('data.seo_title', 'عنوان محدّث');
    }

    public function test_owner_can_sync_service_relations(): void
    {
        $supplier = Supplier::factory()->create();
        $service = Service::factory()->create();

        $this->asRole(UserRole::Owner)
            ->putJson('/api/admin/services/'.$service->id, [
                'name' => $service->name,
                'category' => $service->category->value,
                'supplier_ids' => [$supplier->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.supplier_ids.0', $supplier->id);

        $this->assertTrue($service->fresh()->suppliers()->whereKey($supplier->id)->exists());
    }

    public function test_public_visibility_hides_inactive_and_private_services(): void
    {
        Service::factory()->create([
            'slug' => 'visible-service',
            'is_active' => true,
            'is_public' => true,
            'name' => 'Visible',
        ]);
        Service::factory()->create([
            'slug' => 'private-service',
            'is_active' => true,
            'is_public' => false,
            'name' => 'Private',
        ]);
        Service::factory()->inactive()->create([
            'slug' => 'inactive-service',
            'is_public' => true,
            'name' => 'Inactive',
        ]);

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'visible-service'])
            ->assertJsonMissing(['slug' => 'private-service'])
            ->assertJsonMissing(['slug' => 'inactive-service']);

        $this->getJson('/api/services/private-service')->assertNotFound();
        $this->getJson('/api/services/visible-service')
            ->assertOk()
            ->assertJsonPath('data.slug', 'visible-service')
            ->assertJsonStructure(['data' => ['seo', 'packages', 'addons', 'portfolio', 'related_services']]);
    }

    public function test_public_service_seo_payload_and_sitemap_include_slug(): void
    {
        Service::factory()->create([
            'slug' => 'seo-service',
            'is_active' => true,
            'is_public' => true,
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Description',
            'og_title' => 'OG Title',
            'og_description' => 'OG Description',
            'og_image' => '/brand/og.png',
            'canonical_url' => 'https://hebr.test/services/seo-service',
        ]);

        $this->getJson('/api/services/seo-service')
            ->assertOk()
            ->assertJsonPath('data.seo.title', 'SEO Title')
            ->assertJsonPath('data.seo.description', 'SEO Description')
            ->assertJsonPath('data.seo.og_title', 'OG Title')
            ->assertJsonPath('data.seo.og_image', '/brand/og.png')
            ->assertJsonPath('data.seo.canonical_url', 'https://hebr.test/services/seo-service');

        $this->get('/api/sitemap.xml')
            ->assertOk()
            ->assertSee('/services/seo-service', false);
    }

    public function test_customer_cannot_manage_services_but_can_view_public(): void
    {
        $service = Service::factory()->create([
            'slug' => 'customer-visible',
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->asRole(UserRole::Customer)
            ->getJson('/api/admin/services')
            ->assertForbidden();

        $this->asRole(UserRole::Customer)
            ->postJson('/api/admin/services', [
                'name' => 'Hacked',
                'category' => ServiceCategory::Other->value,
            ])
            ->assertForbidden();

        $this->getJson('/api/services/'.$service->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', 'customer-visible')
            ->assertJsonMissingPath('data.is_active')
            ->assertJsonMissingPath('data.department_id');
    }

    public function test_employee_cannot_manage_services(): void
    {
        $this->asRole(UserRole::WebDeveloper)
            ->getJson('/api/admin/services')
            ->assertForbidden();
    }
}
