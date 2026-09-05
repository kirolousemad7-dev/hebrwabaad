<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCardPaymentGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private FakeCardPaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeCardPaymentGateway;
        $this->app->instance(CardPaymentGateway::class, $this->gateway);

        PaymentSetting::current()->update([
            'card_enabled' => true,
            'instapay_enabled' => true,
            'instapay_account_name' => 'حبر وأبعاد',
            'instapay_bank_name' => 'البنك الأهلي',
            'instapay_account_number' => 'SA1111222233334444555566',
            'instapay_instructions' => 'حوّل المبلغ ثم أدخل رقم العملية.',
        ]);
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
    private function payableOrder(?User $customer = null, string $price = '1500.00'): array
    {
        $customer ??= User::factory()->create();
        $package = Package::factory()->create([
            'price' => $price,
            'discount_amount' => 0,
            'currency' => 'SAR',
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'title' => 'باقة تأسيس',
        ]);

        return compact('customer', 'order', 'package');
    }

    /**
     * @return list<UserRole>
     */
    private function staffDeniedRoles(): array
    {
        return [
            UserRole::AdminManager,
            UserRole::AccountManager,
            UserRole::Hr,
            UserRole::WebDeveloper,
            UserRole::GraphicDesigner,
            UserRole::VideoEditor,
            UserRole::MarketingSpecialist,
            UserRole::EventSpecialist,
            UserRole::PrintingSpecialist,
            UserRole::MediaBuyer,
        ];
    }

    public function test_employees_cannot_use_customer_payment_endpoints(): void
    {
        $developer = User::factory()->webDeveloper()->create();

        $this->asUser($developer)->getJson('/api/customer/payments')->assertForbidden();
        $this->asUser($developer)
            ->postJson('/api/customer/payments', ['order_id' => 1, 'method' => 'CARD'])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_payment_apis(): void
    {
        $this->getJson('/api/customer/payments')->assertUnauthorized();
        $this->postJson('/api/customer/payments', ['order_id' => 1, 'method' => 'CARD'])->assertUnauthorized();
        $this->getJson('/api/admin/payments')->assertUnauthorized();
        $this->getJson('/api/admin/payments/revenue')->assertUnauthorized();
        $this->patchJson('/api/admin/payments/settings', [])->assertUnauthorized();
    }

    public function test_inactive_users_cannot_access_payment_apis(): void
    {
        $customer = User::factory()->inactive()->create();
        $owner = User::factory()->owner()->inactive()->create();

        $this->asUser($customer)->getJson('/api/customer/payments')->assertForbidden();
        $this->asUser($owner)->getJson('/api/admin/payments')->assertForbidden();
    }

    public function test_non_owner_staff_cannot_access_payment_administration(): void
    {
        foreach ($this->staffDeniedRoles() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->asUser($user)
                ->getJson('/api/admin/payments')
                ->assertForbidden()
                ->assertJsonPath('message', 'Forbidden.');

            $this->asUser($user)
                ->getJson('/api/admin/payments/revenue')
                ->assertForbidden();

            $this->asUser($user)
                ->getJson('/api/admin/payments/settings')
                ->assertForbidden();
        }
    }

    public function test_customer_cannot_access_owner_payment_administration(): void
    {
        $setup = $this->payableOrder();
        $customer = $setup['customer'];
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'order_id' => $setup['order']->id,
            'status' => PaymentStatus::PendingVerification,
            'payment_method' => PaymentMethod::Instapay,
            'provider' => PaymentMethod::Instapay->provider(),
        ]);

        $this->asUser($customer)->getJson('/api/admin/payments')->assertForbidden();
        $this->asUser($customer)->postJson('/api/admin/payments/'.$payment->id.'/verify')->assertForbidden();
    }

    public function test_owner_can_list_payments_and_empty_state_has_no_fake_revenue(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->getJson('/api/admin/payments')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.summary.available', false)
            ->assertJsonPath('data.summary.value', null)
            ->assertJsonPath('data.summary.reason', 'no_recorded_revenue');
    }

    public function test_customer_cannot_view_or_pay_another_customers_order(): void
    {
        $alice = $this->payableOrder();
        $bob = User::factory()->create();

        $this->asUser($bob)
            ->postJson('/api/customer/payments', [
                'order_id' => $alice['order']->id,
                'method' => 'INSTAPAY',
                'amount' => '10.00',
            ])
            ->assertForbidden();

        $this->asUser($alice['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $alice['order']->id,
                'method' => 'INSTAPAY',
            ])
            ->assertCreated();

        $payment = Payment::query()->firstOrFail();

        $this->asUser($bob)
            ->getJson('/api/customer/payments/'.$payment->id)
            ->assertForbidden();

        $this->asUser($bob)
            ->postJson('/api/customer/payments/'.$payment->id.'/manual-transfer', [
                'reference_number' => 'stolen-ref',
            ])
            ->assertForbidden();

        $this->asUser($alice['customer'])
            ->getJson('/api/customer/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($bob)
            ->getJson('/api/customer/payments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_frontend_cannot_override_amount_currency_or_status(): void
    {
        $setup = $this->payableOrder(price: '1500.00');

        $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'INSTAPAY',
                'amount' => '10.00',
                'currency' => 'EGP',
                'status' => 'PAID',
                'customer_id' => 999,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.payment_method', PaymentMethod::Instapay->value);

        $this->assertDatabaseHas('payments', [
            'order_id' => $setup['order']->id,
            'amount' => '1500.00',
            'currency' => 'SAR',
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_instapay_pending_verification_owner_approve_and_reject(): void
    {
        $setup = $this->payableOrder();
        $owner = User::factory()->owner()->create();
        $manager = User::factory()->accountManager()->create();

        $created = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'INSTAPAY',
            ])
            ->assertCreated()
            ->json('data');

        $paymentId = $created['id'];

        $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments/'.$paymentId.'/manual-transfer', [
                'reference_number' => 'IP-998877',
                'payer_name' => 'منى',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::PendingVerification->value)
            ->assertJsonPath('data.reference_number', 'IP-998877');

        $this->asUser($manager)
            ->postJson('/api/admin/payments/'.$paymentId.'/verify')
            ->assertForbidden();

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$paymentId.'/reject', [])
            ->assertStatus(422);

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$paymentId.'/reject', [
                'reason' => 'رقم العملية غير موجود',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Rejected->value)
            ->assertJsonPath('data.failure_reason', 'رقم العملية غير موجود');

        $retryOrder = Order::factory()->create([
            'customer_id' => $setup['customer']->id,
            'package_id' => $setup['package']->id,
        ]);

        $second = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $retryOrder->id,
                'method' => 'INSTAPAY',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments/'.$second['id'].'/manual-transfer', [
                'reference_number' => 'IP-112233',
            ])
            ->assertOk();

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$second['id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->assertNotNull(Payment::query()->find($second['id'])?->paid_at);
    }

    public function test_card_checkout_requires_configured_gateway_and_never_marks_paid_locally(): void
    {
        $this->gateway->configured = false;
        $setup = $this->payableOrder();

        $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'الدفع بالبطاقة غير متاح حاليًا، برجاء المحاولة لاحقًا.');

        $this->gateway->configured = true;

        $created = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.provider', 'paytabs')
            ->assertJsonPath('data.checkout_url', 'https://secure-egypt.paytabs.com/payment/page/test_1')
            ->json('data');

        $this->assertSame(PaymentStatus::Processing, Payment::query()->find($created['id'])?->status);
        $this->assertSame('TST_FAKE_'.$created['id'], Payment::query()->find($created['id'])?->provider_transaction_id);
        $this->assertNull(Payment::query()->find($created['id'])?->paid_at);
    }

    public function test_revenue_counts_only_distinct_paid_payments(): void
    {
        $owner = User::factory()->owner()->create();
        $setup = $this->payableOrder();

        Payment::factory()->create([
            'customer_id' => $setup['customer']->id,
            'order_id' => $setup['order']->id,
            'amount' => '1500.00',
            'currency' => 'SAR',
            'status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'paid_at' => now(),
        ]);
        Payment::factory()->create([
            'customer_id' => $setup['customer']->id,
            'order_id' => $setup['order']->id,
            'amount' => '900.00',
            'currency' => 'SAR',
            'status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Instapay,
        ]);
        Payment::factory()->create([
            'customer_id' => $setup['customer']->id,
            'order_id' => $setup['order']->id,
            'amount' => '400.00',
            'currency' => 'SAR',
            'status' => PaymentStatus::Failed,
            'payment_method' => PaymentMethod::Card,
        ]);

        $this->asUser($owner)
            ->getJson('/api/admin/payments/revenue')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.value', 1500)
            ->assertJsonPath('data.paid_count', 1)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.failed_count', 1);
    }

    public function test_payment_json_does_not_expose_secrets_or_card_data(): void
    {
        $setup = $this->payableOrder();
        $owner = User::factory()->owner()->create();

        $created = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->json('data');

        $content = json_encode($created);
        $this->assertStringNotContainsString('sk_live', (string) $content);
        $this->assertStringNotContainsString('PAYTABS_SERVER_KEY', (string) $content);
        $this->assertStringNotContainsString('hebr-test-paytabs-server-key', (string) $content);
        $this->assertArrayNotHasKey('card_number', $created);
        $this->assertArrayNotHasKey('cvv', $created);
        $this->assertArrayNotHasKey('otp', $created);
        $this->assertArrayNotHasKey('server_key', $created);

        $ownerPayload = $this->asUser($owner)
            ->getJson('/api/admin/payments/'.$created['id'])
            ->assertOk()
            ->json('data');

        $ownerJson = json_encode($ownerPayload);
        $this->assertStringNotContainsString('PAYTABS_SERVER_KEY', (string) $ownerJson);
        $this->assertArrayNotHasKey('card_number', $ownerPayload);
        $this->assertArrayNotHasKey('server_key', $ownerPayload);
    }

    public function test_double_submit_reuses_open_card_payment(): void
    {
        $setup = $this->payableOrder();

        $first = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->json('data');

        $second = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'CARD',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, Payment::query()->count());
    }
}
