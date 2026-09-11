<?php

namespace Tests\Feature;

use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowTrigger;
use App\Models\Department;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Models\WorkflowAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingLifecyclePhase5Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_valid_status_transitions_follow_lifecycle_matrix(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::Estimated,
            'estimated_price' => 100,
            'status' => PrintingRequestStatus::Pending,
        ]);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingRequestStatus::InProgress->value);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::ReadyForDelivery->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingRequestStatus::ReadyForDelivery->value);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::Completed->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingRequestStatus::Completed->value);

        $this->assertDatabaseHas('printing_requests', [
            'id' => $request->id,
            'status' => PrintingRequestStatus::Completed->value,
        ]);
        $this->assertNotNull($request->fresh()->status_changed_at);
        $this->assertDatabaseCount('printing_status_histories', 3);
    }

    public function test_invalid_transition_is_rejected_with_422(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create([
            'status' => PrintingRequestStatus::Pending,
            'pricing_type' => PrintingPricingType::Estimated,
            'estimated_price' => 50,
        ]);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::Completed->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('printing_requests', [
            'id' => $request->id,
            'status' => PrintingRequestStatus::Pending->value,
        ]);
        $this->assertDatabaseCount('printing_status_histories', 0);
    }

    public function test_pending_to_in_progress_without_ready_pricing_requires_note(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create([
            'status' => PrintingRequestStatus::Pending,
            'pricing_type' => PrintingPricingType::QuoteRequired,
        ]);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
                'note' => 'بدء الإنتاج بقرار تشغيلي',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingRequestStatus::InProgress->value);
    }

    public function test_assign_updates_assignee_and_department(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $assignee = User::factory()->printingSpecialist()->create();
        $department = Department::query()->create([
            'name' => 'الطباعة',
            'slug' => 'printing',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $request = PrintingRequest::factory()->create();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/assign', [
                'assigned_to' => $assignee->id,
                'assigned_department_id' => $department->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_to.id', $assignee->id)
            ->assertJsonPath('data.assigned_department_id', $department->id);

        $this->assertDatabaseHas('printing_requests', [
            'id' => $request->id,
            'assigned_to' => $assignee->id,
            'assigned_department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('operations_audit_logs', [
            'action' => 'printing.assigned',
            'subject_id' => $request->id,
        ]);
    }

    public function test_history_is_logged_and_readable(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => 200,
            'status' => PrintingRequestStatus::Pending,
        ]);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
                'note' => 'بدء التنفيذ',
            ])
            ->assertOk();

        $this->asUser($specialist)
            ->getJson('/api/operations/printing/'.$request->id.'/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.to_status', PrintingRequestStatus::InProgress->value)
            ->assertJsonPath('data.items.0.note', 'بدء التنفيذ')
            ->assertJsonPath('data.items.0.actor.id', $specialist->id);

        $this->assertDatabaseHas('printing_status_histories', [
            'printing_request_id' => $request->id,
            'from_status' => PrintingRequestStatus::Pending->value,
            'to_status' => PrintingRequestStatus::InProgress->value,
            'actor_id' => $specialist->id,
            'note' => 'بدء التنفيذ',
        ]);
    }

    public function test_admin_pricing_endpoints_still_work_while_pending(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create([
            'status' => PrintingRequestStatus::Pending,
        ]);

        $this->asUser($specialist)
            ->patchJson('/api/admin/printing-requests/'.$request->id.'/pricing', [
                'estimated_price' => 850,
                'pricing_notes' => 'تقدير',
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', PrintingPricingType::Estimated->value)
            ->assertJsonPath('data.status', PrintingRequestStatus::Pending->value);

        $this->assertDatabaseHas('printing_requests', [
            'id' => $request->id,
            'status' => PrintingRequestStatus::Pending->value,
            'pricing_type' => PrintingPricingType::Estimated->value,
        ]);
    }

    public function test_status_changed_automation_create_task_smoke(): void
    {
        $owner = User::factory()->owner()->create();
        $request = PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::Estimated,
            'estimated_price' => 90,
            'status' => PrintingRequestStatus::Pending,
        ]);

        WorkflowAutomation::query()->create([
            'name' => 'On printing status',
            'trigger' => WorkflowTrigger::PrintingStatusChanged->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'متابعة حالة طباعة',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $this->asUser($owner)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk();

        $this->assertDatabaseHas('workflow_automation_runs', [
            'trigger' => WorkflowTrigger::PrintingStatusChanged->value,
            'status' => WorkflowRunStatus::Success->value,
        ]);
        $this->assertDatabaseHas('calendar_items', [
            'title' => 'متابعة حالة طباعة',
        ]);
    }

    public function test_unauthorized_employee_forbidden_from_lifecycle_endpoints(): void
    {
        $employee = User::factory()->graphicDesigner()->create();
        $request = PrintingRequest::factory()->create([
            'pricing_type' => PrintingPricingType::Estimated,
            'estimated_price' => 40,
        ]);

        $this->asUser($employee)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertForbidden();

        $this->asUser($employee)
            ->postJson('/api/operations/printing/'.$request->id.'/assign', [
                'assigned_to' => $employee->id,
            ])
            ->assertForbidden();

        $this->asUser($employee)
            ->getJson('/api/operations/printing/board')
            ->assertForbidden();
    }

    public function test_board_groups_by_all_statuses(): void
    {
        $owner = User::factory()->owner()->create();

        PrintingRequest::factory()->create(['status' => PrintingRequestStatus::Pending, 'product_name' => 'P']);
        PrintingRequest::factory()->inProgress()->create(['product_name' => 'I']);
        PrintingRequest::factory()->completed()->create(['product_name' => 'C']);

        $response = $this->asUser($owner)
            ->getJson('/api/operations/printing/board')
            ->assertOk();

        $columns = $response->json('data.columns');
        $this->assertArrayHasKey(PrintingRequestStatus::Pending->value, $columns);
        $this->assertArrayHasKey(PrintingRequestStatus::InProgress->value, $columns);
        $this->assertArrayHasKey(PrintingRequestStatus::ReadyForDelivery->value, $columns);
        $this->assertArrayHasKey(PrintingRequestStatus::Completed->value, $columns);
        $this->assertArrayHasKey(PrintingRequestStatus::Cancelled->value, $columns);
        $this->assertCount(1, $columns[PrintingRequestStatus::Pending->value]);
        $this->assertCount(1, $columns[PrintingRequestStatus::InProgress->value]);
        $this->assertCount(1, $columns[PrintingRequestStatus::Completed->value]);
    }

    public function test_list_status_filter_and_overdue_excludes_completed(): void
    {
        $owner = User::factory()->owner()->create();

        PrintingRequest::factory()->create([
            'status' => PrintingRequestStatus::InProgress,
            'required_date' => now()->subDays(2)->toDateString(),
            'product_name' => 'متأخر مفتوح',
        ]);
        PrintingRequest::factory()->completed()->create([
            'required_date' => now()->subDays(5)->toDateString(),
            'product_name' => 'مكتمل قديم',
        ]);

        $this->asUser($owner)
            ->getJson('/api/operations/printing?status='.PrintingRequestStatus::InProgress->value)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'متأخر مفتوح');

        $this->asUser($owner)
            ->getJson('/api/operations/printing/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.overdue', 1);
    }

    public function test_pending_can_be_cancelled(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $request = PrintingRequest::factory()->create([
            'status' => PrintingRequestStatus::Pending,
        ]);

        $this->asUser($specialist)
            ->patchJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::Cancelled->value,
                'note' => 'ألغي بناءً على طلب العميل',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingRequestStatus::Cancelled->value);
    }
}
