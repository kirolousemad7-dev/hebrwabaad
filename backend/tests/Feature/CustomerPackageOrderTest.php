<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeCardPaymentGateway;
use Tests\TestCase;

class CustomerPackageOrderTest extends TestCase
{
    use RefreshDatabase;

    private FakeCardPaymentGateway $cards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cards = new FakeCardPaymentGateway;
        $this->app->instance(CardPaymentGateway::class, $this->cards);

        config([
            'payments.paytabs.profile_id' => 154601,
            'payments.paytabs.server_key' => 'test-server-key',
            'payments.paytabs.base_url' => 'https://secure-egypt.paytabs.com',
        ]);

        PaymentSetting::current()->update([
            'card_enabled' => true,
            'instapay_enabled' => true,
            'instapay_account_name' => 'حبر وأبعاد',
            'instapay_account_number' => 'TEST-INSTAPAY',
            'instapay_instructions' => 'حوّل المبلغ ثم أدخل رقم العملية.',
        ]);

        User::factory()->accountManager()->create();
    }

    public function test_customer_creates_real_package_order_with_server_side_price_and_currency(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $package = $this->package();

        $created = $this->asUser($customer)
            ->postJson('/api/customer/orders', [
                'package_slug' => $package->slug,
                'amount' => '1.00',
                'currency' => 'EGP',
                'customer_id' => $otherCustomer->id,
                'payment_status' => 'PAID',
            ])
            ->assertCreated()
            ->assertJsonPath('data.package.id', $package->id)
            ->assertJsonPath('data.package.name', $package->name)
            ->assertJsonPath('data.payable.amount', '9000.00')
            ->assertJsonPath('data.payable.currency', 'SAR')
            ->assertJsonPath('data.payable.available', true)
            ->assertJsonPath('data.latest_payment', null)
            ->assertJsonPath('data.reused', false)
            ->json('data');

        $order = Order::query()->findOrFail($created['id']);

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($package->id, $order->package_id);
        $this->assertSame(OrderStatus::Received, $order->status);
        $this->assertMatchesRegularExpression('/^HEBR-ORD-\d{6}$/', $order->reference);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::Received->value,
            'changed_by' => $customer->id,
        ]);

        $this->asUser($customer)
            ->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.orders.0.id', $order->id);
    }

    public function test_repeated_package_cta_reuses_pending_unpaid_order(): void
    {
        $customer = User::factory()->create();
        $package = $this->package();

        $first = $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertCreated()
            ->json('data');

        $second = $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertOk()
            ->assertJsonPath('data.reused', true)
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, Order::query()->where('customer_id', $customer->id)->where('package_id', $package->id)->count());
    }

    public function test_guest_inactive_customer_and_non_customer_cannot_create_package_order(): void
    {
        $package = $this->package();

        $this->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertUnauthorized();

        $inactive = User::factory()->inactive()->create();
        $this->asUser($inactive)
            ->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertForbidden();

        $owner = User::factory()->owner()->create();
        $this->asUser($owner)
            ->postJson('/api/customer/orders', [
                'package_slug' => $package->slug,
                'customer_id' => User::factory()->create()->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_inactive_or_unknown_package_is_not_ordered(): void
    {
        $customer = User::factory()->create();
        $inactive = $this->package(['is_active' => false]);

        $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => $inactive->slug])
            ->assertUnprocessable()
            ->assertJsonPath('errors.package_slug.0', 'هذه الباقة غير متاحة حاليًا.');

        $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => 'missing-package'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.package_slug.0', 'هذه الباقة غير متاحة حاليًا.');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_created_package_order_uses_existing_card_payment_and_verified_paytabs_callback(): void
    {
        $customer = User::factory()->create();
        $owner = User::factory()->owner()->create();
        $package = $this->package();
        $order = $this->createPackageOrder($customer, $package);

        $payment = $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'CARD',
                'amount' => '1.00',
                'currency' => 'EGP',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '9000.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.provider', 'paytabs')
            ->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->json('data');

        $stored = Payment::query()->findOrFail($payment['id']);

        Http::fake([
            'https://secure-egypt.paytabs.com/payment/query' => Http::response([
                'tran_ref' => $stored->provider_transaction_id,
                'cart_id' => $stored->checkout_session_id,
                'cart_amount' => '9000.00',
                'cart_currency' => 'SAR',
                'profileId' => 154601,
                'payment_result' => ['response_status' => 'A'],
            ]),
        ]);

        $this->postJson('/api/webhooks/paytabs', [
            'tran_ref' => $stored->provider_transaction_id,
            'cart_id' => $stored->checkout_session_id,
        ])->assertOk();

        $this->assertSame(PaymentStatus::Paid, $stored->fresh()->status);

        $this->asUser($customer)
            ->getJson('/api/customer/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.latest_payment.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.payable.available', false);

        $this->asUser($owner)
            ->getJson('/api/admin/payments')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $payment['id'])
            ->assertJsonPath('data.summary.value', 9000);
    }

    public function test_customer_cannot_pay_another_customers_package_order(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createPackageOrder($customer, $this->package());

        $this->asUser($other)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'CARD',
            ])
            ->assertForbidden();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_created_package_order_keeps_existing_instapay_flow(): void
    {
        $customer = User::factory()->create();
        $order = $this->createPackageOrder($customer, $this->package());

        $payment = $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'INSTAPAY',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '9000.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->json('data');

        $this->asUser($customer)
            ->postJson('/api/customer/payments/'.$payment['id'].'/manual-transfer', [
                'reference_number' => 'IP-PACKAGE-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::PendingVerification->value);
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
     * @param  array<string, mixed>  $attributes
     */
    private function package(array $attributes = []): Package
    {
        return Package::factory()->create([
            'name' => 'الباقة التأسيسية',
            'slug' => 'foundation-package',
            'price' => '9000.00',
            'discount_amount' => '0.00',
            'currency' => 'SAR',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function createPackageOrder(User $customer, Package $package): Order
    {
        $id = $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertCreated()
            ->json('data.id');

        return Order::query()->findOrFail($id);
    }
}
