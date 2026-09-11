<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use App\Services\Payments\PayTabsCheckoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrintingPayTabsPhase7Test extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'hebr-test-paytabs-server-key';

    private int $tranSeq = 0;

    private string $hostedTranRef = 'TST241234567890';

    private int $requestStatus = 200;

    /**
     * @var list<array{0: int, 1: array<string, mixed>}>
     */
    private array $queryQueue = [];

    /**
     * @var array{0: int, 1: array<string, mixed>}|null
     */
    private ?array $lastQuery = null;

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
            'app.frontend_url' => 'http://localhost:5173',
        ]);

        $this->app->bind(CardPaymentGateway::class, PayTabsCheckoutGateway::class);

        PaymentSetting::current()->update([
            'card_enabled' => true,
            'instapay_enabled' => true,
            'instapay_account_name' => 'حبر وأبعاد',
            'instapay_bank_name' => 'البنك الأهلي',
            'instapay_account_number' => 'SA1111222233334444555566',
            'instapay_instructions' => 'حوّل المبلغ ثم أدخل رقم العملية.',
        ]);

        $this->queryQueue = [];
        $this->lastQuery = null;
        $this->requestStatus = 200;
        $this->hostedTranRef = 'TST241234567890';
        $this->fakePayTabsHttp();
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{specialist: User, request: PrintingRequest, customer: User}
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

        return compact('specialist', 'request', 'customer');
    }

    /**
     * @return array{specialist: User, request: PrintingRequest, customer: User, id: int, token: string, reference: string}
     */
    private function acceptedQuote(
        string $total = '1000.00',
        string $policy = 'FULL',
        ?string $deposit = null,
    ): array {
        $setup = $this->pricedRequest($total);
        $payload = [
            'printing_request_id' => $setup['request']->id,
            'payment_policy' => $policy,
            'total' => $total,
            'subtotal' => $total,
        ];

        if ($deposit !== null) {
            $payload['deposit_required'] = $deposit;
        }

        $id = $this->asUser($setup['specialist'])
            ->postJson('/api/operations/printing-quotations', $payload)
            ->assertCreated()
            ->json('data.id');

        $send = $this->asUser($setup['specialist'])
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->assertOk()
            ->json('data');

        $token = $send['public_token'];
        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')->assertOk();

        $quotation = PrintingQuotation::query()->findOrFail($id);

        return $setup + [
            'id' => (int) $id,
            'token' => $token,
            'reference' => (string) $quotation->reference,
        ];
    }

    public function test_payment_capabilities_and_public_paytabs_flag(): void
    {
        $setup = $this->acceptedQuote();

        $this->asUser($setup['specialist'])
            ->getJson('/api/operations/payment-capabilities')
            ->assertOk()
            ->assertJsonPath('data.manual', true)
            ->assertJsonPath('data.paytabs', true);

        $this->getJson('/api/public/printing-quotations/'.$setup['token'])
            ->assertOk()
            ->assertJsonPath('data.paytabs_available', true);

        config(['payments.paytabs.server_key' => '']);

        $this->getJson('/api/public/printing-quotations/'.$setup['token'])
            ->assertOk()
            ->assertJsonPath('data.paytabs_available', false);
    }

    public function test_public_checkout_uses_server_amount_and_ignores_forged_client_amount(): void
    {
        $setup = $this->acceptedQuote('1500.00');

        $created = $this->postJson('/api/public/printing-quotations/'.$setup['token'].'/checkout', [
            'amount' => '1.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.checkout_url', 'https://secure-egypt.paytabs.com/payment/page/hosted-test')
            ->json('data');

        $payment = Payment::query()->findOrFail($created['payment_id']);
        $this->assertSame('1500.00', (string) $payment->amount);
        $this->assertSame(PaymentStatus::Processing, $payment->status);
        $this->assertSame($setup['id'], $payment->printing_quotation_id);
        $this->assertSame($setup['reference'].'-P'.$payment->id, $payment->checkout_session_id);

        Http::assertSent(function (Request $request) use ($setup, $payment): bool {
            if (! str_ends_with($request->url(), '/payment/request')) {
                return false;
            }

            return $request['cart_id'] === $setup['reference'].'-P'.$payment->id
                && $request['cart_amount'] === '1500.00'
                && str_contains((string) $request['cart_description'], $setup['reference']);
        });
    }

    public function test_deposit_policy_checkout_amount(): void
    {
        $setup = $this->acceptedQuote('1000.00', PrintingPaymentPolicy::Deposit->value, '300.00');

        $this->postJson('/api/public/printing-quotations/'.$setup['token'].'/checkout')
            ->assertCreated()
            ->assertJsonPath('data.amount', '300.00');

        $this->assertSame('300.00', (string) Payment::query()->latest('id')->value('amount'));
    }

    public function test_disabled_provider_returns_503(): void
    {
        $setup = $this->acceptedQuote();
        config(['payments.enabled' => false]);

        $this->postJson('/api/public/printing-quotations/'.$setup['token'].'/checkout')
            ->assertStatus(503)
            ->assertJsonPath('message', 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');

        config([
            'payments.enabled' => true,
            'payments.paytabs.server_key' => '',
        ]);

        $this->postJson('/api/public/printing-quotations/'.$setup['token'].'/checkout')
            ->assertStatus(503);

        PaymentSetting::current()->update(['card_enabled' => false]);
        config(['payments.paytabs.server_key' => self::SERVER_KEY]);

        $this->postJson('/api/public/printing-quotations/'.$setup['token'].'/checkout')
            ->assertStatus(422);
    }

    public function test_callback_marks_printing_payment_paid_and_is_idempotent(): void
    {
        $setup = $this->startPublicCheckout('800.00');

        $this->fakeQuery($setup, 'A', '800.00', 'SAR', 154601);
        $payload = $this->callbackPayload($setup, 'A', '800.00');

        $this->postJson('/api/webhooks/paytabs', $payload)
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $this->postJson('/api/webhooks/paytabs', $payload)->assertOk();

        $payment = Payment::query()->findOrFail($setup['id']);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid)->count());
        $this->assertNotNull($payment->paid_at);

        $quotation = PrintingQuotation::query()->findOrFail($setup['quotation_id']);
        $this->assertSame(PrintingQuotationStatus::Accepted, $quotation->status);
    }

    public function test_return_url_does_not_mark_paid(): void
    {
        $setup = $this->startPublicCheckout();

        $this->post('/api/payments/paytabs/return', [
            'tran_ref' => $setup['tran_ref'],
            'cart_id' => $setup['cart_id'],
            'payment_result' => ['response_status' => 'A'],
        ])->assertRedirect('http://localhost:5173/payment/result?payment_id='.$setup['id'].'&ref=printing');

        $this->assertSame(PaymentStatus::Processing, Payment::query()->find($setup['id'])?->status);
        $this->assertNull(Payment::query()->find($setup['id'])?->paid_at);
    }

    public function test_amount_mismatch_does_not_mark_paid(): void
    {
        $setup = $this->startPublicCheckout('1000.00');
        $this->fakeQuery($setup, 'A', '1.00', 'SAR', 154601);

        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($setup, 'A', '1.00'))
            ->assertOk();

        $payment = Payment::query()->findOrFail($setup['id']);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertSame('Payment verification failed.', $payment->failure_reason);
    }

    public function test_public_payment_status_poll_authorized_by_quote_token(): void
    {
        $setup = $this->startPublicCheckout();

        $this->getJson('/api/public/payments/'.$setup['id'].'/status?token='.$setup['token'])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.payment_id', $setup['id'])
            ->assertJsonMissingPath('data.provider_transaction_id');

        $other = $this->acceptedQuote('500.00');
        $this->getJson('/api/public/payments/'.$setup['id'].'/status?token='.$other['token'])
            ->assertStatus(422);
    }

    public function test_reconcile_success_marks_paid(): void
    {
        $setup = $this->startPublicCheckout('900.00');
        $owner = User::factory()->owner()->create();

        $this->fakeQuery($setup, 'A', '900.00', 'SAR', 154601);

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$setup['id'].'/reconcile')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $payment = Payment::query()->findOrFail($setup['id']);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->last_reconciled_at);
        $this->assertSame('reconciled_paid', $payment->reconciliation_note);
    }

    public function test_staff_card_payment_returns_checkout_url(): void
    {
        $setup = $this->acceptedQuote('700.00');

        $this->asUser($setup['specialist'])
            ->postJson('/api/operations/printing-quotations/'.$setup['id'].'/payments', [
                'method' => PaymentMethod::Card->value,
                'amount' => '700.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.checkout_url', 'https://secure-egypt.paytabs.com/payment/page/hosted-test');
    }

    /**
     * @return array{id: int, quotation_id: int, token: string, cart_id: string, tran_ref: string}
     */
    private function startPublicCheckout(string $total = '1000.00'): array
    {
        $this->tranSeq++;
        $this->hostedTranRef = 'TST2412345678'.str_pad((string) $this->tranSeq, 2, '0', STR_PAD_LEFT);

        $setup = $this->acceptedQuote($total);
        $created = $this->postJson('/api/public/printing-quotations/'.$setup['token'].'/checkout')
            ->assertCreated()
            ->json('data');

        return [
            'id' => (int) $created['payment_id'],
            'quotation_id' => $setup['id'],
            'token' => $setup['token'],
            'cart_id' => (string) Payment::query()->findOrFail($created['payment_id'])->checkout_session_id,
            'tran_ref' => $this->hostedTranRef,
        ];
    }

    /**
     * @param  array{id: int, cart_id: string, tran_ref: string}  $setup
     */
    private function fakeQuery(array $setup, string $status, string $amount, string $currency, int $profileId): void
    {
        $this->queryQueue[] = [200, [
            'tran_ref' => $setup['tran_ref'],
            'cart_id' => $setup['cart_id'],
            'cart_amount' => $amount,
            'cart_currency' => $currency,
            'profileId' => $profileId,
            'payment_result' => ['response_status' => $status],
        ]];
    }

    private function fakePayTabsHttp(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/payment/request')) {
                if ($this->requestStatus >= 400) {
                    return Http::response(['message' => 'provider error'], $this->requestStatus);
                }

                return Http::response([
                    'redirect_url' => 'https://secure-egypt.paytabs.com/payment/page/hosted-test',
                    'tran_ref' => $this->hostedTranRef,
                    'cart_id' => $request['cart_id'],
                    'profileId' => 154601,
                ], 200);
            }

            if (str_contains($request->url(), '/payment/query')) {
                $next = array_shift($this->queryQueue);
                if ($next === null) {
                    $next = $this->lastQuery ?? [500, ['message' => 'down']];
                } else {
                    $this->lastQuery = $next;
                }

                return Http::response($next[1], $next[0]);
            }

            return Http::response(['unexpected' => true], 599);
        });
    }

    /**
     * @param  array{id: int, cart_id: string, tran_ref: string}  $setup
     * @return array<string, mixed>
     */
    private function callbackPayload(array $setup, string $status, ?string $amount = null): array
    {
        return [
            'tran_ref' => $setup['tran_ref'],
            'cart_id' => $setup['cart_id'],
            'cart_amount' => $amount ?? '1000.00',
            'cart_currency' => 'SAR',
            'profileId' => 154601,
            'payment_result' => ['response_status' => $status],
        ];
    }
}
