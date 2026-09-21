<?php

namespace Tests\Feature;

use App\Enums\CmsPageType;
use App\Enums\UserRole;
use App\Models\CmsPage;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageTest extends TestCase
{
    use RefreshDatabase;

    private function asOwner(): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(
            User::factory()->owner()->create()->createToken('auth')->plainTextToken
        );
    }

    private function asCustomer(): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(
            User::factory()->create(['role' => UserRole::Customer])->createToken('auth')->plainTextToken
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pagePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'صفحة تجريبية',
            'slug' => 'test-cms-page',
            'content' => '<h1>عنوان</h1><p>محتوى الصفحة التجريبية.</p>',
            'page_type' => CmsPageType::General->value,
            'meta_title' => 'صفحة تجريبية | حبر وأبعاد',
            'meta_description' => 'وصف تجريبي لصفحة CMS',
            'is_published' => true,
            'show_in_footer' => true,
            'footer_group' => 'السياسات',
            'footer_order' => 1,
        ], $overrides);
    }

    public function test_owner_can_list_create_update_publish_unpublish_and_manage_footer(): void
    {
        $created = $this->asOwner()
            ->postJson('/api/owner/pages', $this->pagePayload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'test-cms-page')
            ->assertJsonPath('data.is_published', true)
            ->json('data');

        $this->asOwner()
            ->getJson('/api/owner/pages')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);

        $this->asOwner()
            ->putJson('/api/owner/pages/'.$created['id'], [
                'title' => 'صفحة محدّثة',
                'content' => '<h1>عنوان محدّث</h1><p>نص جديد.</p>',
                'meta_title' => 'عنوان SEO محدّث',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'صفحة محدّثة')
            ->assertJsonPath('data.meta_title', 'عنوان SEO محدّث');

        $this->asOwner()
            ->patchJson('/api/owner/pages/'.$created['id'].'/publish', [
                'is_published' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_published', false);

        $this->asOwner()
            ->patchJson('/api/owner/pages/'.$created['id'].'/publish', [
                'is_published' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->asOwner()
            ->patchJson('/api/owner/pages/'.$created['id'].'/footer', [
                'show_in_footer' => true,
                'footer_group' => 'تعرف علينا',
                'footer_order' => 9,
            ])
            ->assertOk()
            ->assertJsonPath('data.show_in_footer', true)
            ->assertJsonPath('data.footer_group', 'تعرف علينا')
            ->assertJsonPath('data.footer_order', 9);
    }

    public function test_customer_cannot_manage_pages(): void
    {
        $page = CmsPage::factory()->create();

        $this->asCustomer()
            ->getJson('/api/owner/pages')
            ->assertForbidden();

        $this->asCustomer()
            ->postJson('/api/owner/pages', $this->pagePayload([
                'slug' => 'customer-blocked',
            ]))
            ->assertForbidden();

        $this->asCustomer()
            ->putJson('/api/owner/pages/'.$page->id, [
                'title' => 'محاولة تعديل',
            ])
            ->assertForbidden();

        $this->asCustomer()
            ->patchJson('/api/owner/pages/'.$page->id.'/publish', [
                'is_published' => false,
            ])
            ->assertForbidden();

        $this->asCustomer()
            ->patchJson('/api/owner/pages/'.$page->id.'/footer', [
                'show_in_footer' => false,
            ])
            ->assertForbidden();

        $this->asCustomer()
            ->deleteJson('/api/owner/pages/'.$page->id)
            ->assertForbidden();
    }

    public function test_public_can_read_published_page(): void
    {
        $page = CmsPage::factory()->create([
            'slug' => 'public-about',
            'title' => 'من نحن عام',
            'is_published' => true,
            'content' => '<h1>من نحن</h1><p>محتوى منشور.</p>',
        ]);

        $this->getJson('/api/public/pages/'.$page->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', 'public-about')
            ->assertJsonPath('data.title', 'من نحن عام')
            ->assertJsonMissingPath('data.is_published')
            ->assertJsonMissingPath('data.is_system')
            ->assertJsonMissingPath('data.show_in_footer')
            ->assertJsonMissingPath('data.created_at');
    }

    public function test_public_cannot_read_draft_page(): void
    {
        $page = CmsPage::factory()->draft()->create([
            'slug' => 'draft-only',
        ]);

        $this->getJson('/api/public/pages/'.$page->slug)
            ->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/public/pages/does-not-exist')
            ->assertNotFound();
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        CmsPage::factory()->create(['slug' => 'taken-slug']);

        $this->asOwner()
            ->postJson('/api/owner/pages', $this->pagePayload([
                'slug' => 'taken-slug',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_footer_list_only_published_visible_and_ordered(): void
    {
        CmsPage::factory()->footer('تعرف علينا', 2)->create([
            'slug' => 'about-footer',
            'title' => 'من نحن',
        ]);
        CmsPage::factory()->footer('تعرف علينا', 1)->create([
            'slug' => 'contact-footer',
            'title' => 'تواصل معنا',
        ]);
        CmsPage::factory()->footer('تعرف علينا', 3)->create([
            'slug' => 'warranty-footer',
            'title' => 'الضمان',
        ]);
        CmsPage::factory()->create([
            'slug' => 'hidden-footer',
            'is_published' => true,
            'show_in_footer' => false,
            'footer_group' => 'تعرف علينا',
            'footer_order' => 0,
        ]);
        CmsPage::factory()->draft()->create([
            'slug' => 'draft-footer',
            'show_in_footer' => true,
            'footer_group' => 'تعرف علينا',
            'footer_order' => 0,
        ]);

        $response = $this->getJson('/api/public/pages?footer=1')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertSame(['contact-footer', 'about-footer', 'warranty-footer'], $slugs);
        $this->assertNotContains('hidden-footer', $slugs);
        $this->assertNotContains('draft-footer', $slugs);
    }

    public function test_unsafe_html_is_sanitized_on_store(): void
    {
        $created = $this->asOwner()
            ->postJson('/api/owner/pages', $this->pagePayload([
                'slug' => 'sanitized-page',
                'content' => '<h1>آمن</h1><p>نص</p><script>alert(1)</script><p onclick="evil()">بعد</p>',
            ]))
            ->assertCreated()
            ->json('data');

        $this->assertStringNotContainsString('<script', $created['content']);
        $this->assertStringNotContainsString('onclick', $created['content']);
        $this->assertStringContainsString('<h1>آمن</h1>', $created['content']);
        $this->assertStringContainsString('بعد', $created['content']);
    }

    public function test_cms_page_seeder_is_idempotent(): void
    {
        $this->seed(CmsPageSeeder::class);
        $this->assertSame(8, CmsPage::query()->count());

        $this->seed(CmsPageSeeder::class);
        $this->assertSame(8, CmsPage::query()->count());

        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'about',
            'is_published' => true,
            'show_in_footer' => true,
            'footer_group' => 'تعرف علينا',
            'footer_order' => 1,
        ]);
        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'services-and-products-policies',
            'footer_group' => 'السياسات',
            'footer_order' => 5,
        ]);
    }

    public function test_seeder_does_not_overwrite_owner_edits(): void
    {
        $this->seed(CmsPageSeeder::class);

        $about = CmsPage::query()->where('slug', 'about')->firstOrFail();
        $about->update([
            'title' => 'من نحن — تعديل المالك',
            'content' => '<p>محتوى مخصص من المالك.</p>',
            'meta_title' => 'عنوان SEO مخصص',
        ]);

        $this->seed(CmsPageSeeder::class);

        $about->refresh();
        $this->assertSame('من نحن — تعديل المالك', $about->title);
        $this->assertStringContainsString('محتوى مخصص من المالك', $about->content);
        $this->assertSame('عنوان SEO مخصص', $about->meta_title);
        $this->assertSame(8, CmsPage::query()->count());
    }

    public function test_system_pages_cannot_be_deleted(): void
    {
        $this->seed(CmsPageSeeder::class);
        $about = CmsPage::query()->where('slug', 'about')->firstOrFail();

        $this->asOwner()
            ->deleteJson('/api/owner/pages/'.$about->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('cms_pages', ['slug' => 'about']);
    }

    public function test_custom_pages_can_be_deleted(): void
    {
        $page = CmsPage::factory()->create(['slug' => 'custom-deletable']);

        $this->asOwner()
            ->deleteJson('/api/owner/pages/'.$page->id)
            ->assertOk();

        $this->assertDatabaseMissing('cms_pages', ['id' => $page->id]);
    }

    public function test_sitemap_includes_published_cms_pages_and_excludes_drafts(): void
    {
        CmsPage::factory()->create([
            'slug' => 'published-policy',
            'is_published' => true,
        ]);
        CmsPage::factory()->draft()->create([
            'slug' => 'draft-policy',
        ]);
        CmsPage::factory()->draft()->create([
            'slug' => 'about',
        ]);

        $sitemap = $this->get('/api/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/published-policy', $sitemap);
        $this->assertStringNotContainsString('/draft-policy', $sitemap);
        $this->assertStringNotContainsString('/about', $sitemap);
    }

    public function test_javascript_urls_are_stripped_from_anchors(): void
    {
        $created = $this->asOwner()
            ->postJson('/api/owner/pages', $this->pagePayload([
                'slug' => 'js-link-page',
                'content' => '<p><a href="javascript:alert(1)">خطر</a><a href="https://hebrwabaad.com">آمن</a></p>',
            ]))
            ->assertCreated()
            ->json('data');

        $this->assertStringNotContainsString('javascript:', $created['content']);
        $this->assertStringContainsString('https://hebrwabaad.com', $created['content']);
    }
}
