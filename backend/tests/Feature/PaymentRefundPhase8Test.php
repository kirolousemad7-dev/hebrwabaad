<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingRequestStatus;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentSetting;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use App\Services\Payments\PayTabsCheckoutGateway;
use App\Services\Payments\PayTabsConfigurationValidator;
use App\Services\Printing\PrintingExecutionEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentRefundPhase8Test extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'hebr-test-paytabs-server-key-phase8';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.enabled' => true,
            'payments.paytabs.profile_id' => 154601,
            'payments.paytabs.server_key' => self::SERVER_KEY,
            'payments.paytabs.base_url' => 'https://secure-egypt.paytabs.com',
            'payments.paytabs.environment' => 'test',
            'payments.paytabs.timeout' => 15,
            'app.url' => 'http://127.0.0.1:8000',
        ]);

        $this->app->bind(CardPaymentGateway::class, PayTabsCheckoutGateway::class);

        PaymentSetting::current()->update([
            'card_enabled' => true,
        ]);
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{owner: User, customer: User, request: PrintingRequest, quotation: PrintingQuotation, payment: Payment}
     */
    private function paidPrintingSetup(
        string $amount = '100.00',
        PrintingRequestStatus $status = PrintingRequestStatus::InProgress,
    ): array {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => $status,
        ]);
        $quotation = PrintingQuotation::factory()->accepted()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'total' => $amount,
            'subtotal' => $amount,
            'payment_policy' => PrintingPaymentPolicy::Full,
        ]);
        $payment = Payment::factory()->paid()->create([
            'customer_id' => $customer->id,
            'order_id' => null,
            'printing_quotation_id' => $quotation->id,
            'amount' => $amount,
            'currency' => 'SAR',
            'payment_method' => PaymentMethod::Card,
            'provider' => 'paytabs',
            'provider_transaction_id' => 'TST-ORIG-REF-1',
            'checkout_session_id' => 'HEBR-PQ-P1',
        ]);

        return compact('owner', 'customer', 'request', 'quotation', 'payment');
    }

    public function test_full_refund_confirms_and_keeps_payment_amount_immutable(): void
    {
        $setup = $this->paidPrintingSetup();

        Http::fake([
            'https://secure-egypt.paytabs.com/payment/request' => Http::response([
                'tran_ref' => 'TST-REFUND-1',
                'response_status' => 'A',
                'payment_result' => ['response_status' => 'A'],
            ], 200),
        ]);

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '100.00',
                'reason' => 'Full refund test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.refund.status', PaymentRefundStatus::Confirmed->value)
            ->assertJsonPath('data.payment_amount', '100.00')
            ->assertJsonPath('data.net_paid', '0.00');

        $this->assertSame('100.00', (string) $setup['payment']->fresh()->amount);
        $this->assertSame(PaymentStatus::Paid, $setup['payment']->fresh()->status);
    }

    public function test_partial_refund_reduces_net_paid_only(): void
    {
        $setup = $this->paidPrintingSetup('200.00');

        Http::fake([
            'https://secure-egypt.paytabs.com/payment/request' => Http::response([
                'tran_ref' => 'TST-REFUND-PARTIAL',
                'payment_result' => ['response_status' => 'A'],
            ], 200),
        ]);

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '50.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.net_paid', '150.00');

        $this->assertSame('200.00', (string) $setup['payment']->fresh()->amount);
    }

    public function test_over_refund_is_rejected(): void
    {
        $setup = $this->paidPrintingSetup();

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '100.01',
                'manual' => true,
            ])
            ->assertStatus(422);
    }

    public function test_duplicate_open_refund_is_rejected(): void
    {
        $setup = $this->paidPrintingSetup();

        PaymentRefund::query()->create([
            'payment_id' => $setup['payment']->id,
            'provider' => 'manual',
            'amount' => '10.00',
            'currency' => 'SAR',
            'status' => PaymentRefundStatus::Pending,
            'is_manual' => true,
            'requested_by' => $setup['owner']->id,
            'requested_at' => now(),
        ]);

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '10.00',
                'manual' => true,
            ])
            ->assertStatus(422);
    }

    public function test_refund_rbac_owner_only(): void
    {
        $setup = $this->paidPrintingSetup();
        $manager = User::factory()->adminManager()->create();

        $this->asUser($manager)
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '10.00',
                'manual' => true,
            ])
            ->assertForbidden();

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '10.00',
                'manual' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.refund.status', PaymentRefundStatus::Pending->value);
    }

    public function test_manual_confirm_marks_confirmed(): void
    {
        $setup = $this->paidPrintingSetup();

        $created = $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '25.00',
                'manual' => true,
            ])
            ->assertCreated()
            ->json('data.refund.id');

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payment-refunds/'.$created.'/confirm-manual')
            ->assertOk()
            ->assertJsonPath('data.refund.status', PaymentRefundStatus::Confirmed->value)
            ->assertJsonPath('data.net_paid', '75.00');
    }

    public function test_paytabs_refund_success_and_failure_mocked(): void
    {
        $setup = $this->paidPrintingSetup();

        Http::fake([
            'https://secure-egypt.paytabs.com/payment/request' => Http::sequence()
                ->push([
                    'tran_ref' => 'TST-RF-OK',
                    'payment_result' => ['response_status' => 'A'],
                ], 200)
                ->push([
                    'tran_ref' => 'TST-RF-FAIL',
                    'payment_result' => ['response_status' => 'D'],
                ], 200),
        ]);

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '10.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.refund.status', PaymentRefundStatus::Confirmed->value);

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '10.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.refund.status', PaymentRefundStatus::Failed->value);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return ($data['tran_type'] ?? null) === 'refund'
                && ($data['tran_class'] ?? null) === 'ecom'
                && ($data['tran_ref'] ?? null) === 'TST-ORIG-REF-1'
                && str_contains((string) ($data['cart_id'] ?? ''), '-RF')
                && $request->hasHeader('Authorization', self::SERVER_KEY);
        });
    }

    public function test_printing_status_not_auto_cancelled_after_refund(): void
    {
        $setup = $this->paidPrintingSetup('100.00', PrintingRequestStatus::InProgress);

        $before = app(PrintingExecutionEligibilityService::class)->eligible($setup['request']);
        $this->assertTrue($before['eligible']);

        Http::fake([
            'https://secure-egypt.paytabs.com/payment/request' => Http::response([
                'tran_ref' => 'TST-RF-EXEC',
                'payment_result' => ['response_status' => 'A'],
            ], 200),
        ]);

        $this->asUser($setup['owner'])
            ->postJson('/api/admin/payments/'.$setup['payment']->id.'/refunds', [
                'amount' => '100.00',
            ])
            ->assertCreated();

        $this->assertSame(PrintingRequestStatus::InProgress, $setup['request']->fresh()->status);

        $after = app(PrintingExecutionEligibilityService::class)->eligible($setup['request']->fresh());
        $this->assertFalse($after['eligible']);

        $refund = PaymentRefund::query()->latest('id')->first();
        $this->assertSame('refund_after_execution', $refund?->metadata['attention'] ?? null);
    }

    public function test_paytabs_status_command_is_read_only(): void
    {
        Http::fake();

        $this->artisan('payments:paytabs-status')
            ->expectsOutputToContain('PayTabs configuration')
            ->expectsOutputToContain('Read-only')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_config_validator_never_leaks_server_key(): void
    {
        $status = app(PayTabsConfigurationValidator::class)->status();
        $encoded = json_encode($status, JSON_UNESCAPED_UNICODE);

        $this->assertTrue($status['has_server_key']);
        $this->assertTrue($status['configured']);
        $this->assertSame('4601', $status['profile_id_hint']);
        $this->assertStringNotContainsString(self::SERVER_KEY, (string) $encoded);
        $this->assertArrayNotHasKey('server_key', $status);

        $capabilities = $this->asUser(User::factory()->owner()->create())
            ->getJson('/api/operations/payment-capabilities')
            ->assertOk()
            ->json('data');

        $this->assertTrue($capabilities['paytabs']);
        $this->assertArrayHasKey('paytabs_config', $capabilities);
        $this->assertStringNotContainsString(self::SERVER_KEY, (string) json_encode($capabilities));
    }

    public function test_list_refunds_endpoint(): void
    {
        $setup = $this->paidPrintingSetup();

        PaymentRefund::query()->create([
            'payment_id' => $setup['payment']->id,
            'provider' => 'manual',
            'amount' => '5.00',
            'currency' => 'SAR',
            'status' => PaymentRefundStatus::Confirmed,
            'is_manual' => true,
            'requested_by' => $setup['owner']->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $this->asUser($setup['owner'])
            ->getJson('/api/admin/payments/'.$setup['payment']->id.'/refunds')
            ->assertOk()
            ->assertJsonPath('data.refundable_amount', '95.00')
            ->assertJsonPath('data.items.0.amount', '5.00');
    }
}
