<?php

namespace Tests\Feature;

use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmPipelineStage;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NeedsDiscoveryWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        CrmLeadSource::query()->create([
            'name' => 'Needs Discovery',
            'slug' => 'needs-discovery',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        CrmPipelineStage::query()->create([
            'name' => 'New Lead',
            'slug' => 'new-lead',
            'probability' => 5,
            'is_won' => false,
            'is_lost' => false,
            'sort_order' => 1,
        ]);
        CrmPipelineStage::query()->create([
            'name' => 'Needs Analysis',
            'slug' => 'needs-analysis',
            'probability' => 40,
            'is_won' => false,
            'is_lost' => false,
            'sort_order' => 2,
        ]);
        CrmPipelineStage::query()->create([
            'name' => 'Qualified',
            'slug' => 'qualified',
            'probability' => 30,
            'is_won' => false,
            'is_lost' => false,
            'sort_order' => 3,
        ]);
    }

    private function asOwner(): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(
            User::factory()->owner()->create()->createToken('auth')->plainTextToken
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'سارة أحمد',
            'phone' => '0551234567',
            'email' => 'sara@example.test',
            'company' => 'شركة تجريبية',
            'answers' => [
                'need' => ['label' => 'هوية وتصميم', 'value' => 'هوية وتصميم'],
                'project_type' => ['label' => 'مشروع جديد', 'value' => 'مشروع جديد'],
                'service' => ['label' => 'أخرى / لست متأكداً', 'value' => 'أخرى', 'id' => 'service:other'],
                'budget' => ['label' => '5,000 – 15,000 ر.س', 'value' => '5,000 – 15,000 ر.س'],
                'deadline' => ['label' => 'خلال شهر', 'value' => 'خلال شهر'],
                'has_files' => ['label' => 'لا حالياً', 'value' => 'no'],
            ],
        ], $overrides);
    }

    public function test_steps_endpoint_returns_deterministic_flow(): void
    {
        $this->getJson('/api/needs-discovery/steps')
            ->assertOk()
            ->assertJsonPath('data.title', 'اكتشف احتياجك')
            ->assertJsonCount(7, 'data.steps');
    }

    public function test_public_submit_creates_requirement_and_crm_lead(): void
    {
        $this->post('/api/needs-discovery', array_merge($this->payload(), [
            'attachments' => [UploadedFile::fake()->create('brief.pdf', 80, 'application/pdf')],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.updated_lead', false);

        $this->assertSame(1, Requirement::query()->count());
        $this->assertSame(1, CrmLead::query()->count());

        $requirement = Requirement::query()->firstOrFail();
        $this->assertSame('سارة أحمد', $requirement->name);
        $this->assertSame('needs-discovery', $requirement->source);
        $this->assertNotEmpty($requirement->attachments);
        $this->assertNotNull($requirement->crm_lead_id);
        Storage::disk('local')->assertExists($requirement->attachments[0]['path']);
    }

    public function test_matching_email_or_phone_updates_existing_lead_without_duplicate(): void
    {
        $this->postJson('/api/needs-discovery', $this->payload())->assertCreated();
        $this->assertSame(1, CrmLead::query()->count());

        $this->postJson('/api/needs-discovery', $this->payload([
            'name' => 'سارة مجدداً',
            'company' => 'شركة محدّثة',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.updated_lead', true);

        $this->assertSame(1, CrmLead::query()->count());
        $this->assertSame(2, Requirement::query()->count());
        $this->assertStringContainsString('اكتشف احتياجك', (string) CrmLead::query()->firstOrFail()->notes);
    }

    public function test_owner_can_list_and_qualify_requirement_creating_task(): void
    {
        $this->postJson('/api/needs-discovery', $this->payload())->assertCreated();
        $requirement = Requirement::query()->firstOrFail();

        $this->asOwner()
            ->getJson('/api/owner/requirements')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.reference', $requirement->reference);

        $this->asOwner()
            ->patchJson('/api/owner/requirements/'.$requirement->id, ['qualify' => true])
            ->assertOk()
            ->assertJsonPath('data.status', RequirementStatus::Qualified->value)
            ->assertJsonPath('data.qualified', true)
            ->assertJsonStructure(['data' => ['task' => ['id', 'title']]]);

        $this->assertNotNull($requirement->fresh()->task_id);
    }

    public function test_customer_cannot_access_owner_requirements(): void
    {
        $token = User::factory()->create(['role' => UserRole::Customer->value])->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/owner/requirements')
            ->assertForbidden();
    }

    public function test_submit_requires_contact_channel(): void
    {
        $this->postJson('/api/needs-discovery', $this->payload([
            'phone' => null,
            'email' => null,
        ]))->assertUnprocessable();
    }
}
