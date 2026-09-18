<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceModuleTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_create_issue_send_and_customer_cannot_see_internal_notes(): void
    {
        Mail::fake();

        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $other = User::factory()->create(['role' => UserRole::Customer->value]);

        $created = $this->asUser($owner)->postJson('/api/operations/invoices', [
            'customer_id' => $customer->id,
            'internal_notes' => 'هامش داخلي سري',
            'notes' => 'ملاحظة للعميل',
            'items' => [
                [
                    'description' => 'خدمة تصميم',
                    'quantity' => 2,
                    'unit_price' => '500.00',
                    'discount_amount' => '0',
                    'tax_amount' => '0',
                ],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame('1000.00', $created['total']);
        $this->assertSame('1000.00', $created['amount_due']);
        $this->assertSame('هامش داخلي سري', $created['internal_notes']);
        $this->assertStringStartsWith('INV-', $created['number']);

        $id = $created['id'];

        $this->asUser($owner)->postJson('/api/operations/invoices/'.$id.'/issue')->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Issued->value);

        $this->asUser($owner)->postJson('/api/operations/invoices/'.$id.'/send')->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Sent->value);

        Mail::assertQueued(InvoiceIssuedMail::class);

        $customerPayload = $this->asUser($customer)->getJson('/api/customer/invoices/'.$id)
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('internal_notes', $customerPayload);
        $this->assertArrayNotHasKey('events', $customerPayload);
        $this->assertSame('1000.00', $customerPayload['total']);
        $this->assertSame('ملاحظة للعميل', $customerPayload['notes']);

        $this->asUser($other)->getJson('/api/customer/invoices/'.$id)->assertNotFound();

        $supplier = User::factory()->create(['role' => UserRole::Supplier->value]);
        $this->asUser($supplier)->getJson('/api/operations/invoices/'.$id)->assertForbidden();
    }

    public function test_record_payment_updates_status_and_rejects_frontend_totals(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);

        $invoice = Invoice::factory()->issued()->create([
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
            'total' => '1000.00',
            'amount_due' => '1000.00',
            'amount_paid' => '0.00',
        ]);
        $invoice->items()->create([
            'description' => 'بند',
            'quantity' => 1,
            'unit_price' => '1000.00',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => '1000.00',
            'sort_order' => 0,
        ]);

        $this->asUser($owner)->postJson('/api/operations/invoices', [
            'customer_id' => $customer->id,
            'number' => 'HACK-1',
            'total' => '1.00',
            'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertUnprocessable();

        $this->asUser($owner)->postJson('/api/operations/invoices/'.$invoice->id.'/payments', [
            'amount' => '400.00',
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference_number' => 'TRX-1',
        ])->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::PartiallyPaid->value)
            ->assertJsonPath('data.amount_paid', '400.00')
            ->assertJsonPath('data.amount_due', '600.00');

        $this->asUser($owner)->postJson('/api/operations/invoices/'.$invoice->id.'/payments', [
            'amount' => '600.00',
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Paid->value)
            ->assertJsonPath('data.amount_due', '0.00');
    }

    public function test_mark_overdue_command(): void
    {
        $invoice = Invoice::factory()->overdue()->create();

        Artisan::call('invoices:mark-overdue');

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_named_invoice_gates_are_registered(): void
    {
        $owner = User::factory()->owner()->create();
        $this->assertTrue($owner->can('invoices.view'));
        $this->assertTrue($owner->can('invoices.create'));
        $this->assertTrue($owner->can('invoices.record_payment'));

        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $this->assertFalse($customer->can('invoices.view'));
    }
}
