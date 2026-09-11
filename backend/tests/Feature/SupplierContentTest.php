<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierContentTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{owner: User, supplierUser: User, supplier: Supplier}
     */
    private function linkedSupplier(): array
    {
        $owner = User::factory()->owner()->create();
        $supplierUser = User::factory()->supplier()->create();
        $supplier = Supplier::factory()->unpublished()->create([
            'user_id' => $supplierUser->id,
            'name' => 'مطبعة الاختبار',
            'slug' => 'test-print',
            'short_description' => 'وصف قصير',
        ]);

        return compact('owner', 'supplierUser', 'supplier');
    }

    public function test_supplier_can_create_and_submit_profile_portfolio_and_product_but_cannot_publish(): void
    {
        ['owner' => $owner, 'supplierUser' => $user, 'supplier' => $supplier] = $this->linkedSupplier();

        $this->asUser($user)
            ->putJson('/api/supplier/profile', [
                'name' => 'مطبعة الاختبار',
                'short_description' => 'تحديث الوصف',
                'location' => 'جدة',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_published', false);

        $this->asUser($user)->postJson('/api/supplier/profile/submit')->assertOk()
            ->assertJsonPath('data.profile_status', ContentStatus::Submitted->value);

        $this->asUser($user)
            ->postJson('/api/admin/suppliers/'.$supplier->id.'/publish')
            ->assertForbidden();

        $portfolio = $this->asUser($user)
            ->postJson('/api/supplier/content/portfolio', [
                'title' => 'علب هدايا',
                'description' => 'عمل تجريبي',
                'image' => '/suppliers/portfolio/boxes-1.svg',
                'category' => 'علب',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ContentStatus::Draft->value)
            ->json('data');

        $this->asUser($user)->postJson('/api/supplier/content/portfolio/'.$portfolio['id'].'/submit')->assertOk()
            ->assertJsonPath('data.status', ContentStatus::Submitted->value);

        $product = $this->asUser($user)
            ->postJson('/api/supplier/content/products', [
                'name' => 'علبة فاخرة',
                'short_description' => 'للتواصل للسعر',
                'category' => 'علب',
                'contact_for_price' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ContentStatus::Draft->value)
            ->json('data');

        $this->asUser($user)->postJson('/api/supplier/content/products/'.$product['id'].'/submit')->assertOk();

        $this->getJson('/api/suppliers/test-print')->assertNotFound();
        $this->getJson('/api/suppliers')->assertJsonMissing(['slug' => 'test-print']);
    }

    public function test_owner_approve_publish_makes_supplier_content_public(): void
    {
        ['owner' => $owner, 'supplierUser' => $user, 'supplier' => $supplier] = $this->linkedSupplier();

        $this->asUser($user)->putJson('/api/supplier/profile', [
            'name' => 'مطبعة الاختبار',
            'short_description' => 'وصف معتمد',
            'location' => 'جدة',
        ])->assertOk();
        $this->asUser($user)->postJson('/api/supplier/profile/submit')->assertOk();

        $this->asUser($owner)
            ->postJson('/api/admin/supplier-reviews/profile/'.$supplier->id.'/approve-publish')
            ->assertOk();

        $this->getJson('/api/suppliers/test-print')
            ->assertOk()
            ->assertJsonPath('data.slug', 'test-print')
            ->assertJsonPath('data.short_description', 'وصف معتمد')
            ->assertJsonMissingPath('data.review_notes')
            ->assertJsonMissingPath('data.profile_status');

        $item = $this->asUser($user)->postJson('/api/supplier/content/portfolio', [
            'title' => 'بوكس فاخر',
            'description' => 'وصف العمل',
            'image' => '/suppliers/portfolio/boxes-1.svg',
            'category' => 'علب',
        ])->json('data');
        $this->asUser($user)->postJson('/api/supplier/content/portfolio/'.$item['id'].'/submit')->assertOk();
        $this->asUser($owner)->postJson('/api/admin/supplier-reviews/portfolio/'.$item['id'].'/approve-publish')->assertOk();

        $product = $this->asUser($user)->postJson('/api/supplier/content/products', [
            'name' => 'منتج ظاهر',
            'slug' => 'visible-box',
            'short_description' => 'منتج',
            'contact_for_price' => true,
        ])->json('data');
        $this->asUser($user)->postJson('/api/supplier/content/products/'.$product['id'].'/submit')->assertOk();
        $this->asUser($owner)->postJson('/api/admin/supplier-reviews/product/'.$product['id'].'/approve-publish')->assertOk();

        $this->getJson('/api/suppliers/test-print')
            ->assertOk()
            ->assertJsonPath('data.portfolio.0.title', 'بوكس فاخر');

        $this->getJson('/api/suppliers/test-print/products/visible-box')
            ->assertOk()
            ->assertJsonPath('data.slug', 'visible-box')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.review_notes');
    }

    public function test_published_profile_edits_stay_pending_until_owner_approves(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->supplier()->create();
        $supplier = Supplier::factory()->create([
            'user_id' => $user->id,
            'name' => 'الوصف القديم',
            'slug' => 'stable-print',
            'short_description' => 'الوصف العام الحالي',
            'is_published' => true,
            'profile_status' => ContentStatus::Published,
        ]);

        $this->asUser($user)
            ->putJson('/api/supplier/profile', [
                'name' => 'الوصف القديم',
                'short_description' => 'وصف جديد قيد المراجعة',
                'location' => $supplier->location,
            ])
            ->assertOk();

        $this->getJson('/api/suppliers/stable-print')
            ->assertOk()
            ->assertJsonPath('data.short_description', 'الوصف العام الحالي');

        $this->asUser($user)->postJson('/api/supplier/profile/submit')->assertOk();
        $this->asUser($owner)->postJson('/api/admin/supplier-reviews/profile_version/'.$supplier->fresh()->pendingProfileVersion->id.'/approve-publish')->assertOk();

        $this->getJson('/api/suppliers/stable-print')
            ->assertOk()
            ->assertJsonPath('data.short_description', 'وصف جديد قيد المراجعة');
    }

    public function test_supplier_cannot_modify_another_supplier_or_owner_apis(): void
    {
        $first = $this->linkedSupplier();
        $otherUser = User::factory()->supplier()->create();
        Supplier::factory()->unpublished()->create(['user_id' => $otherUser->id, 'slug' => 'other-print']);

        $item = SupplierPortfolioItem::factory()->create([
            'supplier_id' => $first['supplier']->id,
            'status' => ContentStatus::Draft,
            'is_active' => false,
        ]);

        $this->asUser($otherUser)
            ->putJson('/api/supplier/content/portfolio/'.$item->id, [
                'title' => 'سرقة',
                'description' => 'لا',
                'image' => '/suppliers/portfolio/boxes-1.svg',
                'category' => 'علب',
            ])
            ->assertForbidden();

        $this->asUser($first['supplierUser'])->getJson('/api/admin/suppliers')->assertForbidden();
        $this->asUser($first['supplierUser'])->getJson('/api/admin/dashboard')->assertForbidden();
        $this->asUser($first['supplierUser'])->getJson('/api/employee/work')->assertForbidden();
    }

    public function test_employee_cannot_manage_suppliers(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        $supplier = Supplier::factory()->create();

        $this->asUser($employee)->getJson('/api/admin/suppliers')->assertForbidden();
        $this->asUser($employee)->getJson('/api/supplier/profile')->assertForbidden();
        $this->asUser($employee)->postJson('/api/admin/suppliers/'.$supplier->id.'/publish')->assertForbidden();
    }

    public function test_customer_cannot_access_supplier_management(): void
    {
        $customer = User::factory()->create();
        $this->asUser($customer)->getJson('/api/admin/suppliers')->assertForbidden();
        $this->asUser($customer)->getJson('/api/supplier/content')->assertForbidden();
        $this->asUser($customer)->getJson('/api/admin/supplier-reviews')->assertForbidden();
    }

    public function test_owner_can_reject_and_request_changes(): void
    {
        ['owner' => $owner, 'supplierUser' => $user, 'supplier' => $supplier] = $this->linkedSupplier();
        $this->asUser($user)->postJson('/api/supplier/profile/submit')->assertOk();

        $this->asUser($owner)
            ->postJson('/api/admin/supplier-reviews/profile/'.$supplier->id.'/request-changes', ['notes' => 'أكمل التخصصات'])
            ->assertOk();

        $this->assertSame(ContentStatus::ChangesRequested, $supplier->fresh()->profile_status);

        $this->asUser($user)->postJson('/api/supplier/profile/resubmit')->assertOk();
        $this->asUser($owner)
            ->postJson('/api/admin/supplier-reviews/profile/'.$supplier->id.'/reject', ['notes' => 'مرفوض'])
            ->assertOk();

        $this->assertSame(ContentStatus::Rejected, $supplier->fresh()->profile_status);
        $this->getJson('/api/suppliers/test-print')->assertNotFound();
    }

    public function test_sitemap_includes_published_supplier_pages_only(): void
    {
        Supplier::factory()->create(['slug' => 'live-partner', 'is_published' => true, 'profile_status' => ContentStatus::Published]);
        Supplier::factory()->unpublished()->create(['slug' => 'hidden-partner']);
        $live = Supplier::query()->where('slug', 'live-partner')->first();
        SupplierProduct::factory()->published()->create([
            'supplier_id' => $live->id,
            'slug' => 'live-product',
        ]);
        SupplierProduct::factory()->create([
            'supplier_id' => $live->id,
            'slug' => 'draft-product',
            'status' => ContentStatus::Draft,
        ]);

        $this->get('/api/sitemap.xml')
            ->assertOk()
            ->assertSee('/suppliers/live-partner', false)
            ->assertSee('/suppliers/live-partner/products/live-product', false)
            ->assertDontSee('hidden-partner', false)
            ->assertDontSee('draft-product', false);
    }
}
