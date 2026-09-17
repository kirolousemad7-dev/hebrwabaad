<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVisibility;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCatalogProfileTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_new_supplier_defaults_to_private_visibility(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->asUser($owner)->postJson('/api/admin/suppliers', [
            'name' => 'مورد خاص',
            'short_description' => 'وصف',
            'location' => 'الرياض',
        ])->assertCreated();

        $this->assertSame(SupplierVisibility::Private->value, $response->json('data.visibility'));
        $this->assertFalse($response->json('data.is_published'));
    }

    public function test_private_supplier_is_hidden_from_public_catalog(): void
    {
        $supplier = Supplier::factory()->create([
            'slug' => 'private-supplier',
            'is_published' => true,
            'visibility' => SupplierVisibility::Private,
            'profile_status' => ContentStatus::Published,
        ]);

        $this->getJson('/api/suppliers/'.$supplier->slug)->assertNotFound();
        $this->getJson('/api/suppliers')->assertOk()->assertJsonMissing(['slug' => 'private-supplier']);
    }

    public function test_owner_can_search_by_product_country_tag_and_price(): void
    {
        $owner = User::factory()->owner()->create();
        $tag = Tag::factory()->create(['name' => 'فاخر', 'scope' => 'supplier']);

        $match = Supplier::factory()->create([
            'name' => 'مطابع النور',
            'country' => 'السعودية',
            'city' => 'الرياض',
            'availability' => 'AVAILABLE',
        ]);
        $match->tags()->attach($tag->id);
        $match->products()->create([
            'name' => 'كروت فاخرة',
            'slug' => 'cards-luxury',
            'price' => 250,
            'currency' => 'SAR',
            'status' => ContentStatus::Draft,
            'visibility' => SupplierVisibility::Internal,
        ]);

        Supplier::factory()->create([
            'name' => 'آخر',
            'country' => 'مصر',
            'city' => 'القاهرة',
        ]);

        $this->asUser($owner)->getJson('/api/admin/suppliers?product=كروت&country=السعودية&tag=فاخر&price_min=100&price_max=300')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $match->id);
    }

    public function test_product_duplicate_archive_import_export(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();
        $product = SupplierProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'name' => 'منتج أصلي',
            'sku' => 'SKU-1',
            'visibility' => SupplierVisibility::Internal,
        ]);

        $dup = $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/products/'.$product->id.'/duplicate')
            ->assertCreated()
            ->json('data');

        $this->assertStringContainsString('نسخة', $dup['name']);
        $this->assertSame(ContentStatus::Draft->value, $dup['status']);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/products/'.$product->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', ContentStatus::Archived->value)
            ->assertJsonPath('data.visibility', SupplierVisibility::Private->value);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/products/import', [
            'rows' => [
                ['name' => 'مستورد 1', 'price' => 10, 'sku' => 'IMP-1'],
                ['name' => ''],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1);

        $export = $this->asUser($owner)->getJson('/api/admin/suppliers/'.$supplier->id.'/products/export')
            ->assertOk()
            ->json('data.items');

        $this->assertGreaterThanOrEqual(3, count($export));
    }

    public function test_service_duplicate_and_category_subcategory_tags_scopes(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $parent = $this->asUser($owner)->postJson('/api/admin/supplier-categories', [
            'name' => 'طباعة',
            'icon' => 'print',
            'seo_title' => 'طباعة',
        ])->assertCreated()->json('data');

        $child = $this->asUser($owner)->postJson('/api/admin/supplier-categories', [
            'name' => 'كروت',
            'parent_id' => $parent['id'],
        ])->assertCreated()->json('data');

        $this->assertSame($parent['id'], $child['parent_id']);

        $this->asUser($owner)->getJson('/api/admin/supplier-categories')
            ->assertOk()
            ->assertJsonPath('data.tree.0.id', $parent['id']);

        $tag = $this->asUser($owner)->postJson('/api/admin/tags', [
            'name' => 'سريع',
            'scope' => 'service',
        ])->assertCreated()->json('data');

        $service = $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/services', [
            'name' => 'خدمة طباعة',
            'category' => 'كروت',
            'service_area' => 'الرياض',
            'delivery_time' => '3 أيام',
            'tag_ids' => [$tag['id']],
            'attachments' => ['/docs/a.pdf'],
        ])->assertCreated()->json('data');

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/services/'.$service['id'].'/duplicate')
            ->assertCreated()
            ->assertJsonPath('data.is_active', false);

        $this->asUser($owner)->getJson('/api/admin/tags?scope=service')
            ->assertOk()
            ->assertJsonFragment(['name' => 'سريع']);
    }

    public function test_portfolio_defaults_to_internal_visibility(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/portfolio', [
            'title' => 'عمل',
            'description' => 'وصف العمل',
            'image' => '/brand/logo.png',
            'category' => 'طباعة',
            'client_type' => 'شركة',
            'documents' => ['/files/brief.pdf'],
            'videos' => ['https://example.com/v.mp4'],
        ])->assertCreated()
            ->assertJsonPath('data.visibility', SupplierVisibility::Internal->value);
    }

    public function test_supplier_profile_enrichment_fields_persist(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->unpublished()->create();

        $this->asUser($owner)->putJson('/api/admin/suppliers/'.$supplier->id, [
            'visibility' => SupplierVisibility::Public->value,
            'availability' => 'AVAILABLE',
            'delivery_time' => '5 أيام',
            'service_areas' => ['الرياض', 'جدة'],
            'certifications' => ['ISO 9001'],
            'is_published' => true,
        ])->assertOk()
            ->assertJsonPath('data.visibility', SupplierVisibility::Public->value)
            ->assertJsonPath('data.availability', 'AVAILABLE')
            ->assertJsonPath('data.service_areas.0', 'الرياض')
            ->assertJsonPath('data.certifications.0', 'ISO 9001');

        $supplier->refresh()->forceFill([
            'profile_status' => ContentStatus::Published,
            'is_active' => true,
            'status' => SupplierStatus::Active,
        ])->save();

        $this->getJson('/api/suppliers/'.$supplier->slug)
            ->assertOk()
            ->assertJsonPath('data.delivery_time', '5 أيام')
            ->assertJsonPath('data.service_areas.1', 'جدة');
    }
}
