<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowTrigger;
use App\Models\PaymentSetting;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Models\WorkflowAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingTrackPhase6Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    private function enableManualPayments(): void
    {
        PaymentSetting::current()->update([
            'instapay_enabled' => true,
            'instapay_account_name' => 'حبر وأبعاد',
            'instapay_bank_name' => 'البنك الأهلي',
            'instapay_account_number' => 'SA1111222233334444555566',
            'instapay_instructions' => 'حوّل المبلغ.',
            'bank_transfer_enabled' => true,
            'bank_name' => 'البنك الأهلي',
            'bank_account_name' => 'حبر وأبعاد',
            'bank_account_number' => '1234567890',
            'bank_instructions' => 'حوّل ثم أبلغنا.',
        ]);
    }

    /**
     * @return array{specialist: User, request: PrintingRequest, tracking: string, quotationId: int}
     */
    private function acceptedWithTracking(string $policy = 'NONE'): array
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'product_name' => 'لوحة أكريليك',
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '500.00',
            'status' => PrintingRequestStatus::Pending,
            'assigned_to' => $specialist->id,
            'required_date' => now()->addDays(5)->toDateString(),
        ]);

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => $policy,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $tracking = $this->postJson('/api/public/printing-quotations/'.$token.'/accept')
            ->assertOk()
            ->json('data.tracking_token');

        $this->assertIsString($tracking);

        return [
            'specialist' => $specialist,
            'request' => $request->fresh(),
            'tracking' => $tracking,
            'quotationId' => (int) $id,
        ];
    }

    public function test_tracking_payload_is_privacy_safe(): void
    {
        ['specialist' => $specialist, 'request' => $request, 'tracking' => $tracking] = $this->acceptedWithTracking();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk();

        $payload = $this->getJson('/api/public/printing-track/'.$tracking)
            ->assertOk()
            ->assertJsonPath('data.status_key', PrintingRequestStatus::InProgress->value)
            ->assertJsonPath('data.status_label', 'جاري التنفيذ')
            ->assertJsonPath('data.product_name', 'لوحة أكريليك')
            ->json('data');

        $this->assertArrayNotHasKey('assigned_to', $payload);
        $this->assertArrayNotHasKey('assignee', $payload);
        $this->assertArrayNotHasKey('department', $payload);
        $this->assertArrayNotHasKey('assigned_department', $payload);
        $this->assertArrayNotHasKey('subtotal', $payload);
        $this->assertArrayNotHasKey('total', $payload);
        $this->assertArrayNotHasKey('deposit_required', $payload);
        $this->assertArrayNotHasKey('payment_policy', $payload);
        $this->assertArrayNotHasKey('notes', $payload);
        $this->assertArrayNotHasKey('created_by', $payload);
        $this->assertArrayHasKey('timeline', $payload);
        $this->assertNotEmpty($payload['timeline']);

        foreach ($payload['timeline'] as $event) {
            $this->assertArrayNotHasKey('actor_id', $event);
            $this->assertArrayNotHasKey('actor_type', $event);
            $this->assertContains($event['event'], [
                'accepted',
                'payment_recorded',
                'status_changed',
                'payment_requirement_met',
            ]);
        }
    }

    public function test_invalid_tracking_token_returns_422(): void
    {
        $this->getJson('/api/public/printing-track/'.str_repeat('z', 64))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_cancelled_status_uses_arabic_public_label(): void
    {
        ['specialist' => $specialist, 'request' => $request, 'tracking' => $tracking] = $this->acceptedWithTracking();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::Cancelled->value,
            ])
            ->assertOk();

        $this->getJson('/api/public/printing-track/'.$tracking)
            ->assertOk()
            ->assertJsonPath('data.status_key', PrintingRequestStatus::Cancelled->value)
            ->assertJsonPath('data.status_label', 'تم إلغاء الطلب');
    }

    public function test_completed_sets_delivered_at_when_null(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->acceptedWithTracking();

        $this->assertNull($request->delivered_at);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::ReadyForDelivery->value,
            ])
            ->assertOk();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::Completed->value,
            ])
            ->assertOk();

        $fresh = $request->fresh();
        $this->assertNotNull($fresh->delivered_at);
        $this->assertSame(PrintingRequestStatus::Completed, $fresh->status);
    }

    public function test_ready_for_delivery_triggers_workflow(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->acceptedWithTracking();

        WorkflowAutomation::query()->create([
            'name' => 'Ready delivery hook',
            'trigger' => WorkflowTrigger::PrintingReadyForDelivery->value,
            'conditions' => [],
            'actions' => [[
                'type' => 'create_task',
                'title' => 'تسليم طباعة',
            ]],
            'is_active' => true,
            'is_template' => false,
            'created_by' => $specialist->id,
        ]);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::ReadyForDelivery->value,
            ])
            ->assertOk();

        $this->assertDatabaseHas('workflow_automation_runs', [
            'trigger' => WorkflowTrigger::PrintingReadyForDelivery->value,
            'status' => WorkflowRunStatus::Success->value,
        ]);
    }

    public function test_patch_delivery_when_ready_for_delivery(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->acceptedWithTracking();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk();

        $this->asUser($specialist)
            ->patchJson('/api/operations/printing/'.$request->id.'/delivery', [
                'delivery_method' => 'PICKUP',
            ])
            ->assertStatus(422);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::ReadyForDelivery->value,
            ])
            ->assertOk();

        $this->asUser($specialist)
            ->patchJson('/api/operations/printing/'.$request->id.'/delivery', [
                'delivery_method' => 'PICKUP',
                'delivery_notes' => 'Front desk',
                'received_by' => 'Ahmed',
            ])
            ->assertOk()
            ->assertJsonPath('data.delivery_method', 'PICKUP')
            ->assertJsonPath('data.received_by', 'Ahmed');
    }

    public function test_accepted_pdf_uses_frozen_snapshot(): void
    {
        ['specialist' => $specialist, 'quotationId' => $id, 'tracking' => $tracking] = $this->acceptedWithTracking();
        unset($tracking);

        $quotation = PrintingQuotation::query()->findOrFail($id);
        $this->assertSame(PrintingQuotationStatus::Accepted, $quotation->status);
        $this->assertIsArray($quotation->snapshot);
        $this->assertSame('500.00', (string) ($quotation->snapshot['total'] ?? ''));

        $quotation->update([
            'total' => '9999.00',
            'subtotal' => '9999.00',
        ]);

        $body = $this->asUser($specialist)
            ->get('/api/operations/printing-quotations/'.$id.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('500.00', $body);
        $this->assertStringNotContainsString('9999.00', $body);
    }

    public function test_payment_receipt_and_staff_timeline(): void
    {
        $this->enableManualPayments();
        ['specialist' => $specialist, 'quotationId' => $id] = $this->acceptedWithTracking(
            PrintingPaymentPolicy::Full->value,
        );

        $paymentId = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/payments', [
                'method' => PaymentMethod::Instapay->value,
                'amount' => '500.00',
                'mark_paid' => true,
                'reference_number' => 'IP-TRACK-1',
            ])
            ->assertCreated()
            ->json('data.payment.id');

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/'.$id.'/payments/'.$paymentId.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.amount', '500.00')
            ->assertJsonPath('data.method', PaymentMethod::Instapay->value)
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $timeline = $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/'.$id.'/timeline')
            ->assertOk()
            ->json('data.items');

        $events = collect($timeline)->pluck('event')->all();
        $this->assertContains('payment_recorded', $events);
        $this->assertContains('accepted', $events);
    }
}
