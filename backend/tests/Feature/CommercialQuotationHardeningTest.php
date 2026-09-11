<?php

namespace Tests\Feature;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\QuoteRequestStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\Payment;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommercialQuotationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{owner: User, customer: User, request: QuoteRequest, quotation: CommercialQuotation, token: string}
     */
    private function sentQuotation(array $overrides = []): array
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuoteRequestStatus::UnderReview,
        ]);

        $created = $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated();
        $qid = (int) $created->json('data.id');

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, array_merge([
            'payment_policy' => PrintingPaymentPolicy::Full->value,
            'valid_until' => now()->addDays(7)->toDateString(),
            'tax_amount' => '0.00',
            'items' => [
                ['description' => 'خدمة أساسية', 'quantity' => 1, 'unit_price' => '1000.00', 'category' => 'OTHER'],
            ],
        ], $overrides))->assertOk();

        Notification::fake();

        $token = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid.'/send')
            ->assertOk()
            ->json('data.public_token');

        return [
            'owner' => $owner,
            'customer' => $customer,
            'request' => $request->fresh(),
            'quotation' => CommercialQuotation::query()->findOrFail($qid),
            'token' => $token,
        ];
    }

    public function test_pdf_for_sent_quotation_excludes_internal_notes(): void
    {
        ['owner' => $owner, 'quotation' => $quotation, 'token' => $token] = $this->sentQuotation([
            'notes' => 'ملاحظات للعميل',
            'internal_notes' => 'تكلفة داخلية سرية 999',
            'terms' => 'شروط واضحة',
        ]);

        $staffPdf = $this->asUser($owner)
            ->get('/api/operations/commercial-quotations/'.$quotation->id.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('حبر وأبعاد', $staffPdf);
        $this->assertStringContainsString((string) $quotation->reference, $staffPdf);
        $this->assertStringContainsString('ملاحظات للعميل', $staffPdf);
        $this->assertStringContainsString('1000.00', $staffPdf);
        $this->assertStringNotContainsString('تكلفة داخلية سرية 999', $staffPdf);
        $this->assertStringNotContainsString('internal_notes', $staffPdf);

        $publicPdf = $this->get('/api/public/commercial-quotations/'.$token.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('عرض سعر', $publicPdf);
        $this->assertStringNotContainsString('تكلفة داخلية سرية 999', $publicPdf);
    }

    public function test_accepted_snapshot_pdf_uses_frozen_total(): void
    {
        ['token' => $token, 'quotation' => $quotation] = $this->sentQuotation();

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertOk();

        $quotation->refresh();
        $this->assertNotNull($quotation->snapshot);
        $this->assertSame('1000.00', (string) ($quotation->snapshot['total'] ?? ''));

        // Mutating live row must not change accepted PDF totals.
        $quotation->update(['total' => '9999.00', 'subtotal' => '9999.00']);

        $html = $this->get('/api/public/commercial-quotations/'.$token.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('1000.00', $html);
        $this->assertStringNotContainsString('9999.00', $html);
    }

    public function test_draft_pdf_not_publicly_available_via_random_token(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create(['customer_id' => $customer->id]);
        $created = $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated();
        $qid = (int) $created->json('data.id');

        $this->get('/api/public/commercial-quotations/'.str_repeat('z', 64).'/pdf')
            ->assertStatus(422);

        $this->asUser($owner)
            ->get('/api/operations/commercial-quotations/'.$qid.'/pdf?format=html')
            ->assertOk();
    }

    public function test_public_payload_includes_revision_history_and_expiry(): void
    {
        ['owner' => $owner, 'token' => $token, 'quotation' => $quotation] = $this->sentQuotation([
            'valid_until' => now()->addDays(2)->toDateString(),
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$token.'/revision', [
            'reason' => 'السعر مرتفع',
            'reason_code' => 'price',
        ])->assertOk();

        $revised = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$quotation->id.'/revise')
            ->assertCreated();
        $qid2 = (int) $revised->json('data.id');

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid2, [
            'payment_policy' => PrintingPaymentPolicy::None->value,
            'valid_until' => now()->addDays(5)->toDateString(),
            'items' => [
                ['description' => 'خدمة أساسية', 'quantity' => 1, 'unit_price' => '900.00', 'category' => 'OTHER'],
                ['description' => 'إضافة', 'quantity' => 1, 'unit_price' => '100.00', 'category' => 'OTHER'],
            ],
        ])->assertOk();

        $token2 = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid2.'/send')
            ->assertOk()
            ->json('data.public_token');

        $payload = $this->getJson('/api/public/commercial-quotations/'.$token2)
            ->assertOk()
            ->json('data');

        $this->assertGreaterThanOrEqual(2, count($payload['revisions'] ?? []));
        $this->assertTrue(collect($payload['revisions'])->contains(fn ($r) => ($r['is_current'] ?? false) === true));
        $this->assertNotNull($payload['revision_diff'] ?? null);
        $this->assertSame('1000.00', (string) ($payload['revision_diff']['previous_total'] ?? ''));
        $this->assertContains('إضافة', $payload['revision_diff']['items_added'] ?? []);
        $this->assertTrue($payload['can_accept'] ?? false);
        $this->assertFalse($payload['can_checkout'] ?? true);
    }

    public function test_notification_deep_link_uses_cq_path(): void
    {
        Notification::fake();
        ['customer' => $customer] = $this->sentQuotation();

        Notification::assertSentTo($customer, QuoteNotification::class, function (QuoteNotification $notification) use ($customer): bool {
            $data = $notification->toArray($customer);

            return str_starts_with((string) ($data['action_url'] ?? ''), '/cq/')
                && str_starts_with((string) ($data['href'] ?? ''), '/cq/');
        });
    }

    public function test_owner_and_customer_notification_api_deep_links(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuoteRequestStatus::UnderReview,
        ]);

        $customer->notify(new QuoteNotification([
            'title' => 'طلب تسعير جديد',
            'message' => 'test',
            'href' => '/dashboard/quote-requests/'.$request->id,
            'action_url' => '/dashboard/quote-requests/'.$request->id,
            'type' => 'quote_request',
        ]));
        $owner->notify(new QuoteNotification([
            'title' => 'طلب تسعير جديد',
            'message' => 'test',
            'href' => '/owner/quote-requests/'.$request->id,
            'action_url' => '/owner/quote-requests/'.$request->id,
            'type' => 'quote_request',
        ]));

        $this->asUser($owner)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.href', '/owner/quote-requests/'.$request->id);

        $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.href', '/dashboard/quote-requests/'.$request->id);

        $publicToken = 'test-cq-token-'.uniqid();
        $customer->notify(new QuoteNotification([
            'title' => 'عرض سعر',
            'message' => 'أرسل',
            'href' => '/cq/'.$publicToken,
            'action_url' => '/cq/'.$publicToken,
            'type' => 'commercial_quotation_sent',
        ]));

        $this->asUser($customer)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.href', '/cq/'.$publicToken);
    }

    public function test_processing_and_paid_payment_states_gate_checkout(): void
    {
        ['token' => $token, 'quotation' => $quotation, 'customer' => $customer] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::Full->value,
        ]);
        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertOk();

        Payment::factory()->create([
            'order_id' => null,
            'customer_id' => $customer->id,
            'commercial_quotation_id' => $quotation->id,
            'amount' => '1000.00',
            'status' => PaymentStatus::Processing,
        ]);

        $processing = $this->getJson('/api/public/commercial-quotations/'.$token)->assertOk()->json('data');
        $this->assertSame('PROCESSING', $processing['latest_payment_status']);
        $this->assertFalse($processing['can_checkout'] ?? true);

        Payment::query()
            ->where('commercial_quotation_id', $quotation->id)
            ->update([
                'status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
            ]);

        $paid = $this->getJson('/api/public/commercial-quotations/'.$token)->assertOk()->json('data');
        $this->assertSame('PAID', $paid['latest_payment_status']);
        $this->assertFalse($paid['can_checkout'] ?? true);
        $this->assertTrue($paid['payment_summary']['requirement_met'] ?? false);
    }

    public function test_customer_payload_hides_internal_notes_on_request(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'internal_notes' => 'هامش ربح داخلي',
        ]);

        $this->asUser($owner)
            ->patchJson('/api/operations/quote-requests/'.$request->id, [
                'internal_notes' => 'هامش ربح داخلي',
            ])
            ->assertOk();

        $payload = $this->asUser($customer)
            ->getJson('/api/customer/quote-requests/'.$request->id)
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('internal_notes', $payload);
        $encoded = json_encode($payload);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('هامش ربح داخلي', $encoded);
    }

    public function test_double_accept_and_expired_cannot_accept(): void
    {
        ['token' => $token] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::None->value,
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertOk();
        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', CommercialQuotationStatus::Accepted->value);

        ['token' => $expiredToken, 'quotation' => $q] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::None->value,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        // Force expiry date on sent row
        $q->update(['valid_until' => now()->subDay()->toDateString()]);

        $this->postJson('/api/public/commercial-quotations/'.$expiredToken.'/accept')->assertUnprocessable();
    }

    public function test_accept_does_not_create_order_or_payment(): void
    {
        ['token' => $token, 'quotation' => $quotation, 'request' => $request] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::Deposit->value,
            'deposit_required' => '250.00',
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertOk();

        $this->assertDatabaseMissing('payments', [
            'commercial_quotation_id' => $quotation->id,
        ]);
        $this->assertSame($request->order_id, $quotation->fresh()->order_id);
        $this->assertDatabaseHas('quote_requests', [
            'id' => $request->id,
            'status' => QuoteRequestStatus::Accepted->value,
        ]);
    }

    public function test_public_payload_never_includes_internal_notes(): void
    {
        ['owner' => $owner, 'token' => $token, 'quotation' => $quotation] = $this->sentQuotation();
        $quotation->update(['internal_notes' => 'سر داخلي']);

        $public = $this->getJson('/api/public/commercial-quotations/'.$token)->assertOk()->json('data');
        $this->assertArrayNotHasKey('internal_notes', $public);

        $staff = $this->asUser($owner)
            ->getJson('/api/operations/commercial-quotations/'.$quotation->id)
            ->assertOk()
            ->json('data');
        $this->assertSame('سر داخلي', $staff['internal_notes'] ?? null);
    }

    public function test_authenticated_customer_pdf_and_other_customer_blocked(): void
    {
        ['customer' => $customer, 'quotation' => $quotation, 'token' => $token] = $this->sentQuotation([
            'notes' => 'ملاحظات للعميل فقط',
            'internal_notes' => 'لا يظهر للعميل',
        ]);

        $ownerPdf = $this->asUser(User::factory()->owner()->create())
            ->get('/api/operations/commercial-quotations/'.$quotation->id.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $publicPdf = $this->get('/api/public/commercial-quotations/'.$token.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $customerPdf = $this->asUser($customer)
            ->get('/api/customer/commercial-quotations/'.$quotation->id.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        foreach ([$ownerPdf, $publicPdf, $customerPdf] as $html) {
            $this->assertStringContainsString('1000.00', $html);
            $this->assertStringContainsString((string) $quotation->reference, $html);
            $this->assertStringContainsString('ملاحظات للعميل فقط', $html);
            $this->assertStringNotContainsString('لا يظهر للعميل', $html);
        }

        $other = User::factory()->create(['role' => UserRole::Customer->value]);
        $this->asUser($other)
            ->get('/api/customer/commercial-quotations/'.$quotation->id.'/pdf')
            ->assertNotFound();
    }

    public function test_payment_cta_hidden_when_paytabs_unavailable(): void
    {
        ['token' => $token] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::Full->value,
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertOk();

        $payload = $this->getJson('/api/public/commercial-quotations/'.$token)->assertOk()->json('data');

        $this->assertTrue($payload['payment_required'] ?? false);
        $this->assertFalse($payload['can_checkout'] ?? true);
        $this->assertFalse($payload['paytabs_configured'] ?? true);
        $this->assertNotEmpty($payload['card_unavailable_message'] ?? null);
        $this->assertStringContainsString('الدفع الإلكتروني غير متاح', (string) $payload['card_unavailable_message']);
        $this->assertSame('FULL', $payload['payment_policy']);
        $this->assertSame('1000.00', (string) ($payload['payment_summary']['amount_due_now'] ?? ''));
    }

    public function test_deposit_and_none_payment_cta_rules(): void
    {
        ['token' => $depositToken] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::Deposit->value,
            'deposit_required' => '250.00',
        ]);
        $this->postJson('/api/public/commercial-quotations/'.$depositToken.'/accept')->assertOk();
        $deposit = $this->getJson('/api/public/commercial-quotations/'.$depositToken)->assertOk()->json('data');
        $this->assertTrue($deposit['payment_required'] ?? false);
        $this->assertFalse($deposit['can_checkout'] ?? true);
        $this->assertSame('250.00', (string) ($deposit['payment_summary']['amount_due_now'] ?? ''));

        ['token' => $noneToken] = $this->sentQuotation([
            'payment_policy' => PrintingPaymentPolicy::None->value,
        ]);
        $this->postJson('/api/public/commercial-quotations/'.$noneToken.'/accept')->assertOk();
        $none = $this->getJson('/api/public/commercial-quotations/'.$noneToken)->assertOk()->json('data');
        $this->assertFalse($none['payment_required'] ?? true);
        $this->assertFalse($none['can_checkout'] ?? true);
        $this->assertTrue(empty($none['card_unavailable_message']));
    }
}
