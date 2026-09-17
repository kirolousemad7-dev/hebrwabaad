<?php

namespace Tests\Feature;

use App\Enums\CommercialQuotationStatus;
use App\Enums\QuoteRequestStatus;
use App\Enums\SupplierQuoteStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationItem;
use App\Models\QuotationSupplierQuote;
use App\Models\QuoteRequest;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationSupplierSourcingTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{
     *   owner: User,
     *   customer: User,
     *   quotation: CommercialQuotation,
     *   item: CommercialQuotationItem,
     *   token: string,
     *   supplierA: array{user: User, supplier: Supplier},
     *   supplierB: array{user: User, supplier: Supplier}
     * }
     */
    private function seededQuotation(): array
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

        $this->asUser($owner)->patchJson('/api/operations/commercial-quotations/'.$qid, [
            'items' => [
                [
                    'description' => 'تصميم وتنفيذ فيديو',
                    'quantity' => 1,
                    'unit_price' => '10000.00',
                    'category' => 'PRODUCTION',
                ],
            ],
            'tax_amount' => '0',
            'discount_amount' => '0',
        ])->assertOk();

        Notification::fake();

        $token = $this->asUser($owner)
            ->postJson('/api/operations/commercial-quotations/'.$qid.'/send')
            ->assertOk()
            ->json('data.public_token');

        $quotation = CommercialQuotation::query()->with('items')->findOrFail($qid);
        $item = $quotation->items->firstOrFail();

        $supplierA = $this->makeSupplier('a@supplier.test', 'مورد أ');
        $supplierB = $this->makeSupplier('b@supplier.test', 'مورد ب');

        return [
            'owner' => $owner,
            'customer' => $customer,
            'quotation' => $quotation,
            'item' => $item,
            'token' => $token,
            'supplierA' => $supplierA,
            'supplierB' => $supplierB,
        ];
    }

    /**
     * @return array{user: User, supplier: Supplier}
     */
    private function makeSupplier(string $email, string $name): array
    {
        $user = User::factory()->supplier()->create([
            'email' => $email,
            'password' => 'Password123!',
        ]);
        $supplier = Supplier::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'display_name' => $name,
            'email' => $email,
            'status' => SupplierStatus::Active,
            'is_active' => true,
        ]);

        return ['user' => $user, 'supplier' => $supplier];
    }

    public function test_owner_can_request_compare_select_and_see_margins(): void
    {
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $supplierB = $ctx['supplierB']['supplier'];
        $userA = $ctx['supplierA']['user'];
        $userB = $ctx['supplierB']['user'];

        $quoteA = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->assertCreated()
            ->json('data');

        $quoteB = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierB->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($userA)->postJson('/api/supplier/sourcing-requests/'.$quoteA['id'].'/respond', [
            'cost' => '3000.00',
            'delivery_days' => 5,
            'notes' => 'عرض أ',
        ])->assertOk();

        $this->asUser($userB)->postJson('/api/supplier/sourcing-requests/'.$quoteB['id'].'/respond', [
            'cost' => '3500.00',
            'delivery_days' => 7,
            'notes' => 'عرض ب',
        ])->assertOk();

        $compare = $this->asUser($owner)
            ->getJson("/api/operations/commercial-quotation-items/{$item->id}/supplier-quotes/compare")
            ->assertOk()
            ->json('data.options');

        $this->assertCount(2, $compare);
        $this->assertSame('3000.00', $compare[0]['cost']);
        $this->assertArrayHasKey('margin', $compare[0]);
        $this->assertSame('7000.00', $compare[0]['margin']['gross_margin']);

        $this->asUser($owner)
            ->postJson('/api/operations/supplier-quotes/'.$quoteA['id'].'/select')
            ->assertOk()
            ->assertJsonPath('data.status', SupplierQuoteStatus::Selected->value);

        $staff = $this->asUser($owner)
            ->getJson("/api/operations/commercial-quotations/{$quotation->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('sourcing', $staff);
        $this->assertSame('مورد أ', $staff['sourcing']['items'][0]['supplier_options'][0]['supplier']['name']);
        $this->assertSame('3000.00', $staff['sourcing']['totals']['selected_supplier_cost']);
        $this->assertSame('7000.00', $staff['sourcing']['totals']['gross_margin']);
        $this->assertEqualsWithDelta(70.0, $staff['sourcing']['totals']['margin_percentage'], 0.01);
    }

    public function test_customer_public_api_cannot_retrieve_supplier_identity_or_cost(): void
    {
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $userA = $ctx['supplierA']['user'];
        $token = $ctx['token'];

        $quote = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($userA)->postJson('/api/supplier/sourcing-requests/'.$quote['id'].'/respond', [
            'cost' => '3000.00',
            'notes' => 'سر داخلي',
        ])->assertOk();

        $this->asUser($owner)->postJson('/api/operations/supplier-quotes/'.$quote['id'].'/select')->assertOk();

        $public = $this->getJson('/api/public/commercial-quotations/'.$token)
            ->assertOk()
            ->json('data');

        $encoded = json_encode($public, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('مورد أ', $encoded);
        $this->assertStringNotContainsString('3000', $encoded);
        $this->assertStringNotContainsString('سر داخلي', $encoded);
        $this->assertArrayNotHasKey('sourcing', $public);
        $this->assertArrayNotHasKey('supplier', $public);
        $this->assertArrayNotHasKey('supplier_options', $public);
        $this->assertArrayNotHasKey('gross_margin', $public);
        $this->assertArrayNotHasKey('margin', $public);
        $this->assertSame('تصميم وتنفيذ فيديو', $public['items'][0]['description']);
        $this->assertArrayHasKey('unit_price', $public['items'][0]);
        $this->assertArrayNotHasKey('cost', $public['items'][0]);

        $pdf = $this->get('/api/public/commercial-quotations/'.$token.'/pdf?format=html')->assertOk();
        $body = $pdf->getContent();
        $this->assertStringNotContainsString('مورد أ', $body);
        $this->assertStringNotContainsString('سر داخلي', $body);
        $this->assertStringNotContainsString('3000', $body);
        $this->assertStringNotContainsString('sourcing', $body);
    }

    public function test_customer_authenticated_pdf_excludes_supplier_data(): void
    {
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $customer = $ctx['customer'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $userA = $ctx['supplierA']['user'];

        $quote = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->json('data');

        $this->asUser($userA)->postJson('/api/supplier/sourcing-requests/'.$quote['id'].'/respond', [
            'cost' => '2500.00',
            'notes' => 'ملاحظات مورد',
        ])->assertOk();

        $pdf = $this->asUser($customer)
            ->get('/api/customer/commercial-quotations/'.$quotation->id.'/pdf?format=html')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('مورد أ', $pdf);
        $this->assertStringNotContainsString('2500', $pdf);
        $this->assertStringNotContainsString('ملاحظات مورد', $pdf);
    }

    public function test_supplier_can_only_access_own_sourcing_requests(): void
    {
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $userA = $ctx['supplierA']['user'];
        $userB = $ctx['supplierB']['user'];

        $quote = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->asUser($userA)
            ->getJson('/api/supplier/sourcing-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $quote['id']);

        $own = $this->asUser($userA)
            ->getJson('/api/supplier/sourcing-requests/'.$quote['id'])
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('customer_price', $own);
        $this->assertArrayNotHasKey('margin', $own);
        $this->assertArrayNotHasKey('gross_margin', $own);

        $this->asUser($userB)
            ->getJson('/api/supplier/sourcing-requests/'.$quote['id'])
            ->assertNotFound();

        $this->asUser($userB)
            ->postJson('/api/supplier/sourcing-requests/'.$quote['id'].'/respond', [
                'cost' => '999.00',
            ])
            ->assertNotFound();
    }

    public function test_accept_attaches_selected_supplier_to_execution_task(): void
    {
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $userA = $ctx['supplierA']['user'];
        $token = $ctx['token'];

        $quoteId = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->json('data.id');

        $this->asUser($userA)->postJson('/api/supplier/sourcing-requests/'.$quoteId.'/respond', [
            'cost' => '3000.00',
            'delivery_days' => 10,
        ])->assertOk();

        $this->asUser($owner)->postJson('/api/operations/supplier-quotes/'.$quoteId.'/select')->assertOk();

        $this->postJson('/api/public/commercial-quotations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', CommercialQuotationStatus::Accepted->value);

        $quotation->refresh();
        $this->assertNotNull($quotation->execution_project_id);

        $task = Task::query()->where('quotation_supplier_quote_id', $quoteId)->first();
        $this->assertNotNull($task);
        $this->assertSame((int) $supplierA->id, (int) $task->supplier_id);

        $publicAfter = $this->getJson('/api/public/commercial-quotations/'.$token)->assertOk()->json('data');
        $encoded = json_encode($publicAfter, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('مورد أ', $encoded);
        $this->assertSame($quotation->execution_project_id, $publicAfter['linkages']['project_id'] ?? null);
    }

    public function test_owner_can_reject_and_replace_supplier(): void
    {
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $supplierB = $ctx['supplierB']['supplier'];

        $quoteA = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->json('data.id');

        $this->asUser($owner)
            ->postJson('/api/operations/supplier-quotes/'.$quoteA.'/reject', ['reason' => 'غير مناسب'])
            ->assertOk()
            ->assertJsonPath('data.status', SupplierQuoteStatus::Rejected->value);

        $replacement = $this->asUser($owner)
            ->postJson('/api/operations/supplier-quotes/'.$quoteA.'/replace', [
                'supplier_id' => $supplierB->id,
                'rejection_reason' => 'استبدال',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($supplierB->id, $replacement['supplier']['id']);
        $this->assertSame(SupplierQuoteStatus::Requested->value, $replacement['status']);
        $this->assertSame(
            $replacement['id'],
            QuotationSupplierQuote::query()->findOrFail($quoteA)->replaced_by_id,
        );
    }

    public function test_customer_cannot_hit_owner_sourcing_endpoints(): void
    {
        $ctx = $this->seededQuotation();
        $customer = $ctx['customer'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];

        $this->asUser($customer)
            ->getJson("/api/operations/commercial-quotations/{$quotation->id}/sourcing")
            ->assertForbidden();

        $this->asUser($customer)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $ctx['supplierA']['supplier']->id,
            ])
            ->assertForbidden();
    }

    public function test_supplier_can_upload_attachment_on_response(): void
    {
        Storage::fake('local');
        $ctx = $this->seededQuotation();
        $owner = $ctx['owner'];
        $quotation = $ctx['quotation'];
        $item = $ctx['item'];
        $supplierA = $ctx['supplierA']['supplier'];
        $userA = $ctx['supplierA']['user'];

        $quoteId = $this->asUser($owner)
            ->postJson("/api/operations/commercial-quotations/{$quotation->id}/items/{$item->id}/supplier-quotes", [
                'supplier_id' => $supplierA->id,
            ])
            ->json('data.id');

        $this->asUser($userA)
            ->post('/api/supplier/sourcing-requests/'.$quoteId.'/respond', [
                'cost' => '4000.00',
                'delivery_days' => 3,
                'attachments' => [UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf')],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SupplierQuoteStatus::Received->value);

        $stored = QuotationSupplierQuote::query()->findOrFail($quoteId);
        $this->assertNotEmpty($stored->attachments);
        $this->assertSame('quote.pdf', $stored->attachments[0]['original_name']);
    }
}
