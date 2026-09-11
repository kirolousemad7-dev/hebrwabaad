<?php

namespace Tests\Feature;

use App\Enums\CrmLeadStatus;
use App\Enums\CrmQuotationStatus;
use App\Models\CrmCompany;
use App\Models\CrmLead;
use App\Models\CrmPipelineStage;
use App\Models\User;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CrmSettingsSeeder::class);
    }

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

    private function createLeadFor(User $assignee, array $overrides = []): CrmLead
    {
        $stage = CrmPipelineStage::query()->where('slug', 'new-lead')->firstOrFail();

        return CrmLead::query()->create(array_merge([
            'reference' => 'LD-2026-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Lead '.$assignee->id,
            'email' => 'lead'.$assignee->id.random_int(1, 9999).'@example.com',
            'phone' => '0100'.random_int(1000000, 9999999),
            'status' => CrmLeadStatus::New->value,
            'stage_id' => $stage->id,
            'priority' => 'MEDIUM',
            'assigned_to' => $assignee->id,
            'score' => 0,
        ], $overrides));
    }

    public function test_company_create_and_list(): void
    {
        $manager = User::factory()->salesManager()->create();

        $created = $this->asUser($manager)
            ->postJson('/api/crm/companies', [
                'name' => 'Hebr Client Co',
                'email' => 'ops@hebr-client.test',
                'city' => 'Cairo',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Hebr Client Co', $created['name']);

        $this->asUser($manager)
            ->getJson('/api/crm/companies')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.name', 'Hebr Client Co');
    }

    public function test_contact_create_linked_to_company(): void
    {
        $manager = User::factory()->salesManager()->create();
        $company = CrmCompany::query()->create([
            'name' => 'Linked Co',
            'assigned_to' => $manager->id,
            'status' => 'active',
        ]);

        $this->asUser($manager)
            ->postJson('/api/crm/contacts', [
                'company_id' => $company->id,
                'name' => 'Primary Contact',
                'email' => 'primary@linked.test',
                'is_primary' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.name', 'Primary Contact');
    }

    public function test_target_create_and_progress(): void
    {
        $manager = User::factory()->salesManager()->create();
        $rep = User::factory()->salesRepresentative()->create();

        $this->asUser($manager)
            ->postJson('/api/crm/targets', [
                'user_id' => $rep->id,
                'period_type' => 'month',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'target_type' => 'deals_won',
                'target_value' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.target_type', 'deals_won');

        $progress = $this->asUser($manager)
            ->getJson('/api/crm/targets/progress')
            ->assertOk()
            ->json('data.items');

        $this->assertNotEmpty($progress);
        $this->assertArrayHasKey('actual_value', $progress[0]);
        $this->assertArrayHasKey('progress_percent', $progress[0]);
    }

    public function test_forecast_returns_keys(): void
    {
        $manager = User::factory()->salesManager()->create();

        $this->asUser($manager)
            ->getJson('/api/crm/forecast')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pipeline_total',
                    'weighted',
                    'commit',
                    'best_case',
                    'expected_this_month',
                    'expected_next_month',
                ],
            ]);
    }

    public function test_calendar_returns_events(): void
    {
        $manager = User::factory()->salesManager()->create();
        $lead = $this->createLeadFor($manager, [
            'expected_close_at' => now()->addDays(2)->toDateString(),
        ]);

        $events = $this->asUser($manager)
            ->getJson('/api/crm/calendar?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString())
            ->assertOk()
            ->json('data.events');

        $this->assertIsArray($events);
        $this->assertTrue(collect($events)->contains(fn ($e) => ($e['lead_id'] ?? null) === $lead->id));
    }

    public function test_lead_import_csv_creates_leads(): void
    {
        Storage::fake('local');
        $manager = User::factory()->salesManager()->create();

        $csv = "full_name,email,phone,company_name\nImport Person,import@example.com,01001112223,Import Co\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->asUser($manager)
            ->post('/api/crm/leads/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('crm_leads', [
            'email' => 'import@example.com',
            'full_name' => 'Import Person',
        ]);
    }

    public function test_export_csv_leads(): void
    {
        $manager = User::factory()->salesManager()->create();
        $this->createLeadFor($manager);

        $response = $this->asUser($manager)->get('/api/crm/export/leads?format=csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_bulk_assign(): void
    {
        $manager = User::factory()->salesManager()->create();
        $rep = User::factory()->salesRepresentative()->create();
        $lead = $this->createLeadFor($manager);

        $this->asUser($manager)
            ->postJson('/api/crm/leads/bulk', [
                'lead_ids' => [$lead->id],
                'action' => 'assign',
                'assigned_to' => $rep->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertSame($rep->id, $lead->fresh()?->assigned_to);
    }

    public function test_merge_leads(): void
    {
        $manager = User::factory()->salesManager()->create();
        $primary = $this->createLeadFor($manager, [
            'full_name' => 'Primary Lead',
            'email' => 'primary-merge@example.com',
            'phone' => null,
            'notes' => 'Keep me',
        ]);
        $secondary = $this->createLeadFor($manager, [
            'full_name' => 'Secondary Lead',
            'email' => 'secondary-merge@example.com',
            'phone' => '01110002000',
            'notes' => 'Extra notes',
        ]);

        $this->asUser($manager)
            ->postJson('/api/crm/leads/merge', [
                'primary_id' => $primary->id,
                'secondary_id' => $secondary->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $primary->id);

        $this->assertSame('01110002000', $primary->fresh()?->phone);
        $this->assertNotNull($secondary->fresh()?->archived_at);
    }

    public function test_quotation_discount_requires_approval_then_manager_approves(): void
    {
        $manager = User::factory()->salesManager()->create();
        $rep = User::factory()->salesRepresentative()->create();
        $lead = $this->createLeadFor($rep);

        $quotation = $this->asUser($rep)
            ->postJson('/api/crm/quotations', [
                'lead_id' => $lead->id,
                'discount_percent' => 25,
                'items' => [
                    ['description' => 'Package', 'quantity' => 1, 'unit_price' => 10000],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(CrmQuotationStatus::PendingApproval->value, $quotation['status']);

        $this->asUser($manager)
            ->postJson('/api/crm/quotations/'.$quotation['id'].'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', CrmQuotationStatus::Approved->value);
    }

    public function test_quotation_pdf_returns_pdf_or_html(): void
    {
        $manager = User::factory()->salesManager()->create();
        $lead = $this->createLeadFor($manager);

        $quotation = $this->asUser($manager)
            ->postJson('/api/crm/quotations', [
                'lead_id' => $lead->id,
                'items' => [
                    ['description' => 'Service', 'quantity' => 1, 'unit_price' => 500],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $response = $this->asUser($manager)->get('/api/crm/quotations/'.$quotation['id'].'/pdf');
        $response->assertOk();
        $contentType = (string) $response->headers->get('content-type');
        $this->assertTrue(
            str_contains($contentType, 'application/pdf') || str_contains($contentType, 'text/html'),
            'Expected pdf or html content type, got: '.$contentType
        );
    }

    public function test_public_quotation_token_accept(): void
    {
        $manager = User::factory()->salesManager()->create();
        $lead = $this->createLeadFor($manager);

        $quotation = $this->asUser($manager)
            ->postJson('/api/crm/quotations', [
                'lead_id' => $lead->id,
                'items' => [
                    ['description' => 'Offer', 'quantity' => 1, 'unit_price' => 1200],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $sent = $this->asUser($manager)
            ->postJson('/api/crm/quotations/'.$quotation['id'].'/send')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($sent['public_token']);

        $this->postJson('/api/public/quotations/'.$sent['public_token'].'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', CrmQuotationStatus::Accepted->value);
    }

    public function test_audit_log_manager_ok_rep_forbidden(): void
    {
        $manager = User::factory()->salesManager()->create();
        $rep = User::factory()->salesRepresentative()->create();
        $this->createLeadFor($manager);

        $this->asUser($manager)
            ->postJson('/api/crm/leads', [
                'full_name' => 'Audited Lead',
                'email' => 'audit@example.com',
                'phone' => '01220003333',
            ])
            ->assertCreated();

        $this->asUser($manager)
            ->getJson('/api/crm/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items', 'meta']]);

        $this->asUser($rep)
            ->getJson('/api/crm/audit-logs')
            ->assertForbidden();
    }

    public function test_rep_cannot_export_other_rep_leads(): void
    {
        $repA = User::factory()->salesRepresentative()->create();
        $repB = User::factory()->salesRepresentative()->create();
        $this->createLeadFor($repA, ['full_name' => 'Secret Lead A']);

        $response = $this->asUser($repB)->get('/api/crm/export/leads?format=csv');
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('Secret Lead A', $content);
    }

    public function test_customer_360_for_converted_customer(): void
    {
        $manager = User::factory()->salesManager()->create();
        User::factory()->accountManager()->create();
        $lead = $this->createLeadFor($manager, [
            'email' => 'customer360@example.com',
            'full_name' => '360 Customer',
        ]);

        $converted = $this->asUser($manager)
            ->postJson('/api/crm/leads/'.$lead->id.'/convert', [
                'deal_value' => 9000,
                'create_project' => true,
            ])
            ->assertOk()
            ->json('data');

        $customerId = $converted['customer']['id'];

        $this->asUser($manager)
            ->getJson('/api/crm/customers/'.$customerId)
            ->assertOk()
            ->assertJsonPath('data.customer.id', $customerId)
            ->assertJsonPath('data.metrics.orders', 1);
    }

    public function test_saved_filter_crud(): void
    {
        $manager = User::factory()->salesManager()->create();

        $filter = $this->asUser($manager)
            ->postJson('/api/crm/saved-filters', [
                'name' => 'Hot Cairo',
                'entity' => 'leads',
                'filters' => ['city' => 'Cairo', 'priority' => 'HIGH'],
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($manager)
            ->getJson('/api/crm/saved-filters')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Hot Cairo');

        $this->asUser($manager)
            ->deleteJson('/api/crm/saved-filters/'.$filter['id'])
            ->assertOk();

        $this->assertDatabaseMissing('crm_saved_filters', ['id' => $filter['id']]);
    }
}
