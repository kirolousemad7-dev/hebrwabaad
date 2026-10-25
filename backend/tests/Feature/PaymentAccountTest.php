<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentVerificationEvent;
use App\Models\User;
use App\Services\Payments\CardPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeCardPaymentGateway;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(CardPaymentGateway::class, new FakeCardPaymentGateway);
        Storage::fake('local');
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
     * @return array{customer: User, order: Order}
     */
    private function payableOrder(): array
    {
        $customer = User::factory()->create();
        $package = Package::factory()->create([
            'price' => '1500.00',
            'discount_amount' => 0,
            'currency' => 'SAR',
        ]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'title' => 'باقة تأسيس',
        ]);

        return compact('customer', 'order');
    }

    public function test_owner_manages_multiple_receiving_accounts_and_customers_only_see_active_ones(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $staff = User::factory()->accountManager()->create();

        $this->asUser($customer)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'INSTAPAY',
                'account_holder_name' => 'حبر',
                'instapay_address' => 'hebr@instapay',
                'instructions' => 'حوّل ثم أرسل الإثبات.',
            ])
            ->assertForbidden();

        $this->asUser($staff)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'INSTAPAY',
                'account_holder_name' => 'حبر',
                'instapay_address' => 'hebr@instapay',
                'instructions' => 'حوّل ثم أرسل الإثبات.',
            ])
            ->assertForbidden();

        $instapay = $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'INSTAPAY',
                'account_holder_name' => 'حبر وأبعاد',
                'instapay_address' => 'hebr@instapay',
                'mobile_number' => '0579434114',
                'instructions' => 'حوّل المبلغ ثم ارفع صورة الإثبات.',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.method', 'INSTAPAY')
            ->assertJsonPath('data.is_default', true)
            ->json('data');

        $second = $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'INSTAPAY',
                'account_holder_name' => 'حساب ثان',
                'instapay_address' => 'second@instapay',
                'instructions' => 'حساب بديل.',
            ])
            ->assertCreated()
            ->json('data');

        $bank = $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'BANK_TRANSFER',
                'account_holder_name' => 'حبر وأبعاد',
                'bank_name' => 'البنك الأهلي',
                'account_number' => '1234567890',
                'iban' => 'SA0380000000608010167519',
                'branch_name' => 'العليا',
                'instructions' => 'أرفق صورة الحوالة.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->json('data');

        $this->asUser($owner)
            ->patchJson('/api/admin/payment-accounts/'.$bank['id'], [
                'branch_name' => 'الرياض',
            ])
            ->assertOk()
            ->assertJsonPath('data.branch_name', 'الرياض');

        $this->asUser($owner)
            ->patchJson('/api/admin/payment-accounts/'.$second['id'], [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $hidden = $this->asUser($customer)
            ->getJson('/api/customer/payments/settings')
            ->assertOk()
            ->json('data.accounts');

        $this->assertCount(2, $hidden);
        $this->assertFalse(collect($hidden)->contains(fn (array $row): bool => $row['id'] === $second['id']));

        $this->asUser($owner)
            ->patchJson('/api/admin/payment-accounts/'.$second['id'], [
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts/'.$second['id'].'/default')
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse(PaymentAccount::query()->find($instapay['id'])?->is_default);

        $visible = $this->asUser($customer)
            ->getJson('/api/customer/payments/settings')
            ->assertOk()
            ->json('data');

        $this->assertTrue($visible['instapay']['ready']);
        $this->assertTrue($visible['bank_transfer']['ready']);
        $this->assertSame('second@instapay', $visible['instapay']['handle']);
    }

    public function test_manual_proof_is_required_private_and_verified_without_double_fulfillment(): void
    {
        $owner = User::factory()->owner()->create();
        $setup = $this->payableOrder();
        $other = User::factory()->create();

        $account = $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'INSTAPAY',
                'account_holder_name' => 'حبر وأبعاد',
                'instapay_address' => 'hebr@instapay',
                'instructions' => 'ارفع صورة الإيصال.',
            ])
            ->assertCreated()
            ->json('data');

        $created = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'INSTAPAY',
                'amount' => '1.00',
                'currency' => 'USD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->json('data');

        $this->asUser($setup['customer'])
            ->post('/api/customer/payments/'.$created['id'].'/manual-transfer', [
                'reference_number' => 'IP-1',
                'payment_account_id' => $account['id'],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof']);

        $this->asUser($setup['customer'])
            ->post('/api/customer/payments/'.$created['id'].'/manual-transfer', [
                'reference_number' => 'IP-1',
                'payment_account_id' => $account['id'],
                'proof' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof']);

        $submitted = $this->asUser($setup['customer'])
            ->post('/api/customer/payments/'.$created['id'].'/manual-transfer', [
                'reference_number' => 'IP-1',
                'payment_account_id' => $account['id'],
                'proof' => UploadedFile::fake()->image('proof.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::PendingVerification->value)
            ->assertJsonPath('data.has_proof', true)
            ->json('data');

        $payment = Payment::query()->findOrFail($created['id']);
        $this->assertSame(PaymentStatus::PendingVerification, $payment->status);
        $this->assertSame('1500.00', $payment->amount);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame(0, Payment::query()->where('order_id', $setup['order']->id)->where('status', PaymentStatus::Paid->value)->count());

        $this->asUser($setup['customer'])
            ->post('/api/customer/payments/'.$created['id'].'/manual-transfer', [
                'reference_number' => 'IP-2',
                'proof' => UploadedFile::fake()->image('again.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->asUser($other)
            ->get('/api/customer/payments/'.$created['id'].'/proof')
            ->assertForbidden();

        $this->asUser($setup['customer'])
            ->get('/api/customer/payments/'.$created['id'].'/proof')
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private');

        $this->asUser($owner)
            ->getJson('/api/operations/command-center')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_manual_payments', 1);

        $this->asUser($other)
            ->postJson('/api/admin/payments/'.$created['id'].'/verify')
            ->assertForbidden();

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$created['id'].'/reject', [
                'reason' => 'الصورة غير واضحة',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Rejected->value)
            ->assertJsonPath('data.failure_reason', 'الصورة غير واضحة');

        $this->assertSame(0, Payment::query()->where('order_id', $setup['order']->id)->where('status', PaymentStatus::Paid->value)->count());

        $this->asUser($setup['customer'])
            ->post('/api/customer/payments/'.$created['id'].'/manual-transfer', [
                'reference_number' => 'IP-3',
                'payment_account_id' => $account['id'],
                'proof' => UploadedFile::fake()->image('proof.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::PendingVerification->value);

        $this->assertDatabaseHas('payment_verification_events', [
            'payment_id' => $created['id'],
            'action' => 'resubmitted',
        ]);

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$created['id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $paidAt = Payment::query()->findOrFail($created['id'])->paid_at;
        $this->assertNotNull($paidAt);

        $this->asUser($owner)
            ->postJson('/api/admin/payments/'.$created['id'].'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->assertSame(1, PaymentVerificationEvent::query()->where('payment_id', $created['id'])->where('action', 'approved')->count());
        $this->assertTrue($paidAt->equalTo(Payment::query()->findOrFail($created['id'])->paid_at));
        $this->assertSame(1, Payment::query()->where('order_id', $setup['order']->id)->where('status', PaymentStatus::Paid->value)->count());
        $this->assertSame($account['id'], $submitted['receiving_account']['id'] ?? $payment->fresh()->payment_account_id);
    }

    public function test_archiving_an_account_keeps_historical_payment_link(): void
    {
        $owner = User::factory()->owner()->create();
        $setup = $this->payableOrder();

        $account = $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts', [
                'method' => 'BANK_TRANSFER',
                'account_holder_name' => 'حبر وأبعاد',
                'bank_name' => 'الراجحي',
                'iban' => 'SA0380000000608010167519',
                'instructions' => 'حوّل ثم ارفع الإثبات.',
            ])
            ->assertCreated()
            ->json('data');

        $payment = $this->asUser($setup['customer'])
            ->postJson('/api/customer/payments', [
                'order_id' => $setup['order']->id,
                'method' => 'BANK_TRANSFER',
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($setup['customer'])
            ->post('/api/customer/payments/'.$payment['id'].'/manual-transfer', [
                'reference_number' => 'BANK-1',
                'payment_account_id' => $account['id'],
                'proof' => UploadedFile::fake()->image('proof.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->asUser($owner)
            ->postJson('/api/admin/payment-accounts/'.$account['id'].'/archive')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertNotNull(PaymentAccount::query()->find($account['id'])?->archived_at);
        $this->assertSame($account['id'], Payment::query()->find($payment['id'])?->payment_account_id);

        $accounts = $this->asUser($setup['customer'])
            ->getJson('/api/customer/payments/settings')
            ->assertOk()
            ->json('data.accounts');

        $this->assertSame([], $accounts);
    }
}
