<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentSetting;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInsightsPhase8Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_payment_insights_and_funnel_smoke_for_owner(): void
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
            'total' => '150.00',
            'subtotal' => '150.00',
            'payment_policy' => PrintingPaymentPolicy::Full,
            'accepted_at' => now()->subDay(),
            'status' => PrintingQuotationStatus::Accepted,
        ]);
        Payment::factory()->paid()->create([
            'customer_id' => $customer->id,
            'order_id' => null,
            'printing_quotation_id' => $quotation->id,
            'amount' => '150.00',
            'currency' => 'SAR',
            'payment_method' => PaymentMethod::Card,
            'paid_at' => now()->subHours(2),
        ]);

        $this->asUser($owner)
            ->getJson('/api/operations/insights/payments?period=30')
            ->assertOk()
            ->assertJsonPath('data.successful', 1)
            ->assertJsonStructure([
                'data' => [
                    'successful',
                    'failed_attempts',
                    'pending',
                    'refund_total',
                    'method_split',
                    'avg_confirm_hours',
                ],
            ]);

        $this->asUser($owner)
            ->getJson('/api/operations/insights/payment-funnel?period=30')
            ->assertOk()
            ->assertJsonPath('data.counts.accepted', 1)
            ->assertJsonStructure([
                'data' => [
                    'counts' => [
                        'accepted',
                        'checkout_created',
                        'confirmed',
                        'eligible',
                        'in_production',
                    ],
                ],
            ]);
    }

    public function test_command_center_revenue_includes_phase8_counters(): void
    {
        $owner = User::factory()->owner()->create();
        PaymentSetting::current();

        Payment::factory()->create([
            'status' => PaymentStatus::PendingVerification,
            'payment_method' => PaymentMethod::Instapay,
            'amount' => '40.00',
        ]);
        Payment::factory()->create([
            'status' => PaymentStatus::Processing,
            'payment_method' => PaymentMethod::Card,
            'amount' => '55.00',
        ]);
        Payment::factory()->create([
            'status' => PaymentStatus::Failed,
            'payment_method' => PaymentMethod::Card,
            'amount' => '10.00',
        ]);

        $paid = Payment::factory()->paid()->create(['amount' => '100.00']);
        PaymentRefund::query()->create([
            'payment_id' => $paid->id,
            'provider' => 'manual',
            'amount' => '25.00',
            'currency' => 'SAR',
            'status' => PaymentRefundStatus::Confirmed,
            'is_manual' => true,
            'requested_by' => $owner->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $this->asUser($owner)
            ->getJson('/api/operations/command-center')
            ->assertOk()
            ->assertJsonPath('data.revenue.pending_review', 1)
            ->assertJsonPath('data.revenue.pending_processing', 1)
            ->assertJsonPath('data.revenue.failed_payments', 1)
            ->assertJsonPath('data.revenue.refunds_confirmed_count', 1)
            ->assertJsonPath('data.revenue.reconciliation_problems', 1);
    }

    public function test_holiday_import_preview_and_copy_year(): void
    {
        $owner = User::factory()->owner()->create();
        $calendar = BusinessCalendar::query()->create([
            'name' => 'Gulf',
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'working_days' => [0, 1, 2, 3, 4],
            'work_start' => '09:00:00',
            'work_end' => '17:00:00',
            'is_default' => true,
            'is_active' => true,
        ]);

        $csv = "date,name\n2026-01-01,New Year\n2026-01-01,Dup\nbad-date,Broken\n";

        $this->asUser($owner)
            ->postJson('/api/operations/business-calendars/'.$calendar->id.'/holidays/import', [
                'preview' => true,
                'csv' => $csv,
            ])
            ->assertOk()
            ->assertJsonPath('data.preview', true)
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.valid.0.date', '2026-01-01');

        $this->assertDatabaseCount('business_calendar_holidays', 0);

        $this->asUser($owner)
            ->postJson('/api/operations/business-calendars/'.$calendar->id.'/holidays/import', [
                'preview' => false,
                'csv' => "2026-09-23,National Day\n",
            ])
            ->assertCreated()
            ->assertJsonPath('data.imported', 1);

        BusinessCalendarHoliday::query()->create([
            'business_calendar_id' => $calendar->id,
            'date' => '2025-09-23',
            'name' => 'National Day 2025',
        ]);

        $this->asUser($owner)
            ->postJson('/api/operations/business-calendars/'.$calendar->id.'/holidays/copy-year', [
                'from_year' => 2025,
                'to_year' => 2027,
            ])
            ->assertOk()
            ->assertJsonPath('data.copied', 1);

        $this->assertTrue(
            BusinessCalendarHoliday::query()
                ->where('business_calendar_id', $calendar->id)
                ->whereDate('date', '2027-09-23')
                ->exists()
        );
    }

    public function test_customer_cannot_access_payment_insights(): void
    {
        $customer = User::factory()->create();

        $this->asUser($customer)
            ->getJson('/api/operations/insights/payments?period=7')
            ->assertForbidden();
    }
}
