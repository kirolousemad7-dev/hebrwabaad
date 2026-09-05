<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCardPaymentGateway;
use Tests\TestCase;

class ManualPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CardPaymentGateway::class, new FakeCardPaymentGateway);

        config([
            'payments.paytabs.profile_id' => 154601,
            'payments.paytabs.server_key' => 'test-server-key',
            'payments.paytabs.base_url' => 'https://secure-egypt.paytabs.com',
        ]);

        User::factory()->accountManager()->create();
    }

    public function test_owner_configures_bank_transfer_and_instapay_accounts(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->patchJson('/api/admin/payments/settings', [
                'bank_transfer_enabled' => true,
                'bank_name' => 'البنك الأهلي',
                'bank_account_name' => 'حبر وأبعاد للدعاية والإعلان',
                'bank_account_number' => '1234567890',
                'bank_iban' => 'SA0380000000608010167519',
                'bank_swift' => 'NCBKSAJE',
                'bank_branch' => 'الرياض - العليا',
                'bank_instructions' => 'حوّل المبلغ ثم أرسل رقم الحوالة.',
                'bank_notes' => 'التحقق خلال يوم عمل.',
                'instapay_enabled' => true,
                'instapay_account_name' => 'حبر وأبعاد',
                'instapay_handle' => 'hebr@instapay',
                'instapay_phone' => '+201000000000',
                'instapay_instructions' => 'حوّل المبلغ ثم أدخل رقم العملية.',
                'instapay_notes' => 'أرسل صورة الإيصال إن توفرت.',
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_transfer_enabled', true)
            ->assertJsonPath('data.bank_transfer_ready', true)
            ->assertJsonPath('data.bank_iban', 'SA0380000000608010167519')
            ->assertJsonPath('data.instapay_ready', true)
            ->assertJsonPath('data.instapay_handle', 'hebr@instapay')
            ->assertJsonPath('data.card_provider', 'PayTabs');

        $settings = PaymentSetting::current();

        $this->assertTrue($settings->bankTransferReady());
        $this->assertTrue($settings->instapayReady());
        $this->assertSame('البنك الأهلي', $settings->bank_name);
    }

    public function test_incomplete_bank_account_is_not_reported_as_ready(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->patchJson('/api/admin/payments/settings', [
                'bank_transfer_enabled' => true,
                'bank_name' => 'البنك الأهلي',
            ])
            ->assertOk()
            ->assertJsonPath('data.bank_transfer_enabled', true)
            ->assertJsonPath('data.bank_transfer_ready', false);

        $this->assertFalse(PaymentSetting::current()->bankTransferReady());
    }

    public function test_owner_settings_never_expose_gateway_secrets(): void
    {
        $owner = User::factory()->owner()->create();

        $body = $this->asUser($owner)
            ->getJson('/api/admin/payments/settings')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('test-server-key', $body);
        $this->assertStringNotContainsString('server_key', $body);
        $this->assertStringNotContainsString('Authorization', $body);
    }

    public function test_only_owner_can_read_or_change_payment_settings(): void
    {
        $customer = User::factory()->create();

        $this->getJson('/api/admin/payments/settings')->assertUnauthorized();
        $this->patchJson('/api/admin/payments/settings', ['bank_transfer_enabled' => true])->assertUnauthorized();

        foreach (['create', 'accountManager', 'hr', 'adminManager', 'webDeveloper'] as $state) {
            $user = $state === 'create'
                ? $customer
                : User::factory()->{$state}()->create();

            $this->asUser($user)
                ->getJson('/api/admin/payments/settings')
                ->assertForbidden();

            $this->asUser($user)
                ->patchJson('/api/admin/payments/settings', [
                    'bank_transfer_enabled' => true,
                    'bank_name' => 'بنك المهاجم',
                ])
                ->assertForbidden();
        }

        $this->assertFalse(PaymentSetting::current()->bank_transfer_enabled);
    }

    public function test_customer_sees_only_enabled_and_configured_manual_accounts(): void
    {
        $customer = User::factory()->create();

        $this->asUser($customer)
            ->getJson('/api/customer/payments/settings')
            ->assertOk()
            ->assertJsonPath('data.bank_transfer.enabled', false)
            ->assertJsonPath('data.bank_transfer.ready', false)
            ->assertJsonPath('data.bank_transfer.bank_name', null)
            ->assertJsonPath('data.instapay.ready', false);

        PaymentSetting::current()->update([
            'bank_transfer_enabled' => true,
            'bank_name' => 'البنك الأهلي',
            'bank_account_name' => 'حبر وأبعاد',
            'bank_iban' => 'SA0380000000608010167519',
            'bank_instructions' => 'حوّل المبلغ ثم أرسل رقم الحوالة.',
        ]);

        $this->asUser($customer)
            ->getJson('/api/customer/payments/settings')
            ->assertOk()
            ->assertJsonPath('data.bank_transfer.ready', true)
            ->assertJsonPath('data.bank_transfer.bank_name', 'البنك الأهلي')
            ->assertJsonPath('data.bank_transfer.iban', 'SA0380000000608010167519');

        PaymentSetting::current()->update(['bank_transfer_enabled' => false]);

        $this->asUser($customer)
            ->getJson('/api/customer/payments/settings')
            ->assertOk()
            ->assertJsonPath('data.bank_transfer.ready', false)
            ->assertJsonPath('data.bank_transfer.iban', null);
    }

    public function test_bank_transfer_cannot_be_used_before_the_owner_configures_it(): void
    {
        $customer = User::factory()->create();
        $order = $this->packageOrder($customer);

        $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'BANK_TRANSFER',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'Bank transfer is not available yet.');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_bank_transfer_runs_through_the_existing_manual_verification_flow(): void
    {
        $this->enableBankTransfer();

        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $order = $this->packageOrder($customer);

        $payment = $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'BANK_TRANSFER',
                'amount' => '1.00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'BANK_TRANSFER')
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.amount', '9000.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.checkout_url', null)
            ->json('data');

        $this->asUser($customer)
            ->postJson('/api/customer/payments/'.$payment['id'].'/manual-transfer', [
                'reference_number' => 'BANK-REF-9001',
                'payer_name' => 'عميل تجريبي',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::PendingVerification->value)
            ->assertJsonPath('data.reference_number', 'BANK-REF-9001');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);

        $this->asUser($owner)
            ->getJson('/api/admin/payments/revenue')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.value', null)
            ->assertJsonPath('data.pending_verification_count', 1);

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$payment['id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.verified_by.id', $owner->id);

        $this->asUser($owner)
            ->getJson('/api/admin/payments/revenue')
            ->assertOk()
            ->assertJsonPath('data.value', 9000)
            ->assertJsonPath('data.paid_count', 1);

        $this->assertNotNull(Payment::query()->findOrFail($payment['id'])->paid_at);
    }

    public function test_owner_can_reject_a_bank_transfer_without_counting_revenue(): void
    {
        $this->enableBankTransfer();

        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $order = $this->packageOrder($customer);

        $payment = $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'BANK_TRANSFER',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($customer)
            ->postJson('/api/customer/payments/'.$payment['id'].'/manual-transfer', [
                'reference_number' => 'BANK-REF-BAD',
            ])
            ->assertOk();

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$payment['id'].'/reject', [
                'reason' => 'لم يصل التحويل إلى الحساب.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Rejected->value)
            ->assertJsonPath('data.failure_reason', 'لم يصل التحويل إلى الحساب.');

        $this->asUser($owner)
            ->getJson('/api/admin/payments/revenue')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.value', null)
            ->assertJsonPath('data.rejected_count', 1);
    }

    public function test_customer_cannot_submit_a_transfer_for_another_customer_payment(): void
    {
        $this->enableBankTransfer();

        $customer = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->packageOrder($customer);

        $payment = $this->asUser($customer)
            ->postJson('/api/customer/payments', [
                'order_id' => $order->id,
                'method' => 'BANK_TRANSFER',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($intruder)
            ->postJson('/api/customer/payments/'.$payment['id'].'/manual-transfer', [
                'reference_number' => 'BANK-REF-STOLEN',
            ])
            ->assertForbidden();

        $this->assertSame(
            PaymentStatus::Pending,
            Payment::query()->findOrFail($payment['id'])->status,
        );
    }

    private function enableBankTransfer(): void
    {
        PaymentSetting::current()->update([
            'bank_transfer_enabled' => true,
            'bank_name' => 'البنك الأهلي',
            'bank_account_name' => 'حبر وأبعاد',
            'bank_account_number' => '1234567890',
            'bank_instructions' => 'حوّل المبلغ ثم أرسل رقم الحوالة.',
        ]);
    }

    private function packageOrder(User $customer): Order
    {
        $package = Package::factory()->create([
            'slug' => 'foundation-package',
            'price' => '9000.00',
            'discount_amount' => '0.00',
            'currency' => 'SAR',
            'is_active' => true,
        ]);

        $id = $this->asUser($customer)
            ->postJson('/api/customer/orders', ['package_slug' => $package->slug])
            ->assertCreated()
            ->json('data.id');

        return Order::query()->findOrFail($id);
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }
}
