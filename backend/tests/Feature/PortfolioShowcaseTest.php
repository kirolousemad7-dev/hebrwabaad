<?php

namespace Tests\Feature;

use App\Enums\CatalogPricingMode;
use App\Enums\PortfolioCategory;
use App\Enums\PortfolioMediaType;
use App\Models\Package;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\Sector;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_public_listing_returns_lightweight_published_items(): void
    {
        $published = PortfolioItem::factory()->create([
            'title' => 'هوية مطعم',
            'category' => PortfolioCategory::Branding,
            'is_published' => true,
        ]);
        PortfolioItem::factory()->unpublished()->create(['title' => 'مخفي']);

        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonFragment(['title' => 'هوية مطعم', 'slug' => $published->slug])
            ->assertJsonMissing(['title' => 'مخفي']);
    }

    public function test_public_detail_and_private_hidden(): void
    {
        $item = PortfolioItem::factory()->create([
            'title' => 'حملة فيديو',
            'category' => PortfolioCategory::Video,
            'challenge' => 'تحدي واضح',
            'solution' => 'حل واضح',
            'execution' => 'تنفيذ واضح',
            'deliverables' => ['ريلز', 'هوية'],
            'results' => null,
            'is_published' => true,
        ]);

        $this->getJson('/api/portfolio/'.$item->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $item->slug)
            ->assertJsonPath('data.challenge', 'تحدي واضح')
            ->assertJsonPath('data.results', null);

        $hidden = PortfolioItem::factory()->unpublished()->create([
            'title' => 'غير منشور',
            'slug' => 'private-case',
        ]);

        $this->getJson('/api/portfolio/'.$hidden->slug)->assertNotFound();
    }

    public function test_image_gallery_and_uploaded_video_media(): void
    {
        $item = PortfolioItem::factory()->create(['is_published' => true]);
        PortfolioMedia::factory()->create([
            'portfolio_item_id' => $item->id,
            'type' => PortfolioMediaType::Image,
            'url' => '/brand/logo.png',
            'display_order' => 1,
            'is_public' => true,
        ]);
        PortfolioMedia::factory()->create([
            'portfolio_item_id' => $item->id,
            'type' => PortfolioMediaType::UploadedVideo,
            'url' => 'https://cdn.example.com/work.mp4',
            'thumbnail_url' => '/brand/logo.png',
            'display_order' => 0,
            'is_featured' => true,
            'is_public' => true,
        ]);

        $payload = $this->getJson('/api/portfolio/'.$item->slug)->assertOk()->json('data');
        $this->assertCount(2, $payload['media']);
        $this->assertSame('UPLOADED_VIDEO', $payload['media'][0]['type']);
        $this->assertSame('IMAGE', $payload['media'][1]['type']);
    }

    public function test_external_video_allowed_and_unsafe_embed_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $item = PortfolioItem::factory()->create(['is_published' => true]);

        $this->asUser($owner)
            ->putJson('/api/admin/portfolio/'.$item->id, [
                'title' => $item->title,
                'category' => $item->category->value,
                'image_url' => $item->image_url,
                'media' => [[
                    'type' => PortfolioMediaType::ExternalVideo->value,
                    'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
                    'is_public' => true,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.media.0.embed.provider', 'youtube');

        $this->asUser($owner)
            ->putJson('/api/admin/portfolio/'.$item->id, [
                'title' => $item->title,
                'category' => $item->category->value,
                'image_url' => $item->image_url,
                'media' => [[
                    'type' => PortfolioMediaType::ExternalVideo->value,
                    'url' => 'https://evil.example/embed/hack',
                    'is_public' => true,
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_website_link_sanitized_and_javascript_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $item = PortfolioItem::factory()->create(['is_published' => true]);

        $this->asUser($owner)
            ->putJson('/api/admin/portfolio/'.$item->id, [
                'title' => $item->title,
                'category' => $item->category->value,
                'image_url' => $item->image_url,
                'project_url' => 'javascript:alert(1)',
            ])
            ->assertOk()
            ->assertJsonPath('data.project_url', null);

        $this->asUser($owner)
            ->putJson('/api/admin/portfolio/'.$item->id, [
                'title' => $item->title,
                'category' => $item->category->value,
                'image_url' => $item->image_url,
                'project_url' => 'https://example.com/store',
            ])
            ->assertOk()
            ->assertJsonPath('data.project_url', 'https://example.com/store');
    }

    public function test_linked_services_package_cta_and_related_work(): void
    {
        $sector = Sector::factory()->create(['slug' => 'restaurants']);
        $service = Service::factory()->create([
            'slug' => 'branding',
            'pricing_mode' => CatalogPricingMode::Quote,
            'is_public' => true,
            'is_active' => true,
        ]);
        $package = Package::factory()->create([
            'slug' => 'restaurant-launch',
            'is_public' => true,
            'is_active' => true,
        ]);

        $item = PortfolioItem::factory()->create([
            'category' => PortfolioCategory::Branding,
            'package_id' => $package->id,
            'is_published' => true,
        ]);
        $item->sectors()->attach($sector->id);
        $item->services()->attach($service->id);

        $related = PortfolioItem::factory()->create([
            'category' => PortfolioCategory::Branding,
            'is_published' => true,
        ]);
        $related->sectors()->attach($sector->id);

        $payload = $this->getJson('/api/portfolio/'.$item->slug)->assertOk()->json('data');
        $this->assertSame('branding', $payload['services'][0]['slug']);
        $this->assertSame('restaurant-launch', $payload['package']['slug']);
        $this->assertNotEmpty($payload['cta']['similar_path']);
        $this->assertTrue($payload['cta']['show_quote_cta']);
        $this->assertStringContainsString('source_type=PORTFOLIO', (string) $payload['cta']['quote_path']);
        $this->assertTrue(collect($payload['related'])->contains(fn ($row) => ($row['slug'] ?? null) === $related->slug));
    }

    public function test_private_media_not_exposed_publicly(): void
    {
        $item = PortfolioItem::factory()->create(['is_published' => true]);
        PortfolioMedia::factory()->create([
            'portfolio_item_id' => $item->id,
            'type' => PortfolioMediaType::Image,
            'url' => '/brand/public.png',
            'is_public' => true,
        ]);
        PortfolioMedia::factory()->private()->create([
            'portfolio_item_id' => $item->id,
            'type' => PortfolioMediaType::Image,
            'url' => '/brand/secret.png',
            'title' => 'سري',
        ]);

        $payload = $this->getJson('/api/portfolio/'.$item->slug)->assertOk()->json('data');
        $this->assertCount(1, $payload['media']);
        $this->assertStringNotContainsString('secret.png', json_encode($payload));
    }
}
