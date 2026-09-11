<?php

namespace Tests\Feature;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Mail\PrintingQuotationMail;
use App\Models\CustomerCommunicationDelivery;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Customer\CustomerCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentCommunicationPhase8Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_quotation_email_records_delivery_with_dedupe_key(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'ops@example.com',
            'mail.from.name' => 'Hebr',
        ]);

        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
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
            ->assertCreated()
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->assertOk()
            ->json('data.public_token');

        Mail::assertQueued(PrintingQuotationMail::class);

        $quotation = PrintingQuotation::query()->findOrFail($id);
        $delivery = CustomerCommunicationDelivery::query()
            ->where('type', 'quote_email')
            ->where('related_id', $id)
            ->first();

        $this->assertNotNull($delivery);
        $this->assertSame('queued', $delivery->status);
        $this->assertSame(
            'quote_email:'.$id.':'.(int) $quotation->revision,
            $delivery->dedupe_key,
        );

        $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/email', [
                'public_token' => $token,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'deduped');

        $this->assertSame(
            1,
            CustomerCommunicationDelivery::query()
                ->where('type', 'quote_email')
                ->where('related_id', $id)
                ->where('status', 'queued')
                ->count(),
        );
    }

    public function test_mail_disabled_skips_without_occupying_dedupe_key(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'ops@example.com',
        ]);

        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '80.00',
            'assigned_to' => $specialist->id,
        ]);

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->json('data.id');

        $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->assertOk();

        Mail::assertNothingQueued();

        $skipped = CustomerCommunicationDelivery::query()
            ->where('type', 'quote_email')
            ->where('related_id', $id)
            ->where('status', 'skipped')
            ->first();

        $this->assertNotNull($skipped);
        $this->assertNull($skipped->dedupe_key);

        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'ops@example.com',
        ]);

        $quotation = PrintingQuotation::query()->findOrFail($id);
        $raw = str_repeat('z', 64);
        $quotation->update([
            'public_token_hash' => PrintingQuotation::hashToken($raw),
            'public_token_hint' => substr($raw, -8),
        ]);

        $result = app(CustomerCommunicationService::class)->sendQuotationEmail(
            $quotation->fresh() ?? $quotation,
            $raw,
        );

        $this->assertSame('queued', $result['status']);
        Mail::assertQueued(PrintingQuotationMail::class);
        $this->assertSame(
            'quote_email:'.$id.':'.(int) $quotation->revision,
            $result['delivery']->dedupe_key,
        );
    }
}
