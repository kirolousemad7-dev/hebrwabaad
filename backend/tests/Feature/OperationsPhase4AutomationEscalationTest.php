<?php

namespace Tests\Feature;

use App\Enums\CalendarItemType;
use App\Enums\OrderStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowTrigger;
use App\Models\CalendarItem;
use App\Models\OperationalEscalationState;
use App\Models\Order;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkflowAutomation;
use App\Models\WorkflowAutomationRun;
use App\Services\Calendar\CalendarService;
use App\Services\Operations\EscalationService;
use App\Services\OrderService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OperationsPhase4AutomationEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_order_status_changed_fires(): void
    {
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer, 'is_active' => true]);

        WorkflowAutomation::query()->create([
            'name' => 'On status change',
            'trigger' => WorkflowTrigger::OrderStatusChanged->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'متابعة تغيير حالة الطلب',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $order = app(OrderService::class)->create($manager, [
            'title' => 'طلب حالة',
            'customer_id' => $customer->id,
        ]);

        app(OrderService::class)->transition($manager, $order, OrderStatus::Confirmed);

        $this->assertDatabaseHas('workflow_automation_runs', [
            'trigger' => WorkflowTrigger::OrderStatusChanged->value,
            'status' => WorkflowRunStatus::Success->value,
        ]);
        $this->assertDatabaseHas('calendar_items', [
            'title' => 'متابعة تغيير حالة الطلب',
        ]);
    }

    public function test_calendar_task_created_fires_when_service_creates_task(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->graphicDesigner()->create();

        WorkflowAutomation::query()->create([
            'name' => 'On calendar task',
            'trigger' => WorkflowTrigger::CalendarTaskCreated->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'notify_user',
                'user_id' => $owner->id,
                'title' => 'مهمة جديدة',
                'message' => 'تم إنشاء مهمة',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        Notification::fake();

        app(CalendarService::class)->create($employee, [
            'title' => 'مهمة يدوية',
            'type' => CalendarItemType::Task->value,
            'starts_at' => now()->addHour()->toIso8601String(),
            'assignee_ids' => [$employee->id],
        ]);

        $this->assertDatabaseHas('workflow_automation_runs', [
            'trigger' => WorkflowTrigger::CalendarTaskCreated->value,
            'status' => WorkflowRunStatus::Success->value,
        ]);
    }

    public function test_loop_protection_blocks_infinite_create_task_retrigger(): void
    {
        $owner = User::factory()->owner()->create();

        $automation = WorkflowAutomation::query()->create([
            'name' => 'Looping task creator',
            'trigger' => WorkflowTrigger::CalendarTaskCreated->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'مهمة من الأتمتة',
            ]],
            'is_active' => true,
            'is_template' => false,
            'max_depth' => 2,
            'max_actions_per_run' => 10,
            'created_by' => $owner->id,
        ]);

        app(CalendarService::class)->create($owner, [
            'title' => 'بذرة',
            'type' => CalendarItemType::Task->value,
            'starts_at' => now()->addHour()->toIso8601String(),
            'assignee_ids' => [$owner->id],
        ]);

        $createdByAutomation = CalendarItem::query()
            ->where('title', 'مهمة من الأتمتة')
            ->count();

        $this->assertLessThanOrEqual(2, $createdByAutomation);

        $skipped = WorkflowAutomationRun::query()
            ->where('automation_id', $automation->id)
            ->where('status', WorkflowRunStatus::Skipped->value)
            ->get();

        $this->assertTrue(
            $skipped->contains(fn (WorkflowAutomationRun $run) => in_array(
                data_get($run->result, 'reason'),
                ['loop_detected', 'blocked_depth'],
                true,
            )),
            'Expected a loop_detected or blocked_depth skip',
        );
    }

    public function test_dry_run_does_not_create_calendar_items(): void
    {
        $owner = User::factory()->owner()->create();

        $automation = WorkflowAutomation::query()->create([
            'name' => 'Dry run automation',
            'trigger' => WorkflowTrigger::OrderCreated->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'يجب ألا تُنشأ',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $before = CalendarItem::query()->count();

        $preview = $this->asUser($owner)->postJson('/api/operations/automations/'.$automation->id.'/dry-run', [
            'context' => [
                'title' => 'معاينة',
                'source_type' => 'order',
                'source_id' => 1,
            ],
        ])->assertOk()
            ->json('data');

        $this->assertTrue($preview['conditions_pass']);
        $this->assertNotEmpty($preview['planned_actions']);
        $this->assertSame($before, CalendarItem::query()->count());
        $this->assertDatabaseMissing('calendar_items', ['title' => 'يجب ألا تُنشأ']);
    }

    public function test_printing_approaching_dispatch_via_scheduled_command(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        WorkflowAutomation::query()->create([
            'name' => 'Printing approaching',
            'trigger' => WorkflowTrigger::PrintingRequiredDateApproaching->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'متابعة طباعة قريبة',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::Pending,
            'required_date' => now()->addDay()->toDateString(),
            'product_name' => 'بروشورات',
        ]);

        $this->artisan('workflows:run-scheduled-triggers', ['--printing-days' => 1])
            ->assertSuccessful();

        $this->assertDatabaseHas('workflow_automation_runs', [
            'trigger' => WorkflowTrigger::PrintingRequiredDateApproaching->value,
            'status' => WorkflowRunStatus::Success->value,
        ]);
        $this->assertDatabaseHas('calendar_items', [
            'title' => 'متابعة طباعة قريبة',
        ]);
    }

    public function test_quiet_hours_delays_automation_notify(): void
    {
        $owner = User::factory()->owner()->create();
        $target = User::factory()->accountManager()->create();

        UserNotificationPreference::query()->create([
            'user_id' => $target->id,
            ...UserNotificationPreference::defaults(),
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '00:00',
            'quiet_hours_end' => '23:59',
            'quiet_hours_timezone' => 'UTC',
        ]);

        $automation = WorkflowAutomation::query()->create([
            'name' => 'Quiet notify',
            'trigger' => WorkflowTrigger::OrderStatusChanged->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'notify_user',
                'user_id' => $target->id,
                'title' => 'تنبيه هادئ',
                'message' => 'يجب أن يتأخر',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $owner->id,
        ]);

        $order = Order::factory()->create([
            'account_manager_id' => $owner->id,
            'status' => OrderStatus::Received,
        ]);

        app(WorkflowAutomationEngine::class)->dispatch(WorkflowTrigger::OrderStatusChanged->value, [
            'source_type' => 'order',
            'source_id' => $order->id,
            'actor_id' => $owner->id,
            'order_id' => $order->id,
            'old_status' => OrderStatus::Received->value,
            'new_status' => OrderStatus::Confirmed->value,
        ], 'quiet-test');

        $this->assertDatabaseHas('delayed_notifications', [
            'user_id' => $target->id,
            'category' => 'automation',
        ]);
        $this->assertSame(0, $target->notifications()->count());
        $this->assertDatabaseHas('workflow_automation_runs', [
            'automation_id' => $automation->id,
            'status' => WorkflowRunStatus::Success->value,
        ]);
    }

    public function test_escalation_state_advances_without_spam_on_second_call(): void
    {
        $assignee = User::factory()->graphicDesigner()->create();

        $item = CalendarItem::factory()->create([
            'title' => 'مهمة متأخرة',
            'type' => CalendarItemType::Task,
            'status' => 'OVERDUE',
            'created_by' => $assignee->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $item->assignees()->sync([$assignee->id]);

        $service = app(EscalationService::class);
        $first = $service->processOverdue();
        $this->assertGreaterThan(0, $first['calendar']);

        $state = OperationalEscalationState::query()
            ->where('source_type', 'calendar_item')
            ->where('source_id', $item->id)
            ->where('rule_key', 'overdue_task')
            ->first();

        $this->assertNotNull($state);
        $levelAfterFirst = (int) $state->level;
        $notifiedAt = $state->last_notified_at?->toIso8601String();

        $second = $service->processOverdue();
        $this->assertSame(0, $second['calendar']);

        $state->refresh();
        $this->assertSame($levelAfterFirst, (int) $state->level);
        $this->assertSame($notifiedAt, $state->last_notified_at?->toIso8601String());
    }

    public function test_printing_operations_list_and_summary(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::Pending,
            'required_date' => now()->toDateString(),
            'product_name' => 'اليوم',
        ]);
        PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::Pending,
            'required_date' => now()->subDays(3)->toDateString(),
            'product_name' => 'متأخر',
        ]);

        $this->asUser($owner)->getJson('/api/operations/printing?category=today')
            ->assertOk()
            ->assertJsonPath('data.summary.today', 1);

        $this->asUser($owner)->getJson('/api/operations/printing/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.overdue', 1);
    }
}
