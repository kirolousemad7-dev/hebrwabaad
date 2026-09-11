<?php

namespace Tests\Feature;

use App\Enums\ApprovalRequestType;
use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\CalendarItem;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowAutomation;
use App\Models\WorkflowAutomationRun;
use App\Services\OrderService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsPhase3Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_crud_departments(): void
    {
        $owner = User::factory()->owner()->create();

        $create = $this->asUser($owner)->postJson('/api/operations/departments', [
            'name' => 'التصميم',
            'description' => 'فريق التصميم',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'التصميم');

        $id = $create->json('data.id');

        $this->asUser($owner)->getJson('/api/operations/departments')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'التصميم');

        $this->asUser($owner)->getJson('/api/operations/departments/options')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $id);

        $this->asUser($owner)->putJson('/api/operations/departments/'.$id, [
            'name' => 'التصميم الجرافيكي',
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'التصميم الجرافيكي');

        $this->asUser($owner)->deleteJson('/api/operations/departments/'.$id)
            ->assertOk();

        $this->assertSoftDeleted('departments', ['id' => $id]);
    }

    public function test_owner_can_assign_employee_department(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->graphicDesigner()->create();
        $department = Department::query()->create([
            'name' => 'التطوير',
            'slug' => 'dev',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->asUser($owner)->postJson('/api/operations/departments/assign-employee', [
            'user_id' => $employee->id,
            'department_id' => $department->id,
        ])->assertOk()
            ->assertJsonPath('data.department_id', $department->id);

        $this->assertSame($department->id, $employee->fresh()->department_id);
    }

    public function test_owner_can_view_project_workspace(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $employee = User::factory()->webDeveloper()->create();

        $project = Project::factory()->create([
            'account_manager_id' => $manager->id,
            'customer_id' => $customer->id,
            'title' => 'مشروع تجريبي',
        ]);

        $this->asUser($owner)->putJson('/api/operations/projects/'.$project->id.'/members', [
            'members' => [
                ['user_id' => $employee->id, 'role' => 'member'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.members.0.user_id', $employee->id);

        $this->asUser($owner)->getJson('/api/operations/projects/'.$project->id.'/workspace')
            ->assertOk()
            ->assertJsonPath('data.project.title', 'مشروع تجريبي')
            ->assertJsonStructure([
                'data' => [
                    'progress',
                    'calendar_tasks',
                    'members',
                    'files_count',
                    'health',
                    'upcoming_calendar_items',
                ],
            ]);
    }

    public function test_automation_order_created_is_idempotent(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer, 'is_active' => true]);

        $automation = WorkflowAutomation::query()->create([
            'name' => 'Order follow-up',
            'trigger' => WorkflowTrigger::OrderCreated->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'متابعة طلب جديد',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $order = app(OrderService::class)->create($manager, [
            'title' => 'طلب اختبار',
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseHas('calendar_items', [
            'title' => 'متابعة طلب جديد',
            'type' => CalendarItemType::Task->value,
        ]);

        $runsBefore = WorkflowAutomationRun::query()->where('automation_id', $automation->id)->count();
        $this->assertSame(1, $runsBefore);

        $calendarCount = CalendarItem::query()->where('title', 'متابعة طلب جديد')->count();

        app(WorkflowAutomationEngine::class)->dispatch('order.created', [
            'source_type' => 'order',
            'source_id' => $order->id,
            'actor_id' => $manager->id,
            'title' => 'متابعة طلب: '.$order->title,
            'related_type' => 'order',
            'related_id' => $order->id,
            'assignee_ids' => [$manager->id],
        ]);

        $this->assertSame($calendarCount, CalendarItem::query()->where('title', 'متابعة طلب جديد')->count());
        $this->assertSame(1, WorkflowAutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_command_center_ok_for_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->getJson('/api/operations/command-center')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'today_tasks',
                        'overdue',
                        'today_meetings',
                        'upcoming_deliveries',
                        'projects_need_attention',
                    ],
                    'attention',
                    'today_timeline',
                    'workload_snapshot',
                    'project_health',
                ],
            ]);
    }

    public function test_my_day_ok_for_employee(): void
    {
        $employee = User::factory()->graphicDesigner()->create();

        $this->asUser($employee)->getJson('/api/operations/my-day')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'items',
                    'overdue',
                    'upcoming',
                    'counts',
                ],
            ]);
    }

    public function test_approval_flow(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->webDeveloper()->create();

        $create = $this->asUser($employee)->postJson('/api/operations/approvals', [
            'type' => ApprovalRequestType::CalendarTaskCompletion->value,
            'related_type' => 'calendar_item',
            'related_id' => 1,
            'title' => 'اعتماد إكمال مهمة',
            'assigned_to' => $owner->id,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->asUser($owner)->getJson('/api/operations/approvals/inbox')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $id);

        $this->asUser($owner)->postJson('/api/operations/approvals/'.$id.'/approve', [
            'decision_notes' => 'موافق',
        ])->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_search_permission_filters_orders_and_crm(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->graphicDesigner()->create();

        $ownerSearch = $this->asUser($owner)->getJson('/api/operations/search?q=test')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('orders', $ownerSearch);
        $this->assertArrayHasKey('crm_leads', $ownerSearch);

        $employeeSearch = $this->asUser($employee)->getJson('/api/operations/search?q=test')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $employeeSearch['orders']);
        $this->assertSame([], $employeeSearch['crm_leads']);
    }

    public function test_calendar_filter_by_department_id(): void
    {
        $owner = User::factory()->owner()->create();
        $department = Department::query()->create([
            'name' => 'التسويق',
            'slug' => 'marketing',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $withDept = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'مهمة قسم',
            'department_id' => $department->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'type' => CalendarItemType::Task->value,
        ]);
        $withDept->assignees()->sync([$owner->id]);

        $without = CalendarItem::factory()->create([
            'created_by' => $owner->id,
            'title' => 'مهمة عامة',
            'department_id' => null,
            'starts_at' => now()->addDay()->setTime(11, 0),
            'type' => CalendarItemType::Task->value,
        ]);
        $without->assignees()->sync([$owner->id]);

        $from = now()->toDateString();
        $to = now()->addDays(3)->toDateString();

        $filtered = $this->asUser($owner)->getJson(
            '/api/calendar?from='.$from.'&to='.$to.'&scope=team&include_linked=0&department_id='.$department->id
        )->assertOk();

        $titles = collect($filtered->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('مهمة قسم'));
        $this->assertFalse($titles->contains('مهمة عامة'));
    }

    public function test_inactive_automation_does_not_fire(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer, 'is_active' => true]);

        WorkflowAutomation::query()->create([
            'name' => 'Inactive order follow-up',
            'trigger' => WorkflowTrigger::OrderCreated->value,
            'conditions' => [],
            'actions' => [['type' => 'create_task', 'title' => 'لا يجب أن تظهر']],
            'is_active' => false,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        app(OrderService::class)->create($manager, [
            'title' => 'طلب صامت',
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseMissing('calendar_items', ['title' => 'لا يجب أن تظهر']);
        $this->assertSame(0, WorkflowAutomationRun::query()->count());
    }

    public function test_automation_conditions_match_and_fail(): void
    {
        $owner = User::factory()->owner()->create();
        $engine = app(WorkflowAutomationEngine::class);

        $automation = WorkflowAutomation::query()->create([
            'name' => 'Sales only',
            'trigger' => WorkflowTrigger::CrmOpportunityWon->value,
            'conditions' => [['field' => 'department', 'op' => 'eq', 'value' => 'sales']],
            'actions' => [['type' => 'create_task', 'title' => 'تجهيز مبيعات']],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $fail = $engine->dispatch('crm.opportunity.won', [
            'source_type' => 'crm_opportunity',
            'source_id' => 101,
            'department' => 'design',
            'actor_id' => $owner->id,
        ]);
        $this->assertSame('skipped', $fail[0]['status'] ?? null);

        $pass = $engine->dispatch('crm.opportunity.won', [
            'source_type' => 'crm_opportunity',
            'source_id' => 102,
            'department' => 'sales',
            'actor_id' => $owner->id,
            'assignee_ids' => [$owner->id],
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'title' => 'فرصة رابحة',
        ]);
        $this->assertSame(
            'success',
            $pass[0]['status'] ?? null,
            json_encode($pass[0] ?? [], JSON_UNESCAPED_UNICODE),
        );
        $this->assertDatabaseHas('calendar_items', ['title' => 'تجهيز مبيعات']);
        $this->assertSame(2, WorkflowAutomationRun::query()->where('automation_id', $automation->id)->count());
    }

    public function test_unauthorized_user_cannot_manage_automations(): void
    {
        $employee = User::factory()->graphicDesigner()->create();

        $this->asUser($employee)->postJson('/api/operations/automations', [
            'name' => 'Forbidden',
            'trigger' => WorkflowTrigger::OrderCreated->value,
            'actions' => [['type' => 'create_task', 'title' => 'x']],
        ])->assertForbidden();
    }

    public function test_invalid_action_config_is_logged_without_crash(): void
    {
        $owner = User::factory()->owner()->create();
        $engine = app(WorkflowAutomationEngine::class);

        WorkflowAutomation::query()->create([
            'name' => 'Bad action',
            'trigger' => WorkflowTrigger::OrderCreated->value,
            'conditions' => [],
            'actions' => [['type' => 'drop_table']],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $result = $engine->dispatch('order.created', [
            'source_type' => 'order',
            'source_id' => 9991,
            'actor_id' => $owner->id,
        ]);

        $this->assertSame('success', $result[0]['status'] ?? null);
        $this->assertFalse($result[0]['result']['actions'][0]['ok'] ?? true);
    }
}
