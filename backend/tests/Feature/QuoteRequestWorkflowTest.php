<?php

namespace Tests\Feature;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\QuoteRequestSource;
use App\Enums\QuoteRequestStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationItem;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuoteRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_customer_can_create_service_quote_request(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $owner = User::factory()->owner()->create();

        $response = $this->asUser($customer)
            ->postJson('/api/customer/quote-requests', [
                'source_type' => QuoteRequestSource::Service->value,
                'source_id' => 12,
                'title' => 'طلب تسعير استراتيجية',
                'city' => 'جدة',
                'required_date' => now()->addDays(10)->toDateString(),
                'budget_min' => 1000,
                'budget_max' => 5000,
                'customer_notes' => 'نحتاج عرض سعر',
                'payload' => [
                    'line_items' => [
                        [
                            'description' => 'استراتيجية تسويقية',
                            'quantity' => 1,
                            'unit_price' => '0.00',
                            'category' => 'CREATIVE',
                        ],
                    ],
                ],
                'idempotency_key' => 'svc-12-a',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', QuoteRequestStatus::New->value);

        $this->assertStringStartsWith('QR-'.now()->format('Y').'-', (string) $response->json('data.reference'));
        $this->assertDatabaseHas('quote_requests', [
            'customer_id' => $customer->id,
            'source_type' => QuoteRequestSource::Service->value,
            'source_id' => 12,
        ]);
    }

    public function test_duplicate_open_request_is_blocked(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'source_type' => QuoteRequestSource::Package->value,
            'source_id' => 5,
            'status' => QuoteRequestStatus::UnderReview,
        ]);

        $this->asUser($customer)
            ->postJson('/api/customer/quote-requests', [
                'source_type' => QuoteRequestSource::Package->value,
                'source_id' => 5,
                'title' => 'باقة تسويق',
            ])
            ->assertUnprocessable();
    }

    public function test_custom_package_quote_request_includes_selected_services(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);

        $this->asUser($customer)
            ->postJson('/api/customer/quote-requests', [
                'source_type' => QuoteRequestSource::CustomPackage->value,
                'title' => 'صمّم باقتك',
                'payload' => [
                    'line_items' => [
                        ['description' => 'Strategy', 'quantity' => 1, 'unit_price' => '3500.00', 'category' => 'CREATIVE'],
                        ['description' => 'Reels', 'quantity' => 8, 'unit_price' => '0.00', 'category' => 'PRODUCTION'],
                    ],
                ],
                'idempotency_key' => 'custom-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payload.line_items.1.description', 'Reels');
    }

    public function test_event_quote_request_stores_event_payload(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);

        $this->asUser($customer)
            ->postJson('/api/customer/quote-requests', [
                'source_type' => QuoteRequestSource::EventRequest->value,
                'source_id' => 9,
                'title' => 'فعالية مؤتمر',
                'city' => 'الرياض',
                'payload' => [
                    'event' => [
                        'event_type' => 'conference',
                        'attendance' => 200,
                        'buy_or_rent' => 'rent',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.payload.event.attendance', 200);
    }

    public function test_printing_source_is_accepted_but_does_not_create_commercial_quotation(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $owner = User::factory()->owner()->create();

        $created = $this->asUser($customer)
            ->postJson('/api/customer/quote-requests', [
                'source_type' => QuoteRequestSource::PrintingRequest->value,
                'source_id' => 44,
                'title' => 'طباعة كروت',
            ])
            ->assertCreated()
            ->assertJsonPath('data.quotation_type', 'PRINTING');

        $id = (int) $created->json('data.id');

        $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$id.'/quotations')
            ->assertStatus(422)
            ->assertJsonPath('errors.quotation_type', 'PRINTING');
    }

    public function test_owner_sees_inbox_and_employee_is_blocked(): void
    {
        $owner = User::factory()->owner()->create();
        $employee = User::factory()->create(['role' => UserRole::GraphicDesigner->value]);
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        QuoteRequest::factory()->create(['customer_id' => $customer->id]);

        $this->asUser($owner)
            ->getJson('/api/operations/quote-requests')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->asUser($employee)
            ->getJson('/api/operations/quote-requests')
            ->assertForbidden();
    }

    public function test_information_request_and_customer_response_cycle(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuoteRequestStatus::New,
        ]);

        $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/request-information', [
                'message' => 'يرجى تحديد المقاس النهائي',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', QuoteRequestStatus::NeedsInformation->value);

        $this->asUser($customer)
            ->postJson('/api/customer/quote-requests/'.$request->id.'/respond', [
                'message' => 'المقاس A4 خامة مطفية',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', QuoteRequestStatus::UnderReview->value);
    }

    public function test_owner_creates_sends_and_customer_accepts_quotation(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuoteRequestStatus::UnderReview,
            'payload' => [
                'line_items' => [
                    ['description' => 'تصميمات سوشيال', 'quantity' => 15, 'unit_price' => '0.00', 'category' => 'CREATIVE'],
                ],
            ],
        ]);

        $created = $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated();

        $qid = (int) $created->json('data.id');

        $this->asUser($owner)
            ->patchJson('/api/operations/commercial-quotations/'.$qid, [
                'payment_policy' => PrintingPaymentPolicy::None->value,
                'valid_until' => now()->addDays(7)->toDateString(),
                'tax_amount' => '0.00',
                'items' => [
                    [
                        'description' => 'تصميمات سوشيال',
                        'quantity' => 15,
                        'unit_price' => '300.00',
                        'category' => 'CREATIVE',
                    ],
                    [
                        'description' => 'ريلز',
                        'quantity' => 8,
                        'unit_price' => '1000.00',
                        'category' => 'PRODUCTION',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '12500.00')
            ->assertJsonPath('data.total', '12500.00');

        $send = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid.'/send')
            ->assertOk();

        $token = $send->json('data.public_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        $this->assertDatabaseHas('quote_requests', [
            'id' => $request->id,
            'status' => QuoteRequestStatus::Quoted->value,
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', CommercialQuotationStatus::Accepted->value);

        $quotation = CommercialQuotation::query()->findOrFail($qid);
        $this->assertNotNull($quotation->snapshot);
        $this->assertSame('12500.00', (string) ($quotation->snapshot['total'] ?? ''));

        $this->assertDatabaseHas('quote_requests', [
            'id' => $request->id,
            'status' => QuoteRequestStatus::Accepted->value,
        ]);
    }

    public function test_double_accept_is_idempotent_and_expired_cannot_accept(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create(['customer_id' => $customer->id]);

        $quotation = CommercialQuotation::factory()->create([
            'quote_request_id' => $request->id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
            'status' => CommercialQuotationStatus::Sent,
            'valid_until' => now()->addDays(5)->toDateString(),
            'payment_policy' => PrintingPaymentPolicy::None,
            'total' => '500.00',
            'subtotal' => '500.00',
        ]);
        CommercialQuotationItem::query()->create([
            'commercial_quotation_id' => $quotation->id,
            'description' => 'خدمة',
            'quantity' => '1.00',
            'unit_price' => '500.00',
            'subtotal' => '500.00',
            'sort_order' => 0,
        ]);

        $raw = str_repeat('c', 64);
        $quotation->update([
            'public_token_hash' => CommercialQuotation::hashToken($raw),
            'public_token_hint' => 'cccccccc',
            'sent_at' => now(),
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$raw.'/accept')->assertOk();
        $this->postJson('/api/public/commercial-quotations/'.$raw.'/accept')->assertOk()
            ->assertJsonPath('data.status', CommercialQuotationStatus::Accepted->value);

        $expired = CommercialQuotation::factory()->create([
            'quote_request_id' => $request->id,
            'customer_id' => $customer->id,
            'created_by' => $owner->id,
            'status' => CommercialQuotationStatus::Sent,
            'valid_until' => now()->subDay()->toDateString(),
            'revision' => 2,
            'reference' => $quotation->reference.'-R2',
        ]);
        $raw2 = str_repeat('d', 64);
        $expired->update([
            'public_token_hash' => CommercialQuotation::hashToken($raw2),
            'public_token_hint' => 'dddddddd',
            'sent_at' => now()->subDays(2),
        ]);

        $this->postJson('/api/public/commercial-quotations/'.$raw2.'/accept')->assertUnprocessable();
    }

    public function test_reject_and_revision_flow_with_new_revision_only_accept(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create(['customer_id' => $customer->id]);

        $created = $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated();
        $qid = (int) $created->json('data.id');

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, [
            'payment_policy' => PrintingPaymentPolicy::None->value,
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [
                ['description' => 'خدمة', 'quantity' => 1, 'unit_price' => '1000.00', 'category' => 'OTHER'],
            ],
        ])->assertOk();

        $token = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid.'/send')
            ->assertOk()
            ->json('data.public_token');

        $this->postJson('/api/public/commercial-quotations/'.$token.'/revision', [
            'reason' => 'مراجعة السعر',
            'reason_code' => 'price_review',
        ])->assertOk();

        $this->assertDatabaseHas('quote_requests', [
            'id' => $request->id,
            'status' => QuoteRequestStatus::RevisionRequested->value,
        ]);

        $revised = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid.'/revise')
            ->assertCreated();
        $qid2 = (int) $revised->json('data.id');

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid2, [
            'payment_policy' => PrintingPaymentPolicy::None->value,
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [
                ['description' => 'خدمة', 'quantity' => 1, 'unit_price' => '900.00', 'category' => 'OTHER'],
            ],
        ])->assertOk();

        $token2 = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid2.'/send')
            ->assertOk()
            ->json('data.public_token');

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertUnprocessable();
        $this->postJson('/api/public/commercial-quotations/'.$token2.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', CommercialQuotationStatus::Accepted->value);
    }

    public function test_customer_cannot_view_other_customer_request(): void
    {
        $a = User::factory()->create(['role' => UserRole::Customer->value]);
        $b = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create(['customer_id' => $a->id]);

        $this->asUser($b)
            ->getJson('/api/customer/quote-requests/'.$request->id)
            ->assertNotFound();
    }

    public function test_customer_quote_api_hides_internal_quotation_and_supplier_fields(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create([
            'customer_id' => $customer->id,
            'internal_notes' => 'هامش داخلي للمورد 3000',
        ]);

        $created = $this->asUser($owner)
            ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
            ->assertCreated();
        $qid = (int) $created->json('data.id');

        CommercialQuotation::query()->whereKey($qid)->update([
            'internal_notes' => 'تكلفة مورد سرية',
            'public_token_hash' => hash('sha256', 'secret-public-token'),
            'tracking_token_hash' => hash('sha256', 'secret-track-token'),
        ]);
        CommercialQuotationItem::query()
            ->where('commercial_quotation_id', $qid)
            ->update([
                'selected_supplier_quote_id' => null,
                'meta' => ['supplier_cost' => 3000, 'margin' => 40],
            ]);

        $response = $this->asUser($customer)
            ->getJson('/api/customer/quote-requests/'.$request->id)
            ->assertOk();

        $payload = $response->json('data');
        $this->assertArrayNotHasKey('internal_notes', $payload);
        $quotation = $payload['commercial_quotations'][0] ?? null;
        $this->assertIsArray($quotation);
        $this->assertArrayNotHasKey('internal_notes', $quotation);
        $this->assertArrayNotHasKey('public_token_hash', $quotation);
        $this->assertArrayNotHasKey('tracking_token_hash', $quotation);
        $item = $quotation['items'][0] ?? null;
        $this->assertIsArray($item);
        $this->assertArrayNotHasKey('selected_supplier_quote_id', $item);
        $this->assertArrayNotHasKey('meta', $item);
        $this->assertStringNotContainsString('3000', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function test_none_deposit_and_full_payment_policies_are_stored_on_accept_snapshot(): void
    {
        foreach ([PrintingPaymentPolicy::None, PrintingPaymentPolicy::Deposit, PrintingPaymentPolicy::Full] as $policy) {
            $owner = User::factory()->owner()->create();
            $customer = User::factory()->create(['role' => UserRole::Customer->value]);
            $request = QuoteRequest::factory()->create(['customer_id' => $customer->id]);

            $created = $this->asUser($owner)
                ->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')
                ->assertCreated();
            $qid = (int) $created->json('data.id');

            $payload = [
                'payment_policy' => $policy->value,
                'valid_until' => now()->addDays(7)->toDateString(),
                'items' => [
                    ['description' => 'خدمة', 'quantity' => 1, 'unit_price' => '2000.00', 'category' => 'OTHER'],
                ],
            ];
            if ($policy === PrintingPaymentPolicy::Deposit) {
                $payload['deposit_required'] = '500.00';
            }

            $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, $payload)->assertOk();
            $token = $this->asUser($owner)
                ->postJson('/api/operations/commercial-quotations/'.$qid.'/send')
                ->assertOk()
                ->json('data.public_token');

            $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')->assertOk();

            $snapshot = CommercialQuotation::query()->findOrFail($qid)->snapshot;
            $this->assertSame($policy->value, $snapshot['payment_policy'] ?? null);
            $this->assertArrayHasKey('total', $snapshot);

            // Acceptance does not mark payment as completed.
            $this->assertDatabaseMissing('payments', [
                'commercial_quotation_id' => $qid,
            ]);
        }
    }

    public function test_reject_keeps_history(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::Customer->value]);
        $request = QuoteRequest::factory()->create(['customer_id' => $customer->id]);
        $created = $this->asUser($owner)->postJson('/api/operations/quote-requests/'.$request->id.'/quotations')->assertCreated();
        $qid = (int) $created->json('data.id');
        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, [
            'payment_policy' => PrintingPaymentPolicy::None->value,
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [['description' => 'خدمة', 'quantity' => 1, 'unit_price' => '100.00', 'category' => 'OTHER']],
        ])->assertOk();
        $token = $this->asUser($owner)->postJson('/api/operations/commercial-quotations/'.$qid.'/send')->json('data.public_token');

        $this->postJson('/api/public/commercial-quotations/'.$token.'/reject', [
            'reason' => 'خارج الميزانية',
        ])->assertOk()->assertJsonPath('data.status', CommercialQuotationStatus::Rejected->value);

        $this->assertDatabaseHas('commercial_quotations', ['id' => $qid, 'status' => CommercialQuotationStatus::Rejected->value]);
        $this->assertDatabaseHas('quote_requests', ['id' => $request->id, 'status' => QuoteRequestStatus::Rejected->value]);
    }
}
