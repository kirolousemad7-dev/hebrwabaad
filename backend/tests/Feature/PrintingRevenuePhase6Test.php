<?php

namespace Tests\Feature;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingRevenuePhase6Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_printing_insights_returns_200_with_amounts_for_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '800.00',
            'status' => PrintingRequestStatus::Pending,
        ]);

        PrintingQuotation::factory()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'status' => PrintingQuotationStatus::Sent,
            'sent_at' => now()->subHours(5),
            'total' => '800.00',
            'subtotal' => '800.00',
            'payment_policy' => PrintingPaymentPolicy::Full,
        ]);

        PrintingQuotation::factory()->accepted()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'sent_at' => now()->subHours(10),
            'accepted_at' => now()->subHours(2),
            'total' => '800.00',
            'subtotal' => '800.00',
            'payment_policy' => PrintingPaymentPolicy::None,
        ]);

        $this->asUser($owner)
            ->getJson('/api/operations/insights/printing?period=30')
            ->assertOk()
            ->assertJsonPath('data.period_days', 30)
            ->assertJsonPath('data.include_amounts', true)
            ->assertJsonPath('data.quotations_sent', 2)
            ->assertJsonPath('data.accepted_count', 1)
            ->assertJsonPath('data.accepted_value', '800.00');
    }

    public function test_command_center_includes_revenue_section_and_hides_amounts_for_account_manager(): void
    {
        $owner = User::factory()->owner()->create();
        $am = User::factory()->accountManager()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::ReadyForDelivery,
        ]);

        PrintingQuotation::factory()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'status' => PrintingQuotationStatus::Sent,
            'sent_at' => now(),
            'valid_until' => now()->addDay()->toDateString(),
            'total' => '1200.00',
            'subtotal' => '1200.00',
        ]);

        $ownerPayload = $this->asUser($owner)
            ->getJson('/api/operations/command-center')
            ->assertOk()
            ->json('data.revenue');

        $this->assertNotNull($ownerPayload);
        $this->assertSame(1, $ownerPayload['quotes_awaiting_customer']);
        $this->assertTrue($ownerPayload['include_amounts']);
        $this->assertNotNull($ownerPayload['amounts']);
        $this->assertSame('1200.00', $ownerPayload['amounts']['quotes_awaiting_customer_value']);
        $this->assertSame(1, $ownerPayload['ready_for_delivery']);

        $amPayload = $this->asUser($am)
            ->getJson('/api/operations/command-center')
            ->assertOk()
            ->json('data.revenue');

        $this->assertNotNull($amPayload);
        $this->assertSame(1, $amPayload['quotes_awaiting_customer']);
        $this->assertFalse($amPayload['include_amounts']);
        $this->assertNull($amPayload['amounts']);

        $amInsights = $this->asUser($am)
            ->getJson('/api/operations/insights/printing?period=7')
            ->assertOk()
            ->json('data');

        $this->assertFalse($amInsights['include_amounts']);
        $this->assertNull($amInsights['accepted_value']);
        $this->assertNull($amInsights['payments_received_sum']);
    }

    public function test_export_printing_quotations_csv_escapes_formula_and_includes_reference(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['name' => '=HACK']);
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'product_name' => 'Banner',
        ]);

        PrintingQuotation::factory()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'reference' => 'PQ-2026-0099',
            'status' => PrintingQuotationStatus::Sent,
            'sent_at' => now(),
            'total' => '50.00',
        ]);

        $response = $this->asUser($owner)->get('/api/operations/export/printing-quotations.csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('PQ-2026-0099', $body);
        $this->assertStringContainsString("'=HACK", $body);
        $this->assertStringNotContainsString("\n=HACK", $body);
    }

    public function test_send_returns_public_path_for_share(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '100.00',
            'status' => PrintingRequestStatus::Pending,
            'assigned_to' => $specialist->id,
        ]);

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->json('data.id');

        $send = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->assertOk();

        $token = $send->json('data.public_token');
        $this->assertIsString($token);
        $send->assertJsonPath('data.public_path', '/q/'.$token);
        $this->assertStringContainsString('/q/'.$token, (string) $send->json('data.public_url'));
    }

    public function test_track_viewed_recorded_first_only(): void
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '100.00',
            'assigned_to' => $specialist->id,
        ]);

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $tracking = $this->postJson('/api/public/printing-quotations/'.$token.'/accept')
            ->assertOk()
            ->json('data.tracking_token');

        $this->getJson('/api/public/printing-track/'.$tracking)->assertOk();
        $this->getJson('/api/public/printing-track/'.$tracking)->assertOk();

        $this->assertSame(
            1,
            PrintingQuotationEvent::query()
                ->where('printing_quotation_id', $id)
                ->where('event', 'track_viewed')
                ->count(),
        );
    }

    public function test_customer_printing_history_ok_for_owner(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'product_name' => 'Sticker sheet',
        ]);

        PrintingQuotation::factory()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'status' => PrintingQuotationStatus::Sent,
            'sent_at' => now(),
            'total' => '75.00',
        ]);

        $this->asUser($owner)
            ->getJson('/api/operations/customers/'.$customer->id.'/printing-history')
            ->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.printing_requests.0.product_name', 'Sticker sheet')
            ->assertJsonPath('data.quotations.0.total', '75.00');
    }
}
