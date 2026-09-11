<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\PaymentSetting;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingQuotationPhase6Test extends TestCase
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
     * @return array{specialist: User, request: PrintingRequest}
     */
    private function pricedRequest(string $price = '1000.00'): array
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => $price,
            'status' => PrintingRequestStatus::Pending,
            'assigned_to' => $specialist->id,
        ]);

        return compact('specialist', 'request');
    }

    public function test_create_draft_and_send_returns_public_token(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $create = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::Full->value,
                'notes' => 'Customer-facing notes',
                'terms' => 'Net 0',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PrintingQuotationStatus::Draft->value)
            ->assertJsonPath('data.total', '1000.00');

        $id = (int) $create->json('data.id');

        $send = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', PrintingQuotationStatus::Sent->value);

        $token = $send->json('data.public_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertDatabaseMissing('printing_quotations', [
            'id' => $id,
            'public_token_hash' => $token,
        ]);
        $this->assertDatabaseHas('printing_quotations', [
            'id' => $id,
            'public_token_hash' => PrintingQuotation::hashToken($token),
            'status' => PrintingQuotationStatus::Sent->value,
        ]);
    }

    public function test_public_view_hides_internal_fields(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $send = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->assertCreated();

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$send->json('data.id').'/send')
            ->json('data.public_token');

        $payload = $this->getJson('/api/public/printing-quotations/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', PrintingQuotationStatus::Viewed->value)
            ->json('data');

        $this->assertArrayNotHasKey('assigned_to', $payload);
        $this->assertArrayNotHasKey('created_by', $payload);
        $this->assertArrayNotHasKey('public_token_hash', $payload);
        $this->assertArrayNotHasKey('customer_id', $payload);
        $this->assertSame('1000.00', $payload['total']);
    }

    public function test_accept_is_idempotent(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $first = $this->postJson('/api/public/printing-quotations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', PrintingQuotationStatus::Accepted->value);

        $this->assertNotNull($first->json('data.tracking_token'));

        $second = $this->postJson('/api/public/printing-quotations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', PrintingQuotationStatus::Accepted->value);

        $this->assertNull($second->json('data.tracking_token'));
        $this->assertDatabaseCount('printing_quotations', 1);
        $this->assertSame(
            PrintingRequestStatus::Pending->value,
            $request->fresh()->status->value,
        );
    }

    public function test_reject_by_token(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/reject', [
            'reason' => 'Too expensive',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingQuotationStatus::Rejected->value);

        $this->assertDatabaseHas('printing_quotations', [
            'id' => $id,
            'status' => PrintingQuotationStatus::Rejected->value,
            'rejection_reason' => 'Too expensive',
        ]);
    }

    public function test_expired_valid_until_blocks_accept(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'valid_until' => now()->subDay()->toDateString(),
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_revoked_token_blocked_after_revise(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/revise')
            ->assertCreated()
            ->assertJsonPath('data.revision', 2)
            ->assertJsonPath('data.status', PrintingQuotationStatus::Draft->value);

        $this->getJson('/api/public/printing-quotations/'.$token)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_full_payment_policy_eligibility_and_manual_payment(): void
    {
        $this->enableManualPayments();
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest('800.00');

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::Full->value,
                'total' => '800.00',
                'subtotal' => '800.00',
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')->assertOk();

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonFragment(['full_payment_not_met']);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/payments', [
                'method' => PaymentMethod::Instapay->value,
                'amount' => '800.00',
                'mark_paid' => true,
                'reference_number' => 'IP-999',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', PaymentStatus::Paid->value);

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true);
    }

    public function test_deposit_policy_eligibility(): void
    {
        $this->enableManualPayments();
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest('1000.00');

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::Deposit->value,
                'deposit_required' => '300.00',
                'total' => '1000.00',
                'subtotal' => '1000.00',
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')->assertOk();

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/payments', [
                'method' => PaymentMethod::BankTransfer->value,
                'amount' => '300.00',
                'mark_paid' => true,
            ])
            ->assertCreated();

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.payment_summary.requirement_met', true);
    }

    public function test_cannot_start_in_progress_without_full_payment_when_quote_accepted(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest('500.00');

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::Full->value,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')->assertOk();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
                'note' => 'try anyway',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_can_start_in_progress_when_none_policy_after_accept(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest('500.00');

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')->assertOk();

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingRequestStatus::InProgress->value);
    }

    public function test_staff_pdf_returns_200(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->pricedRequest();

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
            ])
            ->json('data.id');

        $this->asUser($specialist)
            ->get('/api/operations/printing-quotations/'.$id.'/pdf')
            ->assertOk();
    }
}
