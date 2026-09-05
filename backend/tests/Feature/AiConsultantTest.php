<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Enums\PackageCategory;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\Consultant\ConsultantSettings;
use App\Services\Consultant\RecommendationValidator;
use Database\Seeders\PackageSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConsultantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed([ServiceSeeder::class, PackageSeeder::class]);
    }

    public function test_guest_can_start_and_resume_consultation(): void
    {
        $started = $this->postJson('/api/consultations')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ConsultationStatus::InProgress->value)
            ->assertJsonPath('data.prompt.id', 'help_mode')
            ->json('data');

        $this->assertNotEmpty($started['token']);
        $this->assertDatabaseHas('consultation_events', [
            'name' => 'ai_consultation_started',
        ]);

        $this->getJson('/api/consultations/'.$started['token'])
            ->assertOk()
            ->assertJsonPath('data.token', $started['token'])
            ->assertJsonPath('data.prompt.id', 'help_mode')
            ->assertJsonMissingPath('data.employees')
            ->assertJsonMissingPath('data.projects')
            ->assertJsonMissingPath('data.tasks');
    }

    public function test_answers_persist_and_reset_creates_new_session(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');

        $this->postJson('/api/consultations/'.$token.'/answers', [
            'question_id' => 'help_mode',
            'value' => 'guided',
        ])->assertOk()->assertJsonPath('data.state.help_mode', 'guided');

        $this->getJson('/api/consultations/'.$token)
            ->assertOk()
            ->assertJsonPath('data.state.help_mode', 'guided');

        $reset = $this->postJson('/api/consultations/'.$token.'/reset')
            ->assertOk()
            ->json('data');

        $this->assertNotSame($token, $reset['token']);
        $this->assertNull($reset['state']['help_mode']);
        $this->assertDatabaseHas('consultations', [
            'public_token' => $token,
            'status' => ConsultationStatus::Abandoned->value,
        ]);
    }

    public function test_restaurant_flow_recommends_active_marketing_package(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'restaurants-food',
            'business_subtype' => 'chicken-restaurant',
            'goals' => ['increase_sales', 'more_customers'],
            'budget' => '10000_25000',
            'timeline' => '1_3_months',
        ]);

        $this->assertSame(ConsultationStatus::Completed->value, $result['status']);
        $this->assertNotNull($result['diagnosis']);
        $this->assertIsInt($result['readiness']['score']);
        $this->assertSame('digital-marketing-package', $result['recommendations']['best_match']['slug']);
        $this->assertEquals(15000, $result['recommendations']['best_match']['final_price']);
        $this->assertSame('SAR', $result['recommendations']['best_match']['currency']);
        $this->assertSame('choose_package', $result['recommendations']['cta']['type']);
        $this->assertStringContainsString('digital-marketing-package', $result['recommendations']['cta']['path']);
        $this->assertDatabaseHas('consultation_events', ['name' => 'consultation_completed']);
    }

    public function test_ecommerce_flow_recommends_ecommerce_package(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'ecommerce',
            'business_subtype' => 'fashion-ecommerce',
            'goals' => ['build_ecommerce'],
            'budget' => '10000_25000',
            'timeline' => '1_3_months',
        ]);

        $this->assertSame('ecommerce-launch-package', $result['recommendations']['best_match']['slug']);
        $this->assertEquals(22000, $result['recommendations']['best_match']['final_price']);
    }

    public function test_event_flow_uses_event_package_and_real_cta(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'events',
            'business_subtype' => 'wedding',
            'goals' => ['prepare_event'],
            'event_date' => '2026-10-20',
            'budget' => '10000_25000',
            'timeline' => '2_4_weeks',
        ]);

        $this->assertSame('events-package', $result['recommendations']['best_match']['slug']);
        $this->assertSame('plan_event', $result['recommendations']['cta']['type']);
        $this->assertSame('/event-packages', $result['recommendations']['cta']['path']);
    }

    public function test_website_and_unsure_flows_do_not_invent_catalog_items(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $website = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'professional-services',
            'business_subtype' => 'consulting',
            'goals' => ['build_website', 'launch_business'],
            'budget' => '5000_10000',
            'timeline' => '1_3_months',
        ]);

        $this->assertContains($website['recommendations']['best_match']['slug'] ?? null, ['foundation-package', null]);
        if ($website['recommendations']['best_match']) {
            $this->assertTrue(Package::query()->where('slug', $website['recommendations']['best_match']['slug'])->where('is_active', true)->exists());
        }

        $unsureToken = $this->postJson('/api/consultations')->json('data.token');
        $unsure = $this->completeFlow($unsureToken, [
            'help_mode' => 'unsure',
            'business_category' => 'restaurants-food',
            'business_subtype' => 'chicken-restaurant',
            'budget' => '10000_25000',
            'timeline' => 'flexible',
        ]);

        $this->assertTrue($unsure['state']['unsure_needs']);
        $this->assertNotNull($unsure['recommendations']);
        $this->assertDatabaseHas('packages', [
            'slug' => $unsure['recommendations']['best_match']['slug'] ?? 'digital-marketing-package',
            'is_active' => true,
        ]);
    }

    public function test_printing_flow_uses_real_catalog_and_quote_fallback(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'printing-packaging',
            'business_subtype' => 'promotional-materials',
            'goals' => ['print_materials'],
            'needed_services' => ['printing'],
            'printing_product' => 'a5-flyers',
            'budget' => 'under_5000',
            'timeline' => '1_week',
        ]);

        $this->assertNull($result['recommendations']['best_match']);
        $this->assertSame('a5-flyers', $result['recommendations']['printing']['product_slug']);
        $this->assertEquals(120, $result['recommendations']['printing']['starting_price']);
        $this->assertStringContainsString('printing', $result['recommendations']['cta']['path']);
    }

    public function test_budget_mismatch_does_not_recommend_over_budget_package(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'ecommerce',
            'business_subtype' => 'general-ecommerce',
            'goals' => ['build_ecommerce'],
            'budget' => 'under_5000',
            'timeline' => 'flexible',
        ]);

        $this->assertNull($result['recommendations']['best_match']);
        $this->assertNotNull($result['recommendations']['fallback']);
    }

    public function test_inactive_package_is_never_recommended(): void
    {
        Package::query()->where('slug', 'digital-marketing-package')->update(['is_active' => false]);

        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'restaurants-food',
            'business_subtype' => 'chicken-restaurant',
            'goals' => ['increase_sales', 'run_ads'],
            'budget' => '10000_25000',
            'timeline' => '1_3_months',
        ]);

        $best = $result['recommendations']['best_match']['slug'] ?? null;
        $this->assertNotSame('digital-marketing-package', $best);
        if ($best) {
            $this->assertTrue(Package::query()->where('slug', $best)->where('is_active', true)->exists());
        }
    }

    public function test_validator_strips_unknown_package_and_service(): void
    {
        $catalog = [
            'packages' => Package::query()->active()->get()->map(fn (Package $package) => [
                'id' => $package->id,
                'slug' => $package->slug,
                'name' => $package->name,
                'description' => $package->description,
                'category' => $package->category->value,
                'price' => (float) $package->price,
                'discount_amount' => (float) $package->discount_amount,
                'final_price' => (float) $package->finalPrice(),
                'currency' => $package->currency,
                'duration_days' => $package->duration_days,
                'is_active' => true,
                'items' => [],
            ])->all(),
            'services' => Service::query()->active()->get()->map(fn (Service $service) => [
                'id' => $service->id,
                'slug' => $service->slug,
                'name' => $service->name,
                'summary' => $service->summary,
                'category' => $service->category->value,
                'base_price' => (float) $service->base_price,
                'currency' => $service->currency,
                'duration_days' => $service->duration_days,
                'is_active' => true,
            ])->all(),
            'printing_slugs' => ['a5-flyers'],
        ];

        $validated = app(RecommendationValidator::class)->validate([
            'best_match' => ['slug' => 'totally-fake-package', 'name' => 'Fake', 'final_price' => 9],
            'alternative' => ['slug' => 'foundation-package', 'name' => 'Wrong name', 'final_price' => 1],
            'services' => [
                ['slug' => 'ghost-service', 'name' => 'Ghost'],
                ['slug' => 'graphic-design', 'name' => 'Wrong'],
            ],
            'printing' => ['product_slug' => 'invented-flyer'],
            'cta' => ['type' => 'choose_package', 'label' => 'x', 'path' => '/packages'],
        ], $catalog);

        $this->assertNull($validated['best_match']);
        $this->assertSame('foundation-package', $validated['alternative']['slug']);
        $this->assertEquals(9000, $validated['alternative']['final_price']);
        $this->assertCount(1, $validated['services']);
        $this->assertSame('graphic-design', $validated['services'][0]['slug']);
        $this->assertNull($validated['printing']['product_slug']);
    }

    public function test_conversation_extracts_restaurant_facts_and_skips_repeats(): void
    {
        $token = $this->postJson('/api/consultations')->json('data.token');
        $this->postJson('/api/consultations/'.$token.'/answers', [
            'question_id' => 'help_mode',
            'value' => 'chat',
        ])->assertOk();

        $data = $this->postJson('/api/consultations/'.$token.'/messages', [
            'message' => 'عندي مطعم فراخ فرعين في سموحة وبنبيع delivery كويس بس عايز نزود الطلبات',
        ])->assertOk()->json('data');

        $this->assertSame('restaurants-food', $data['state']['business_category']);
        $this->assertSame('chicken-restaurant', $data['state']['business_subtype']);
        $this->assertSame('سموحة', $data['state']['location']);
        $this->assertContains('increase_sales', $data['state']['goals']);
        $this->assertNotSame('business_category', $data['prompt']['id'] ?? null);
        $this->assertNotSame('business_subtype', $data['prompt']['id'] ?? null);
    }

    public function test_openai_failure_falls_back_to_rules(): void
    {
        config()->set('consultant.provider', 'openai');
        config()->set('consultant.openai.api_key', 'test-key');
        ConsultantSettings::update(['provider' => 'openai']);
        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => 'down'], 503),
        ]);

        $token = $this->postJson('/api/consultations')->json('data.token');
        $this->postJson('/api/consultations/'.$token.'/messages', [
            'message' => 'لدي متجر إلكتروني للأزياء وأريد بناء ecommerce',
        ])->assertOk()
            ->assertJsonPath('data.state.business_category', 'ecommerce');
    }

    public function test_malformed_token_and_disabled_consultant(): void
    {
        $this->getJson('/api/consultations/not-a-real-token')->assertNotFound();

        ConsultantSettings::update(['enabled' => false]);

        $this->postJson('/api/consultations')
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_use_consultant_but_cannot_access_admin_or_workspace(): void
    {
        $customer = User::factory()->create();
        $token = $customer->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/consultations')
            ->assertCreated()
            ->assertJsonPath('data.status', ConsultationStatus::InProgress->value);

        $this->withToken($token)->getJson('/api/admin/consultant')->assertForbidden();
        $this->withToken($token)->getJson('/api/admin/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/workspace/projects')->assertForbidden();
        $this->withToken($token)->getJson('/api/workspace/tasks')->assertForbidden();
    }

    public function test_guest_and_employee_cannot_access_admin_consultant_settings(): void
    {
        $this->getJson('/api/admin/consultant')->assertUnauthorized();

        $employee = User::factory()->webDeveloper()->create();
        $this->withToken($employee->createToken('auth')->plainTextToken)
            ->getJson('/api/admin/consultant')
            ->assertForbidden();

        $this->withToken($employee->createToken('auth')->plainTextToken)
            ->getJson('/api/admin/employees')
            ->assertForbidden();
    }

    public function test_owner_can_toggle_consultant_and_lead_capture_works(): void
    {
        $owner = User::factory()->owner()->create();
        $ownerToken = $owner->createToken('auth')->plainTextToken;

        $this->withToken($ownerToken)
            ->getJson('/api/admin/consultant')
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $consultation = $this->postJson('/api/consultations')->json('data');

        $this->postJson('/api/consultations/'.$consultation['token'].'/lead', [
            'name' => 'منى',
            'email' => 'mona@example.com',
            'phone' => '0500000000',
            'business_name' => 'بيت الفراخ',
            'contact_method' => 'phone',
        ])->assertCreated()->assertJsonPath('data.lead_captured', true);

        $this->assertDatabaseHas('consultation_leads', [
            'email' => 'mona@example.com',
            'business_name' => 'بيت الفراخ',
        ]);

        $this->withToken($ownerToken)
            ->patchJson('/api/admin/consultant', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_consultation_does_not_expose_internal_or_inactive_prices_from_fake_data(): void
    {
        Package::factory()->inactive()->create([
            'slug' => 'secret-internal-package',
            'name' => 'Internal Only',
            'category' => PackageCategory::Marketing,
            'price' => 999999,
        ]);

        $config = $this->getJson('/api/consultations/config')->assertOk()->json('data');
        $this->assertTrue($config['enabled']);
        $this->assertNotEmpty($config['categories']);

        $token = $this->postJson('/api/consultations')->json('data.token');
        $result = $this->completeFlow($token, [
            'help_mode' => 'guided',
            'business_category' => 'restaurants-food',
            'business_subtype' => 'cafe',
            'goals' => ['increase_sales'],
            'budget' => '10000_25000',
            'timeline' => 'flexible',
        ]);

        $slugs = array_filter([
            $result['recommendations']['best_match']['slug'] ?? null,
            $result['recommendations']['alternative']['slug'] ?? null,
        ]);
        $this->assertNotContains('secret-internal-package', $slugs);
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    private function completeFlow(string $token, array $seed): array
    {
        foreach ($seed as $questionId => $value) {
            $this->postJson('/api/consultations/'.$token.'/answers', [
                'question_id' => $questionId,
                'value' => $value,
            ])->assertOk();
        }

        $data = $this->getJson('/api/consultations/'.$token)->assertOk()->json('data');
        $guard = 0;

        while (($data['status'] ?? null) !== ConsultationStatus::Completed->value && isset($data['prompt']['id']) && $guard < 24) {
            $this->postJson('/api/consultations/'.$token.'/answers', [
                'question_id' => $data['prompt']['id'],
                'value' => $this->defaultAnswer($data['prompt']),
            ])->assertOk();
            $data = $this->getJson('/api/consultations/'.$token)->assertOk()->json('data');
            $guard++;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $prompt
     */
    private function defaultAnswer(array $prompt): mixed
    {
        $id = $prompt['id'] ?? '';

        if (($prompt['type'] ?? '') === 'multi_chips') {
            $first = $prompt['options'][0]['id'] ?? 'other';

            return [$first];
        }

        if (($prompt['type'] ?? '') === 'text') {
            return $id === 'event_date' ? '2026-11-01' : 'اختبار';
        }

        return $prompt['options'][0]['id'] ?? '__skip__';
    }
}
