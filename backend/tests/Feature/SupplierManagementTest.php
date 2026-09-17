<?php

namespace Tests\Feature;

use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\SupplierContact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_create_supplier_with_lifecycle_defaults_and_code(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->asUser($owner)->postJson('/api/admin/suppliers', [
            'name' => 'مورد تجريبي',
            'short_description' => 'وصف قصير',
            'location' => 'الرياض',
        ])->assertCreated();

        $supplierId = $response->json('data.id');
        $supplier = Supplier::query()->findOrFail($supplierId);

        $this->assertSame('SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT), $supplier->supplier_code);
        $this->assertSame('مورد تجريبي', $supplier->display_name);
        $this->assertSame('مورد تجريبي', $supplier->legal_name);
        $this->assertSame(SupplierStatus::Pending, $supplier->status);
        $this->assertSame(SupplierVerificationStatus::Unverified, $supplier->verification_status);
        $this->assertSame($owner->id, $supplier->created_by);
        $this->assertSame($owner->id, $supplier->updated_by);
    }

    public function test_owner_can_manage_contacts_services_categories_tags_and_lifecycle(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/contacts', [
            'name' => 'أحمد',
            'email' => 'ahmad@example.com',
            'is_primary' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'أحمد')
            ->assertJsonPath('data.is_primary', true);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/services', [
            'name' => 'طباعة',
            'pricing_model' => 'FIXED',
            'minimum_price' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.pricing_model', 'FIXED');

        $category = $this->asUser($owner)->postJson('/api/admin/supplier-categories', [
            'name' => 'طباعة تجارية',
        ])->assertCreated()
            ->json('data');

        $tag = $this->asUser($owner)->postJson('/api/admin/tags', [
            'name' => 'مفضل',
        ])->assertCreated()
            ->json('data');

        $this->asUser($owner)->putJson('/api/admin/suppliers/'.$supplier->id, [
            'category_ids' => [$category['id']],
            'tag_ids' => [$tag['id']],
            'city' => 'جدة',
        ])->assertOk()
            ->assertJsonPath('data.city', 'جدة');

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', SupplierStatus::Active->value);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/verify', [
            'verification_status' => SupplierVerificationStatus::FullyVerified->value,
        ])->assertOk()
            ->assertJsonPath('data.verification_status', SupplierVerificationStatus::FullyVerified->value);

        $this->asUser($owner)->getJson('/api/admin/suppliers/'.$supplier->id)
            ->assertOk()
            ->assertJsonPath('data.supplier.city', 'جدة')
            ->assertJsonCount(1, 'data.contacts')
            ->assertJsonCount(1, 'data.services')
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonCount(1, 'data.tags');
    }

    public function test_nested_contact_update_returns_404_when_contact_belongs_to_another_supplier(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();
        $other = Supplier::factory()->create();
        $contact = SupplierContact::factory()->create(['supplier_id' => $other->id]);

        $this->asUser($owner)
            ->putJson('/api/admin/suppliers/'.$supplier->id.'/contacts/'.$contact->id, [
                'name' => 'مسروق',
            ])
            ->assertNotFound();
    }

    public function test_public_supplier_resource_hides_contact_unless_show_public_contact(): void
    {
        $supplier = Supplier::factory()->create([
            'slug' => 'public-contact-test',
            'phone' => '0500000000',
            'email' => 'public@example.com',
            'website' => 'https://example.com',
            'address' => 'شارع الملك',
            'show_public_contact' => false,
            'is_published' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/suppliers/'.$supplier->slug)
            ->assertOk()
            ->assertJsonMissing(['phone' => '0500000000'])
            ->assertJsonMissing(['email' => 'public@example.com']);

        $supplier->forceFill(['show_public_contact' => true])->save();

        $this->getJson('/api/suppliers/'.$supplier->slug)
            ->assertOk()
            ->assertJsonPath('data.phone', '0500000000')
            ->assertJsonPath('data.email', 'public@example.com')
            ->assertJsonPath('data.website', 'https://example.com');
    }

    public function test_customer_cannot_access_admin_supplier_management(): void
    {
        $customer = User::factory()->create();
        $supplier = Supplier::factory()->create();
        SupplierCategory::factory()->create();
        Tag::factory()->create();

        $this->asUser($customer)->getJson('/api/admin/suppliers')->assertForbidden();
        $this->asUser($customer)->postJson('/api/admin/suppliers/'.$supplier->id.'/approve')->assertForbidden();
        $this->asUser($customer)->getJson('/api/admin/supplier-categories')->assertForbidden();
        $this->asUser($customer)->getJson('/api/admin/tags')->assertForbidden();
    }

    public function test_owner_can_manage_products_portfolio_documents_and_suspend(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::factory()->create();

        $product = $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/products', [
            'name' => 'منتج داخلي',
            'short_description' => 'غير منشور للعامة',
            'price' => 250,
            'currency' => 'SAR',
        ])->assertCreated()
            ->assertJsonPath('data.visibility', 'INTERNAL')
            ->json('data');

        $this->asUser($owner)->putJson('/api/admin/suppliers/'.$supplier->id.'/products/'.$product['id'], [
            'name' => 'منتج داخلي محدّث',
            'sku' => 'SKU-1',
        ])->assertOk()
            ->assertJsonPath('data.sku', 'SKU-1');

        $portfolio = $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/portfolio', [
            'title' => 'أعمال سابقة',
            'description' => 'داخلي',
            'image' => '/suppliers/portfolio/cards-1.svg',
            'category' => 'طباعة',
        ])->assertCreated()
            ->assertJsonPath('data.visibility', 'INTERNAL')
            ->json('data');

        $this->asUser($owner)->putJson('/api/admin/suppliers/'.$supplier->id.'/portfolio/'.$portfolio['id'], [
            'title' => 'أعمال محدّثة',
            'description' => 'داخلي',
            'image' => '/suppliers/portfolio/cards-1.svg',
            'category' => 'طباعة',
        ])->assertOk()
            ->assertJsonPath('data.title', 'أعمال محدّثة');

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/documents', [
            'title' => 'عقد توريد',
            'category' => 'contracts',
            'path' => '/supplier-docs/contract.pdf',
            'original_name' => 'contract.pdf',
        ])->assertCreated()
            ->assertJsonPath('data.visibility', 'INTERNAL');

        $this->getJson('/api/suppliers/'.$supplier->slug)
            ->assertOk()
            ->assertJsonMissing(['title' => 'أعمال محدّثة'])
            ->assertJsonMissingPath('data.internal_notes');

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/suspend', [
            'notes' => 'إيقاف مؤقت',
        ])->assertOk()
            ->assertJsonPath('data.status', SupplierStatus::Suspended->value)
            ->assertJsonPath('data.is_active', false);
    }
}
