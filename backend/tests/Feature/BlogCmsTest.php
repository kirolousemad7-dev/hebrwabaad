<?php

namespace Tests\Feature;

use App\Enums\BlogPostStatus;
use App\Enums\UserRole;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogCmsTest extends TestCase
{
    use RefreshDatabase;

    private function asOwner(): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(
            User::factory()->owner()->create()->createToken('auth')->plainTextToken
        );
    }

    public function test_owner_can_manage_blog_taxonomy_and_publish_post(): void
    {
        $author = $this->asOwner()
            ->postJson('/api/admin/blog/authors', [
                'name' => 'فريق المحتوى',
                'bio' => 'كاتب المنصة',
            ])
            ->assertCreated()
            ->json('data');

        $category = $this->asOwner()
            ->postJson('/api/admin/blog/categories', [
                'name' => 'تسويق',
                'description' => 'مقالات تسويقية',
            ])
            ->assertCreated()
            ->json('data');

        $tag = $this->asOwner()
            ->postJson('/api/admin/blog/tags', ['name' => 'هوية'])
            ->assertCreated()
            ->json('data');

        $post = $this->asOwner()
            ->postJson('/api/admin/blog/posts', [
                'title' => 'كيف تبني هوية قوية',
                'excerpt' => 'دليل مختصر للهوية البصرية',
                'content' => "مقدمة\n\nتفاصيل المقال حول الهوية.",
                'author_id' => $author['id'],
                'category_id' => $category['id'],
                'tag_ids' => [$tag['id']],
                'status' => BlogPostStatus::Published->value,
                'seo_title' => 'هوية قوية | حبر وأبعاد',
                'meta_description' => 'دليل بناء الهوية البصرية',
                'featured_image' => 'https://cdn.example.test/blog/cover.jpg',
                'og_image' => 'https://cdn.example.test/blog/og.jpg',
                'canonical_url' => 'https://hebr.example/blog/kayfa-tabni-hawiya',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', BlogPostStatus::Published->value)
            ->assertJsonPath('data.category.name', 'تسويق')
            ->json('data');

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post['id'],
            'title' => 'كيف تبني هوية قوية',
            'status' => BlogPostStatus::Published->value,
        ]);

        $this->getJson('/api/blog')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.slug', $post['slug']);

        $this->getJson('/api/blog/'.$post['slug'])
            ->assertOk()
            ->assertJsonPath('data.title', 'كيف تبني هوية قوية')
            ->assertJsonPath('data.seo.title', 'هوية قوية | حبر وأبعاد')
            ->assertJsonStructure(['data' => ['content', 'related', 'tags']]);

        $this->getJson('/api/blog/category/'.$category['slug'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'تسويق')
            ->assertJsonPath('data.meta.total', 1);

        $sitemap = $this->get('/api/sitemap.xml')->assertOk();
        $this->assertStringContainsString('/blog/'.$post['slug'], $sitemap->getContent());
    }

    public function test_draft_and_future_scheduled_posts_are_hidden_from_public(): void
    {
        BlogPost::factory()->create([
            'status' => BlogPostStatus::Draft,
            'title' => 'مسودة',
        ]);
        BlogPost::factory()->scheduled()->create([
            'title' => 'مجدول لاحقاً',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'منشور الآن',
        ]);

        $this->getJson('/api/blog')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.title', 'منشور الآن');
    }

    public function test_customer_cannot_access_admin_blog(): void
    {
        $token = User::factory()->create(['role' => UserRole::Customer->value])->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/blog/posts')
            ->assertForbidden();
    }

    public function test_public_search_filters_posts(): void
    {
        BlogPost::factory()->published()->create([
            'title' => 'طباعة فاخرة',
            'excerpt' => 'تغليف وبوكسات',
        ]);
        BlogPost::factory()->published()->create([
            'title' => 'تسويق رقمي',
            'excerpt' => 'حملات سوشيال',
        ]);

        $this->getJson('/api/blog?q=طباعة')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.title', 'طباعة فاخرة');
    }

    public function test_updating_post_and_archiving_removes_from_public(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'مقال قابل للأرشفة',
        ]);

        $this->asOwner()
            ->putJson('/api/admin/blog/posts/'.$post->id, [
                'title' => 'مقال مؤرشف',
                'content' => $post->content,
                'status' => BlogPostStatus::Archived->value,
                'author_id' => $post->author_id,
                'category_id' => $post->category_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BlogPostStatus::Archived->value);

        $this->getJson('/api/blog/'.$post->slug)->assertNotFound();
    }
}
