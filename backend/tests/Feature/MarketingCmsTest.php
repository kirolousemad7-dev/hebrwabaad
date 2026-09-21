<?php

namespace Tests\Feature;

use App\Enums\MediaVisibility;
use App\Enums\UserRole;
use App\Models\MarketingContent;
use App\Models\MarketingSection;
use App\Models\Media;
use App\Models\User;
use App\Services\Marketing\MarketingMediaLibraryService;
use Database\Seeders\MarketingSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingCmsTest extends TestCase
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

    public function test_customer_cannot_access_owner_marketing_endpoints(): void
    {
        MarketingSection::factory()->create(['key' => 'hero']);

        $this->asCustomer()
            ->getJson('/api/owner/marketing/sections')
            ->assertForbidden();

        $this->asCustomer()
            ->getJson('/api/owner/marketing/media')
            ->assertForbidden();
    }

    public function test_owner_can_list_and_update_marketing_section_contents(): void
    {
        $section = MarketingSection::factory()->create([
            'key' => 'why-us',
            'admin_title' => 'Why us',
            'is_enabled' => true,
        ]);

        MarketingContent::factory()->create([
            'marketing_section_id' => $section->id,
            'content_key' => 'title',
            'value_text' => 'عنوان قديم',
            'is_enabled' => true,
        ]);

        $this->asOwner()
            ->getJson('/api/owner/marketing/sections')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'why-us');

        $this->asOwner()
            ->putJson('/api/owner/marketing/sections/why-us', [
                'admin_title' => 'لماذا نحن',
                'contents' => [
                    [
                        'content_key' => 'title',
                        'value_text' => 'عنوان محدّث',
                        'value_html' => '<p>نص <script>alert(1)</script>آمن</p>',
                        'is_enabled' => true,
                    ],
                    [
                        'content_key' => 'eyebrow',
                        'value_text' => 'لماذا نحن',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.admin_title', 'لماذا نحن');

        $title = MarketingContent::query()
            ->where('marketing_section_id', $section->id)
            ->where('content_key', 'title')
            ->first();

        $this->assertNotNull($title);
        $this->assertSame('عنوان محدّث', $title->value_text);
        $this->assertStringNotContainsString('<script>', (string) $title->value_html);
        $this->assertStringContainsString('<p>', (string) $title->value_html);
    }

    public function test_public_api_returns_only_enabled_sections_and_contents(): void
    {
        $enabled = MarketingSection::factory()->create([
            'key' => 'process',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);
        $disabled = MarketingSection::factory()->disabled()->create([
            'key' => 'hidden-section',
            'sort_order' => 2,
        ]);

        MarketingContent::factory()->create([
            'marketing_section_id' => $enabled->id,
            'content_key' => 'title',
            'value_text' => 'مرئي',
            'is_enabled' => true,
        ]);
        MarketingContent::factory()->disabled()->create([
            'marketing_section_id' => $enabled->id,
            'content_key' => 'secret',
            'value_text' => 'مخفي',
        ]);
        MarketingContent::factory()->create([
            'marketing_section_id' => $disabled->id,
            'content_key' => 'title',
            'value_text' => 'لا يظهر',
            'is_enabled' => true,
        ]);

        $response = $this->getJson('/api/public/marketing')
            ->assertOk()
            ->assertJsonPath('success', true);

        $keys = collect($response->json('data.sections'))->pluck('key')->all();
        $this->assertContains('process', $keys);
        $this->assertNotContains('hidden-section', $keys);

        $process = collect($response->json('data.sections'))->firstWhere('key', 'process');
        $contentKeys = collect($process['contents'] ?? [])->pluck('key')->all();
        $this->assertContains('title', $contentKeys);
        $this->assertNotContains('secret', $contentKeys);
        $this->assertArrayNotHasKey('admin_title', $process);
        $this->assertArrayNotHasKey('config', $process);
    }

    public function test_public_api_hides_disabled_section_by_key(): void
    {
        MarketingSection::factory()->disabled()->create(['key' => 'final-cta']);

        $this->getJson('/api/public/marketing/sections/final-cta')
            ->assertNotFound();
    }

    public function test_owner_can_upload_marketing_image_and_reject_invalid_files(): void
    {
        Storage::fake('public');

        $upload = $this->asOwner()
            ->post('/api/owner/marketing/media', [
                'file' => UploadedFile::fake()->image('storefront.jpg', 800, 600),
                'alt_text' => 'واجهة الفرع',
                'title' => 'Storefront',
            ], ['Accept' => 'application/json']);

        $upload->assertCreated()
            ->assertJsonPath('data.alt_text', 'واجهة الفرع')
            ->assertJsonPath('data.title', 'Storefront');

        $mediaId = (int) $upload->json('data.id');
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'collection' => 'marketing_library',
            'visibility' => MediaVisibility::Public->value,
        ]);

        $this->asOwner()
            ->post('/api/owner/marketing/media', [
                'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_replace_keeps_previous_media_and_delete_blocked_while_used(): void
    {
        Storage::fake('public');

        $section = MarketingSection::factory()->create(['key' => 'hero']);
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('auth')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $created = $this->withToken($token)
            ->post('/api/owner/marketing/media', [
                'file' => UploadedFile::fake()->image('old.jpg', 400, 300),
                'alt_text' => 'قديم',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $oldId = (int) $created['id'];

        MarketingContent::factory()->create([
            'marketing_section_id' => $section->id,
            'content_key' => 'visual_image',
            'media_id' => $oldId,
        ]);

        $replaced = $this->withToken($token)
            ->post('/api/owner/marketing/media/'.$oldId.'/replace', [
                'file' => UploadedFile::fake()->image('new.jpg', 400, 300),
                'alt_text' => 'جديد',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $newId = (int) $replaced['new']['id'];
        $this->assertNotSame($oldId, $newId);
        $this->assertDatabaseHas('media', ['id' => $oldId]);
        $this->assertDatabaseHas('media', ['id' => $newId]);

        $this->withToken($token)
            ->deleteJson('/api/owner/marketing/media/'.$oldId)
            ->assertStatus(422);

        $this->withToken($token)
            ->putJson('/api/owner/marketing/sections/hero', [
                'contents' => [
                    [
                        'content_key' => 'visual_image',
                        'media_id' => $newId,
                    ],
                ],
            ])
            ->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/owner/marketing/media/'.$oldId)
            ->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $oldId]);
    }

    public function test_seeder_registers_default_sections_without_breaking_public_payload(): void
    {
        User::factory()->owner()->create();
        $this->seed(MarketingSectionSeeder::class);

        $this->assertDatabaseHas('marketing_sections', ['key' => 'hero']);
        $this->assertDatabaseHas('marketing_sections', ['key' => 'services']);
        $this->assertDatabaseHas('media', ['collection' => 'marketing_library']);

        $this->getJson('/api/public/marketing')
            ->assertOk()
            ->assertJsonPath('success', true);

        $keys = collect($this->getJson('/api/public/marketing')->json('data.sections'))->pluck('key')->all();
        $this->assertContains('hero', $keys);
        $this->assertContains('about', $keys);

        $about = collect($this->getJson('/api/public/marketing')->json('data.sections'))->firstWhere('key', 'about');
        $aboutKeys = collect($about['contents'] ?? [])->pluck('key')->all();
        $this->assertContains('quote', $aboutKeys);
        $this->assertContains('cta_label', $aboutKeys);

        $services = collect($this->getJson('/api/public/marketing')->json('data.sections'))->firstWhere('key', 'services');
        $serviceKeys = collect($services['contents'] ?? [])->pluck('key')->all();
        $this->assertContains('visual_strategy', $serviceKeys);
        $this->assertContains('visual_branding', $serviceKeys);

        $packages = collect($this->getJson('/api/public/marketing')->json('data.sections'))->firstWhere('key', 'packages');
        $packageKeys = collect($packages['contents'] ?? [])->pluck('key')->all();
        $this->assertContains('visual_basic', $packageKeys);
        $this->assertContains('visual_professional', $packageKeys);
    }

    public function test_existing_cms_pages_public_endpoint_still_works(): void
    {
        $this->getJson('/api/public/pages')->assertOk();
    }

    public function test_admin_manager_can_access_owner_marketing_endpoints(): void
    {
        MarketingSection::factory()->create(['key' => 'hero']);

        $this->app['auth']->forgetGuards();
        $token = User::factory()->adminManager()
            ->create()
            ->createToken('auth')
            ->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/owner/marketing/sections')
            ->assertOk();
    }

    public function test_unauthenticated_cannot_access_owner_marketing_endpoints(): void
    {
        $this->getJson('/api/owner/marketing/sections')->assertUnauthorized();
        $this->getJson('/api/owner/marketing/media')->assertUnauthorized();
    }

    public function test_inactive_media_is_hidden_from_public_payload(): void
    {
        Storage::fake('public');
        $section = MarketingSection::factory()->create(['key' => 'about', 'is_enabled' => true]);
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('auth')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $mediaId = (int) $this->withToken($token)
            ->post('/api/owner/marketing/media', [
                'file' => UploadedFile::fake()->image('about.jpg', 400, 300),
                'alt_text' => 'about',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->putJson('/api/owner/marketing/media/'.$mediaId, ['is_active' => false])
            ->assertOk();

        MarketingContent::factory()->create([
            'marketing_section_id' => $section->id,
            'content_key' => 'visual_image',
            'media_id' => $mediaId,
            'is_enabled' => true,
        ]);

        $sectionPayload = $this->getJson('/api/public/marketing/sections/about')
            ->assertOk()
            ->json('data');

        $visual = collect($sectionPayload['contents'] ?? [])->firstWhere('key', 'visual_image');
        $this->assertNotNull($visual);
        $this->assertNull($visual['media']);
    }

    public function test_public_payload_omits_admin_fields(): void
    {
        MarketingSection::factory()->create([
            'key' => 'final-cta',
            'admin_title' => 'Internal CTA title',
            'is_enabled' => true,
            'config' => ['secret' => true],
        ]);

        $payload = $this->getJson('/api/public/marketing/sections/final-cta')
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('admin_title', $payload);
        $this->assertArrayNotHasKey('config', $payload);
        $this->assertArrayNotHasKey('is_enabled', $payload);
        $this->assertArrayNotHasKey('id', $payload);
    }

    public function test_orphans_endpoint_lists_unused_library_media(): void
    {
        Storage::fake('public');
        $owner = User::factory()->owner()->create();
        $token = $owner->createToken('auth')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $mediaId = (int) $this->withToken($token)
            ->post('/api/owner/marketing/media', [
                'file' => UploadedFile::fake()->image('orphan.jpg', 200, 200),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $orphans = $this->withToken($token)
            ->getJson('/api/owner/marketing/media/orphans')
            ->assertOk()
            ->json('data');

        $ids = collect($orphans)->pluck('id')->all();
        $this->assertContains($mediaId, $ids);
    }

    public function test_media_id_must_belong_to_marketing_library(): void
    {
        $section = MarketingSection::factory()->create(['key' => 'hero']);
        $owner = User::factory()->owner()->create();
        $foreign = Media::query()->create([
            'disk' => 'local',
            'path' => 'media/misc/x.jpg',
            'original_name' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 10,
            'visibility' => MediaVisibility::Internal,
            'uploaded_by' => $owner->id,
            'collection' => 'default',
            'sort_order' => 0,
            'is_primary' => false,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($owner->createToken('auth')->plainTextToken)
            ->putJson('/api/owner/marketing/sections/hero', [
                'contents' => [
                    [
                        'content_key' => 'visual_image',
                        'media_id' => $foreign->id,
                    ],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('marketing_contents', [
            'marketing_section_id' => $section->id,
            'media_id' => $foreign->id,
        ]);
    }

    public function test_registry_only_media_cannot_be_deleted_even_when_unused(): void
    {
        $owner = User::factory()->owner()->create();
        $this->app['auth']->forgetGuards();
        $token = $owner->createToken('auth')->plainTextToken;

        /** @var MarketingMediaLibraryService $library */
        $library = app(MarketingMediaLibraryService::class);
        $registry = $library->registerLocalPublicAsset($owner, '/marketing/storefront.jpg', [
            'source_key' => 'storefront',
            'title' => 'Storefront',
            'alt_text' => 'واجهة فرع حبر وأبعاد',
        ]);

        $this->assertSame(0, $library->usageCount($registry));
        $this->assertTrue((bool) (($registry->metadata ?? [])['is_registry_only'] ?? false));

        $repoPublic = dirname(base_path())
            .DIRECTORY_SEPARATOR.'frontend'
            .DIRECTORY_SEPARATOR.'public'
            .DIRECTORY_SEPARATOR.'marketing'
            .DIRECTORY_SEPARATOR.'storefront.jpg';

        $this->assertFileExists($repoPublic);
        $beforeHash = hash_file('sha256', $repoPublic);
        $beforeSize = filesize($repoPublic);

        $this->withToken($token)
            ->deleteJson('/api/owner/marketing/media/'.$registry->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('media', [
            'id' => $registry->id,
            'collection' => 'marketing_library',
        ]);

        $this->assertFileExists($repoPublic);
        $this->assertSame($beforeHash, hash_file('sha256', $repoPublic));
        $this->assertSame($beforeSize, filesize($repoPublic));
    }
}
