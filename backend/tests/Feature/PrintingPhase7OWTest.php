<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\Payment;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingPhase7OWTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_printing_funnel_returns_stages_for_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '500.00',
            'status' => PrintingRequestStatus::Pending,
        ]);

        PrintingQuotation::factory()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'status' => PrintingQuotationStatus::Sent,
            'sent_at' => now()->subHours(4),
            'viewed_at' => now()->subHours(3),
            'total' => '500.00',
            'subtotal' => '500.00',
            'payment_policy' => PrintingPaymentPolicy::Full,
        ]);

        $payload = $this->asUser($owner)
            ->getJson('/api/operations/insights/printing-funnel?period=30')
            ->assertOk()
            ->json('data');

        $this->assertSame(30, $payload['period_days']);
        $this->assertIsArray($payload['stages']);
        $keys = collect($payload['stages'])->pluck('key')->all();
        $this->assertSame(
            ['sent', 'viewed', 'accepted', 'payment_met', 'in_production', 'delivered'],
            $keys,
        );
        $this->assertGreaterThanOrEqual(1, (int) $payload['stages'][0]['count']);
    }

    public function test_payments_reconciliation_lists_processing_and_failed_cards(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::Pending,
        ]);
        $quotation = PrintingQuotation::factory()->accepted()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'total' => '100.00',
            'subtotal' => '100.00',
            'payment_policy' => PrintingPaymentPolicy::Full,
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'printing_quotation_id' => $quotation->id,
            'order_id' => null,
            'amount' => '100.00',
            'currency' => 'SAR',
            'payment_method' => PaymentMethod::Card,
            'status' => PaymentStatus::Processing,
            'provider' => 'paytabs',
            'failure_reason' => 'server_key=hebr-secret-should-hide',
        ]);

        Payment::factory()->create([
            'customer_id' => $customer->id,
            'printing_quotation_id' => $quotation->id,
            'order_id' => null,
            'amount' => '50.00',
            'currency' => 'SAR',
            'payment_method' => PaymentMethod::Card,
            'status' => PaymentStatus::Failed,
            'provider' => 'paytabs',
            'reconciliation_note' => 'mismatch:amount',
        ]);

        $payload = $this->asUser($owner)
            ->getJson('/api/operations/payments/reconciliation')
            ->assertOk()
            ->json('data');

        $this->assertGreaterThanOrEqual(2, $payload['meta']['total']);
        $reasons = collect($payload['items'])->pluck('failure_reason')->filter()->implode(' ');
        $this->assertStringNotContainsString('hebr-secret-should-hide', $reasons);
        $this->assertTrue(
            collect($payload['items'])->contains(fn (array $row): bool => $row['status'] === PaymentStatus::Processing->value)
        );
    }

    public function test_command_center_revenue_includes_phase7_counts(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::ReadyForDelivery,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '200.00',
        ]);

        PrintingQuotation::factory()->accepted()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'total' => '200.00',
            'subtotal' => '200.00',
            'payment_policy' => PrintingPaymentPolicy::None,
        ]);

        $revenue = $this->asUser($owner)
            ->getJson('/api/operations/command-center')
            ->assertOk()
            ->json('data.revenue');

        $this->assertArrayHasKey('awaiting_payment', $revenue);
        $this->assertArrayHasKey('payments_processing', $revenue);
        $this->assertArrayHasKey('reconcile_attention', $revenue);
        $this->assertArrayHasKey('execution_eligible', $revenue);
        $this->assertSame(1, $revenue['ready_for_delivery']);
        $this->assertSame(1, $revenue['execution_eligible']);
    }

    public function test_operations_settings_includes_capability_flags(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)
            ->getJson('/api/operations/settings')
            ->assertOk()
            ->assertJsonPath('data.delivery_modes.0', 'pickup')
            ->assertJsonPath('data.delivery_modes.1', 'manual_delivery')
            ->assertJsonStructure(['data' => ['paytabs_available', 'mail_enabled']]);
    }

    public function test_health_check_reports_paytabs_and_processing_without_external_calls(): void
    {
        config([
            'payments.paytabs.profile_id' => 1,
            'payments.paytabs.server_key' => 'test-key',
            'payments.paytabs.base_url' => 'https://secure-egypt.paytabs.com',
        ]);

        $this->artisan('operations:health-check')
            ->expectsOutputToContain('Health check complete.')
            ->assertSuccessful();
    }
}
