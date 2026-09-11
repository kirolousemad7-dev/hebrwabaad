<?php

namespace Tests\Feature;

use App\Enums\PortfolioCategory;
use App\Models\PortfolioItem;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSiteTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_public_seo_returns_defaults_for_known_pages(): void
    {
        $this->getJson('/api/seo/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.page_key', 'home')
            ->assertJsonPath('data.robots', 'index,follow');
    }

    public function test_public_seo_rejects_unknown_pages(): void
    {
        $this->getJson('/api/seo/owner')->assertNotFound();
    }

    public function test_owner_can_update_seo_and_public_page_reflects_it(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->putJson('/api/admin/seo/home', [
                'title' => 'عنوان تجريبي لحبر',
                'description' => 'وصف تجريبي لا يتجاوز الحدود.',
                'robots' => 'noindex,follow',
                'og_image' => '/brand/logo.png',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'عنوان تجريبي لحبر')
            ->assertJsonPath('data.robots', 'noindex,follow');

        $this->getJson('/api/seo/home')
            ->assertOk()
            ->assertJsonPath('data.title', 'عنوان تجريبي لحبر')
            ->assertJsonPath('data.robots', 'noindex,follow');
    }

    public function test_guest_cannot_manage_seo(): void
    {
        $this->getJson('/api/admin/seo')->assertUnauthorized();
    }

    public function test_customer_cannot_manage_seo(): void
    {
        $customer = User::factory()->create();

        $this->asUser($customer)
            ->getJson('/api/admin/seo')
            ->assertForbidden();

        $this->asUser($customer)
            ->putJson('/api/admin/seo/home', ['title' => 'لا'])
            ->assertForbidden();
    }

    public function test_seo_rejects_javascript_urls(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->putJson('/api/admin/seo/home', [
                'canonical_url' => 'javascript:alert(1)',
            ])
            ->assertStatus(422);
    }

    public function test_public_portfolio_hides_unpublished_items(): void
    {
        PortfolioItem::factory()->create([
            'title' => 'منشور',
            'is_published' => true,
            'category' => PortfolioCategory::Branding,
        ]);
        PortfolioItem::factory()->unpublished()->create(['title' => 'مخفي']);

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'منشور')
            ->assertJsonMissingPath('data.0.is_published');
    }

    public function test_public_testimonials_are_empty_until_published(): void
    {
        Testimonial::factory()->create();

        $this->getJson('/api/testimonials')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Testimonial::factory()->published()->create(['quote' => 'تجربة حقيقية']);

        $this->getJson('/api/testimonials')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quote', 'تجربة حقيقية');
    }

    public function test_contact_form_stores_inquiry_without_exposing_it_publicly(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'زائر',
            'email' => 'guest@example.com',
            'message' => 'أرغب في بدء مشروع جديد معكم.',
        ])->assertCreated();

        $this->assertDatabaseCount('contact_inquiries', 1);

        $customer = User::factory()->create();
        $this->asUser($customer)
            ->getJson('/api/admin/contact-inquiries')
            ->assertForbidden();

        $owner = User::factory()->owner()->create();
        $this->asUser($owner)
            ->getJson('/api/admin/contact-inquiries')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'guest@example.com');
    }

    public function test_owner_can_add_a_portfolio_item(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->postJson('/api/admin/portfolio', [
                'title' => 'عمل حقيقي لاحق',
                'category' => 'web',
                'description' => 'سيُضاف من المالك.',
                'image_url' => '/brand/logo.png',
                'is_sample' => false,
                'is_published' => true,
            ])
            ->assertCreated();

        $this->assertSame(1, PortfolioItem::query()->where('is_sample', false)->count());

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonFragment(['title' => 'عمل حقيقي لاحق']);
    }

    public function test_owner_can_unpublish_and_delete_portfolio_items_from_public_api(): void
    {
        $owner = User::factory()->owner()->create();

        $create = $this->asUser($owner)
            ->postJson('/api/admin/portfolio', [
                'title' => 'عمل يظهر ثم يختفي',
                'category' => 'branding',
                'description' => 'اختبار التدفق العام.',
                'image_url' => '/brand/logo.png',
                'is_sample' => false,
                'is_published' => true,
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->json('data');

        $id = (int) $create['id'];

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonFragment(['title' => 'عمل يظهر ثم يختفي']);

        $this->asUser($owner)
            ->putJson('/api/admin/portfolio/'.$id, [
                'title' => 'عمل محدّث',
                'category' => 'branding',
                'description' => 'وصف محدّث',
                'image_url' => '/brand/logo.png',
                'is_sample' => false,
                'is_published' => true,
                'sort_order' => 1,
            ])
            ->assertOk();

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonFragment(['title' => 'عمل محدّث'])
            ->assertJsonMissing(['title' => 'عمل يظهر ثم يختفي']);

        $this->asUser($owner)
            ->putJson('/api/admin/portfolio/'.$id, [
                'title' => 'عمل محدّث',
                'category' => 'branding',
                'description' => 'وصف محدّث',
                'image_url' => '/brand/logo.png',
                'is_sample' => false,
                'is_published' => false,
                'sort_order' => 1,
            ])
            ->assertOk();

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonMissing(['title' => 'عمل محدّث']);

        $this->asUser($owner)
            ->deleteJson('/api/admin/portfolio/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
        $this->getJson('/api/portfolio')->assertOk()->assertJsonMissing(['title' => 'عمل محدّث']);
    }
}
