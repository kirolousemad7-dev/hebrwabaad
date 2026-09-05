<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Notifications\OwnerPaymentReceivedNotification;
use App\Notifications\PaymentPaidNotification;
use App\Services\Payments\CardPaymentGateway;
use App\Services\Payments\PayTabsCheckoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayTabsPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'hebr-test-paytabs-server-key';

    private int $tranSeq = 0;

    /**
     * @var list<string>
     */
    private array $logLines = [];

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

        $this->logLines = [];
        $this->queryQueue = [];
        $this->lastQuery = null;
        $this->requestStatus = 200;
        $this->hostedTranRef = 'TST241234567890';
        $this->fakePayTabsHttp();
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logLines[] = $event->message.' '.json_encode($event->context, JSON_UNESCAPED_UNICODE);
        });
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

    /**
     * @return array{customer: User, order: Order, package: Package}
     */
    private function payableOrder(?User $customer = null, string $price = '1500.00', string $currency = 'SAR'): array
    {
        $customer ??= User::factory()->create();
        $package = Package::factory()->create([
            'price' => $price,
            'discount_amount' => 0,
            'currency' => $currency,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'title' => 'باقة تأسيس',
        ]);

        return compact('customer', 'order', 'package');
    }

    public function test_missing_paytabs_credentials_fail_gracefully_without_secrets(): void
    {
        config(['payments.paytabs.server_key' => '']);
        $setup = $this->payableOrder();

        $response = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');

        $this->assertStringNotContainsString(self::SERVER_KEY, (string) $response->getContent());
        $this->assertLogsDoNotContainSecrets();
        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Paid)->count());
    }

    public function test_card_checkout_sends_paytabs_hosted_page_payload_and_returns_redirect_url(): void
    {
        $setup = $this->payableOrder();

        $created = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
                'amount' => '1.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.provider', 'paytabs')
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.checkout_url', 'https://secure-egypt.paytabs.com/payment/page/hosted-test')
            ->assertJsonPath('data.provider_transaction_id', 'TST241234567890')
            ->json('data');

        $this->assertArrayNotHasKey('card_number', $created);
        $this->assertStringNotContainsString(self::SERVER_KEY, (string) json_encode($created));
        $this->assertNull(Payment::query()->find($created['id'])?->paid_at);

        Http::assertSent(function (Request $request) use ($setup, $created): bool {
            if (! str_ends_with($request->url(), '/payment/request')) {
                return false;
            }

            $expectedCartId = $setup['order']->reference.'-P'.$created['id'];

            $auth = $request->header('Authorization');
            $authValue = is_array($auth) ? (string) ($auth[0] ?? '') : (string) $auth;

            return $request['profile_id'] === 154601
                && $request['tran_type'] === 'sale'
                && $request['tran_class'] === 'ecom'
                && $request['cart_id'] === $expectedCartId
                && $request['cart_currency'] === 'SAR'
                && $request['cart_amount'] === '1500.00'
                && $request['payment_methods'] === ['creditcard']
                && $request['callback'] === 'http://127.0.0.1:8000/api/webhooks/paytabs'
                && $request['return'] === 'http://127.0.0.1:8000/api/payments/paytabs/return'
                && $authValue === self::SERVER_KEY;
        });

        $payment = Payment::query()->findOrFail($created['id']);
        $this->assertSame($setup['order']->reference.'-P'.$created['id'], $payment->checkout_session_id);
        $this->assertSame('TST241234567890', $payment->provider_transaction_id);
        $this->assertLogsDoNotContainSecrets();
    }

    public function test_paytabs_api_failure_does_not_mark_paid(): void
    {
        $this->requestStatus = 500;

        $setup = $this->payableOrder();

        $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');

        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Paid)->count());
        $this->assertLogsDoNotContainSecrets();
    }

    public function test_successful_callback_is_verified_marks_paid_and_is_idempotent(): void
    {
        Notification::fake();
        $setup = $this->startCardPayment();
        $owner = User::factory()->owner()->create();

        $this->fakeQuery($setup, 'A', '1500.00', 'SAR', 154601);

        $payload = $this->callbackPayload($setup, 'A', '1500.00');

        $this->postJson('/api/webhooks/paytabs', $payload)
            ->assertOk()
            ->assertJsonPath('data.received', true)
            ->assertJsonMissingPath('data.server_key');

        $this->postJson('/api/webhooks/paytabs', $payload)->assertOk();

        $payment = Payment::query()->findOrFail($setup['id']);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid)->count());
        $this->assertNotNull($payment->paid_at);
        $this->assertSame($setup['tran_ref'], $payment->provider_transaction_id);

        Notification::assertSentTo($setup['customer'], PaymentPaidNotification::class);
        Notification::assertSentTo($owner, OwnerPaymentReceivedNotification::class);
        $this->assertSame(1, Notification::sent($setup['customer'], PaymentPaidNotification::class)->count());
        $this->assertSame(1, Notification::sent($owner, OwnerPaymentReceivedNotification::class)->count());

        $this->asUser($owner)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.overview.revenue.available', true)
            ->assertJsonPath('data.overview.revenue.value', 1500)
            ->assertJsonPath('data.overview.revenue.secondary.paid_count', 1);

        $this->assertLogsDoNotContainSecrets();
    }

    public function test_failed_and_cancelled_callbacks_map_to_existing_statuses(): void
    {
        $failed = $this->startCardPayment();
        $this->fakeQuery($failed, 'D', '1500.00', 'SAR', 154601);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($failed, 'D'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, Payment::query()->find($failed['id'])?->status);

        $cancelled = $this->startCardPayment();
        $this->fakeQuery($cancelled, 'C', '1500.00', 'SAR', 154601);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($cancelled, 'C'))->assertOk();
        $this->assertSame(PaymentStatus::Cancelled, Payment::query()->find($cancelled['id'])?->status);

        $pending = $this->startCardPayment();
        $this->fakeQuery($pending, 'P', '1500.00', 'SAR', 154601);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($pending, 'P'))->assertOk();
        $this->assertSame(PaymentStatus::Processing, Payment::query()->find($pending['id'])?->status);
        $this->assertNull(Payment::query()->find($pending['id'])?->paid_at);
    }

    public function test_amount_currency_and_profile_mismatches_do_not_mark_paid(): void
    {
        $amount = $this->startCardPayment();
        $this->fakeQuery($amount, 'A', '1.00', 'SAR', 154601);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($amount, 'A', '1.00'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, Payment::query()->find($amount['id'])?->status);
        $this->assertNull(Payment::query()->find($amount['id'])?->paid_at);

        $currency = $this->startCardPayment();
        $this->fakeQuery($currency, 'A', '1500.00', 'EGP', 154601);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($currency, 'A'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, Payment::query()->find($currency['id'])?->status);

        $profile = $this->startCardPayment();
        $this->fakeQuery($profile, 'A', '1500.00', 'SAR', 999999);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($profile, 'A'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, Payment::query()->find($profile['id'])?->status);

        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Paid)->count());
        $this->assertLogsDoNotContainSecrets();
    }

    public function test_unknown_transaction_does_not_create_payment_and_query_outage_returns_503(): void
    {
        $this->queryQueue = [
            [200, [
                'tran_ref' => 'TST-UNKNOWN',
                'cart_id' => 'MISSING-P999',
                'cart_amount' => '1500.00',
                'cart_currency' => 'SAR',
                'profileId' => 154601,
                'payment_result' => ['response_status' => 'A'],
            ]],
            [500, ['message' => 'down']],
        ];

        $this->postJson('/api/webhooks/paytabs', [
            'tran_ref' => 'TST-UNKNOWN',
            'cart_id' => 'MISSING-P999',
        ])->assertOk();

        $this->assertSame(0, Payment::query()->count());

        $this->postJson('/api/webhooks/paytabs', ['tran_ref' => 'TST-DOWN'])
            ->assertStatus(503);
    }

    public function test_customer_cannot_pay_another_customers_order_or_access_owner_apis(): void
    {
        $alice = $this->payableOrder();
        $bob = User::factory()->create();

        $this->asUser($bob)
            ->postJson('/api/customer/payments', [
                'order_id' => $alice['order']->id,
                'method' => 'CARD',
                'amount' => '1.00',
            ])
            ->assertForbidden();

        $created = $this->asUser($alice['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $alice['order']->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($bob)
            ->postJson('/api/customer/payments/'.$created['id'].'/card')
            ->assertForbidden();

        $this->asUser($bob)->getJson('/api/customer/payments/'.$created['id'])->assertForbidden();
        $this->asUser($bob)->getJson('/api/admin/payments')->assertForbidden();
        $this->asUser($alice['customer'])->getJson('/api/admin/payments')->assertForbidden();
        $this->asUser($alice['customer'])->postJson('/api/admin/payments/'.$created['id'].'/verify')->assertForbidden();
    }

    public function test_staff_roles_cannot_access_owner_payment_administration(): void
    {
        $roles = [
            UserRole::AdminManager,
            UserRole::AccountManager,
            UserRole::Hr,
            UserRole::GraphicDesigner,
            UserRole::WebDeveloper,
            UserRole::MediaBuyer,
            UserRole::VideoEditor,
            UserRole::PrintingSpecialist,
            UserRole::MarketingSpecialist,
            UserRole::EventSpecialist,
        ];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->asUser($user)->getJson('/api/admin/payments')->assertForbidden();
            $this->asUser($user)->getJson('/api/admin/payments/revenue')->assertForbidden();
        }
    }

    public function test_inactive_customer_cannot_pay_and_paid_payment_cannot_be_charged_again(): void
    {
        $inactive = User::factory()->inactive()->create();
        $setup = $this->payableOrder($inactive);

        $this->asUser($inactive)
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertForbidden();

        Notification::fake();
        $paid = $this->startCardPayment();
        $this->fakeQuery($paid, 'A', '1500.00', 'SAR', 154601);
        $this->postJson('/api/webhooks/paytabs', $this->callbackPayload($paid, 'A'))->assertOk();

        $this->asUser($paid['customer'])
            ->postJson('/api/customer/payments/'.$paid['id'].'/card')
            ->assertStatus(422);

        $this->asUser($paid['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $paid['order']->id,
                'method' => 'CARD',
            ])
            ->assertStatus(422);

        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid)->count());
        $this->assertSame(1, Notification::sent($paid['customer'], PaymentPaidNotification::class)->count());
    }

    public function test_browser_return_does_not_mark_paid(): void
    {
        $setup = $this->startCardPayment();

        $this->post('/api/payments/paytabs/return', [
            'tran_ref' => $setup['tran_ref'],
            'cart_id' => $setup['cart_id'],
            'payment_result' => ['response_status' => 'A'],
        ])->assertRedirect('http://localhost:5173/dashboard/orders/'.$setup['order']->id.'/pay?payment='.$setup['id'].'&checkout=return');

        $this->assertSame(PaymentStatus::Processing, Payment::query()->find($setup['id'])?->status);
        $this->assertNull(Payment::query()->find($setup['id'])?->paid_at);
    }

    public function test_webhook_is_public_and_does_not_expose_secrets(): void
    {
        $this->postJson('/api/webhooks/paytabs', ['note' => 'empty'])
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $this->getJson('/api/webhooks/stripe')->assertNotFound();
        $this->assertLogsDoNotContainSecrets();
    }

    /**
     * @return array{id: int, customer: User, order: Order, cart_id: string, tran_ref: string}
     */
    private function startCardPayment(): array
    {
        $this->tranSeq++;
        $this->hostedTranRef = 'TST2412345678'.str_pad((string) $this->tranSeq, 2, '0', STR_PAD_LEFT);

        $setup = $this->payableOrder();
        $created = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->json('data');

        $this->flushHeaders();

        return [
            'id' => $created['id'],
            'customer' => $setup['customer'],
            'order' => $setup['order'],
            'cart_id' => (string) Payment::query()->findOrFail($created['id'])->checkout_session_id,
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
            'cart_amount' => $amount ?? '1500.00',
            'cart_currency' => 'SAR',
            'profileId' => 154601,
            'payment_result' => ['response_status' => $status],
        ];
    }

    private function assertLogsDoNotContainSecrets(): void
    {
        foreach ($this->logLines as $line) {
            $this->assertStringNotContainsString(self::SERVER_KEY, $line);
            $this->assertStringNotContainsString('Authorization', $line);
        }
    }
}
