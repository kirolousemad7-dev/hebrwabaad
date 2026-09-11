<?php

namespace Tests\Feature;

use App\Enums\CrmFollowUpType;
use App\Enums\CrmLeadStatus;
use App\Enums\UserRole;
use App\Models\ContactInquiry;
use App\Models\CrmLead;
use App\Models\CrmLostReason;
use App\Models\CrmPipelineStage;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\CrmSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSalesFlowTest extends TestCase
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

    public function test_contact_form_creates_crm_lead(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'سارة أحمد',
            'email' => 'sara@example.com',
            'phone' => '01001234567',
            'message' => 'أحتاج هوية بصرية لمتجري الإلكتروني بالكامل.',
        ])->assertCreated();

        $inquiry = ContactInquiry::query()->where('email', 'sara@example.com')->first();
        $this->assertNotNull($inquiry);
        $this->assertNotNull($inquiry->crm_lead_id);

        $lead = CrmLead::query()->find($inquiry->crm_lead_id);
        $this->assertNotNull($lead);
        $this->assertSame('سارة أحمد', $lead->full_name);
        $this->assertSame('sara@example.com', $lead->email);
        $this->assertSame(CrmLeadStatus::New, $lead->status);
        $this->assertMatchesRegularExpression('/^LD-\d{4}-\d{4}$/', $lead->reference);
    }

    public function test_sales_manager_assigns_rep_schedules_follow_up_moves_stage_quotes_and_converts(): void
    {
        $manager = User::factory()->salesManager()->create();
        $rep = User::factory()->salesRepresentative()->create();
        $accountManager = User::factory()->accountManager()->create();
        $this->assertNotNull($accountManager);

        $this->postJson('/api/contact', [
            'name' => 'عميل محتمل',
            'email' => 'lead@example.com',
            'phone' => '01112223334',
            'message' => 'أريد باقة تسويق رقمي متكاملة للمطعم.',
        ])->assertCreated();

        $lead = CrmLead::query()->where('email', 'lead@example.com')->firstOrFail();

        $this->asUser($manager)
            ->patchJson('/api/crm/leads/'.$lead->id.'/assign', [
                'assigned_to' => $rep->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $rep->id);

        $this->asUser($rep)
            ->postJson('/api/crm/follow-ups', [
                'lead_id' => $lead->id,
                'type' => CrmFollowUpType::Call->value,
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'notes' => 'اتصال تعارف',
            ])
            ->assertCreated()
            ->assertJsonPath('data.lead_id', $lead->id);

        $contacted = CrmPipelineStage::query()->where('slug', 'contacted')->firstOrFail();
        $qualified = CrmPipelineStage::query()->where('slug', 'qualified')->firstOrFail();

        $this->asUser($rep)
            ->patchJson('/api/crm/leads/'.$lead->id.'/stage', ['stage_id' => $contacted->id])
            ->assertOk()
            ->assertJsonPath('data.stage.slug', 'contacted');

        $this->asUser($rep)
            ->patchJson('/api/crm/leads/'.$lead->id.'/stage', ['stage_id' => $qualified->id])
            ->assertOk()
            ->assertJsonPath('data.stage.slug', 'qualified');

        $quotation = $this->asUser($rep)
            ->postJson('/api/crm/quotations', [
                'lead_id' => $lead->id,
                'items' => [
                    [
                        'description' => 'باقة تسويق رقمي',
                        'quantity' => 1,
                        'unit_price' => 15000,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertEquals(15000, (float) $quotation['total']);
        $this->assertMatchesRegularExpression('/^QT-\d{4}-\d{4}$/', $quotation['number']);

        $converted = $this->asUser($rep)
            ->postJson('/api/crm/leads/'.$lead->id.'/convert', [
                'deal_value' => 15000,
                'create_project' => true,
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame(CrmLeadStatus::Won->value, $converted['lead']['status']);
        $this->assertNotNull($converted['customer']['id']);
        $this->assertNotNull($converted['order']['reference']);
        $this->assertNotNull($converted['project']['id']);

        $customer = User::query()->find($converted['customer']['id']);
        $this->assertSame(UserRole::Customer, $customer?->role);
        $this->assertSame(1, Order::query()->where('customer_id', $customer?->id)->count());
    }

    public function test_rep_cannot_access_another_reps_lead(): void
    {
        $repA = User::factory()->salesRepresentative()->create();
        $repB = User::factory()->salesRepresentative()->create();
        $stage = CrmPipelineStage::query()->where('slug', 'new-lead')->firstOrFail();

        $lead = CrmLead::query()->create([
            'reference' => 'LD-2026-0099',
            'full_name' => 'Lead B',
            'email' => 'lead-b@example.com',
            'status' => CrmLeadStatus::New->value,
            'stage_id' => $stage->id,
            'assigned_to' => $repB->id,
        ]);

        $this->asUser($repA)
            ->getJson('/api/crm/leads/'.$lead->id)
            ->assertForbidden();

        $this->asUser($repA)
            ->getJson('/api/crm/leads')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_mark_lost_requires_reason(): void
    {
        $manager = User::factory()->salesManager()->create();
        $stage = CrmPipelineStage::query()->where('slug', 'new-lead')->firstOrFail();

        $lead = CrmLead::query()->create([
            'reference' => 'LD-2026-0100',
            'full_name' => 'Lost Candidate',
            'email' => 'lost@example.com',
            'status' => CrmLeadStatus::New->value,
            'stage_id' => $stage->id,
            'assigned_to' => $manager->id,
        ]);

        $this->asUser($manager)
            ->postJson('/api/crm/leads/'.$lead->id.'/lose', [])
            ->assertStatus(422);

        $reason = CrmLostReason::query()->firstOrFail();

        $this->asUser($manager)
            ->postJson('/api/crm/leads/'.$lead->id.'/lose', [
                'lost_reason_id' => $reason->id,
                'notes' => 'اختار منافس',
                'competitor' => 'Other Agency',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', CrmLeadStatus::Lost->value)
            ->assertJsonPath('data.lost_reason.id', $reason->id);
    }
}
