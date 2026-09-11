<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\CustomerPortalAccess;
use App\Models\Payment;
use App\Models\PrintingQuotation;
use App\Models\PrintingQuotationEvent;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalPhase8Test extends TestCase
{
    use RefreshDatabase;

    public function test_portal_show_includes_summary_timeline_and_documents(): void
    {
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::InProgress,
        ]);
        $sent = PrintingQuotation::factory()->sent()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'status' => PrintingQuotationStatus::Sent,
        ]);
        $accepted = PrintingQuotation::factory()->accepted()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
            'payment_policy' => PrintingPaymentPolicy::Full,
            'total' => '100.00',
        ]);
        Payment::factory()->paid()->create([
            'customer_id' => $customer->id,
            'printing_quotation_id' => $accepted->id,
            'order_id' => null,
            'amount' => '100.00',
            'status' => PaymentStatus::Paid,
        ]);

        PrintingQuotationEvent::query()->create([
            'printing_quotation_id' => $accepted->id,
            'event' => 'accepted',
            'actor_id' => null,
            'actor_type' => 'customer',
            'meta' => null,
        ]);
        PrintingQuotationEvent::query()->create([
            'printing_quotation_id' => $accepted->id,
            'event' => 'payment_recorded',
            'actor_id' => null,
            'actor_type' => 'system',
            'meta' => ['amount' => '100.00', 'currency' => 'SAR'],
        ]);

        $raw = str_repeat('p', 64);
        CustomerPortalAccess::query()->create([
            'customer_id' => $customer->id,
            'token_hash' => CustomerPortalAccess::hashToken($raw),
            'token_hint' => substr($raw, -8),
            'expires_at' => now()->addDay(),
            'created_by' => null,
        ]);

        $response = $this->getJson('/api/public/portal/'.$raw)->assertOk();

        $response->assertJsonPath('data.summary.awaiting_quote_response', 1);
        $response->assertJsonStructure([
            'data' => [
                'summary' => [
                    'awaiting_quote_response',
                    'payments_due',
                    'approvals_due',
                    'in_progress',
                    'ready_for_pickup',
                ],
                'timeline',
                'documents' => [
                    'quotes',
                    'receipts',
                ],
                'support_href',
            ],
        ]);

        $this->assertSame(1, (int) $response->json('data.summary.in_progress'));
        $this->assertNull($response->json('data.support_href'));
        $this->assertNotEmpty($response->json('data.timeline'));
        $this->assertSame($accepted->id, $response->json('data.documents.quotes.0.quotation_id'));
        $this->assertStringContainsString(
            '/api/public/portal/'.$raw.'/documents/quotations/'.$accepted->id.'/pdf',
            (string) $response->json('data.documents.quotes.0.path'),
        );
        $this->assertSame($sent->id, $response->json('data.quotations.1.id')
            ?? $response->json('data.quotations.0.id'));
    }

    public function test_portal_timeline_is_bounded_to_thirty(): void
    {
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create(['user_id' => $customer->id]);
        $quotation = PrintingQuotation::factory()->accepted()->create([
            'printing_request_id' => $request->id,
            'customer_id' => $customer->id,
        ]);

        for ($i = 0; $i < 35; $i++) {
            PrintingQuotationEvent::query()->create([
                'printing_quotation_id' => $quotation->id,
                'event' => 'status_changed',
                'actor_id' => null,
                'actor_type' => 'system',
                'meta' => ['status_key' => 'IN_PROGRESS', 'status_label' => 'جاري التنفيذ'],
            ]);
        }

        $raw = str_repeat('t', 64);
        CustomerPortalAccess::query()->create([
            'customer_id' => $customer->id,
            'token_hash' => CustomerPortalAccess::hashToken($raw),
            'token_hint' => substr($raw, -8),
            'expires_at' => now()->addDay(),
        ]);

        $timeline = $this->getJson('/api/public/portal/'.$raw)
            ->assertOk()
            ->json('data.timeline');

        $this->assertCount(30, $timeline);
    }
}
