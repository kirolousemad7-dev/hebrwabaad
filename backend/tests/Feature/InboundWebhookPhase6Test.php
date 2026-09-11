<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\InboundWebhookIntegration;
use App\Models\InboundWebhookReceipt;
use App\Models\Payment;
use App\Models\PrintingQuotation;
use App\Models\User;
use App\Services\Operations\InboundWebhooks\InboundWebhookManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class InboundWebhookPhase6Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{integration: InboundWebhookIntegration, secret: string}
     */
    private function makeIntegration(?User $owner = null): array
    {
        $owner ??= User::factory()->owner()->create();
        $secret = 'test-inbound-secret-value-1234567890';

        $integration = InboundWebhookIntegration::query()->create([
            'name' => 'Generic HMAC',
            'integration_type' => InboundWebhookManager::TYPE_GENERIC_HMAC,
            'secret_encrypted' => Crypt::encryptString($secret),
            'secret_hint' => substr($secret, -4),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        return compact('integration', 'secret');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signedHeaders(string $secret, array $payload, ?string $deliveryId = null, ?int $timestamp = null): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $ts = (string) ($timestamp ?? now()->timestamp);
        $deliveryId ??= (string) Str::uuid();

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HEBR_TIMESTAMP' => $ts,
            'HTTP_X_HEBR_DELIVERY' => $deliveryId,
            'HTTP_X_HEBR_SIGNATURE' => 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret),
        ];
    }

    public function test_owner_create_returns_secret_once(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->asUser($owner)->postJson('/api/operations/inbound-webhooks', [
            'name' => 'Ops inbound',
            'integration_type' => InboundWebhookManager::TYPE_GENERIC_HMAC,
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.secret'));
        $this->assertSame(4, strlen((string) $response->json('data.secret_hint')));
        $this->assertArrayNotHasKey('secret_encrypted', $response->json('data'));

        $id = $response->json('data.id');
        $this->asUser($owner)->getJson('/api/operations/inbound-webhooks/'.$id)
            ->assertOk()
            ->assertJsonMissingPath('data.secret');
    }

    public function test_valid_signature_ping(): void
    {
        ['integration' => $integration, 'secret' => $secret] = $this->makeIntegration();
        $payload = ['event' => 'ping'];
        $headers = $this->signedHeaders($secret, $payload);

        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.pong', true);

        $this->assertDatabaseHas('inbound_webhook_receipts', [
            'delivery_id' => $headers['HTTP_X_HEBR_DELIVERY'],
            'status' => InboundWebhookReceipt::STATUS_PROCESSED,
            'event' => 'ping',
        ]);
    }

    public function test_invalid_signature_rejected(): void
    {
        ['integration' => $integration, 'secret' => $secret] = $this->makeIntegration();
        $payload = ['event' => 'ping'];
        $headers = $this->signedHeaders($secret, $payload);
        $headers['HTTP_X_HEBR_SIGNATURE'] = 'sha256=deadbeef';

        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertStatus(401)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseHas('inbound_webhook_receipts', [
            'delivery_id' => $headers['HTTP_X_HEBR_DELIVERY'],
            'status' => InboundWebhookReceipt::STATUS_REJECTED,
        ]);
    }

    public function test_replay_returns_same_success_without_reprocessing(): void
    {
        ['integration' => $integration, 'secret' => $secret] = $this->makeIntegration();
        $payload = ['event' => 'ping'];
        $headers = $this->signedHeaders($secret, $payload);

        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertOk()->assertJsonPath('replay', false);

        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('replay', true);

        $this->assertSame(1, InboundWebhookReceipt::query()
            ->where('delivery_id', $headers['HTTP_X_HEBR_DELIVERY'])
            ->count());
    }

    public function test_unknown_event_rejected(): void
    {
        ['integration' => $integration, 'secret' => $secret] = $this->makeIntegration();
        $payload = ['event' => 'printing.external_status', 'printing_request_id' => 1];
        $headers = $this->signedHeaders($secret, $payload);

        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseHas('inbound_webhook_receipts', [
            'delivery_id' => $headers['HTTP_X_HEBR_DELIVERY'],
            'status' => InboundWebhookReceipt::STATUS_REJECTED,
            'result_summary' => 'unknown_event',
        ]);
    }

    public function test_payment_confirm_idempotent(): void
    {
        $owner = User::factory()->owner()->create();
        ['integration' => $integration, 'secret' => $secret] = $this->makeIntegration($owner);

        $quotation = PrintingQuotation::factory()->accepted()->create([
            'created_by' => $owner->id,
        ]);

        $payment = Payment::query()->create([
            'customer_id' => $quotation->customer_id,
            'order_id' => null,
            'printing_quotation_id' => $quotation->id,
            'amount' => $quotation->total,
            'currency' => $quotation->currency,
            'payment_method' => PaymentMethod::Instapay,
            'status' => PaymentStatus::Pending,
            'provider' => PaymentMethod::Instapay->provider(),
        ]);

        $payload = [
            'event' => 'payment.confirm',
            'payment_id' => $payment->id,
            'provider_transaction_id' => 'EXT-123',
            'external_reference' => 'CORR-99',
        ];
        $headers = $this->signedHeaders($secret, $payload);

        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.payment_id', $payment->id);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame('EXT-123', $payment->fresh()->provider_transaction_id);

        $receipt = InboundWebhookReceipt::query()
            ->where('delivery_id', $headers['HTTP_X_HEBR_DELIVERY'])
            ->first();
        $this->assertNotNull($receipt);
        $this->assertSame('CORR-99', $receipt->payload_meta['external_reference'] ?? null);

        // Replay must not fail even though payment is already paid.
        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $headers,
            json_encode($payload),
        )->assertOk()
            ->assertJsonPath('replay', true);

        $this->assertSame(1, InboundWebhookReceipt::query()
            ->where('delivery_id', $headers['HTTP_X_HEBR_DELIVERY'])
            ->count());
    }

    public function test_payment_confirm_second_delivery_is_idempotent_when_already_paid(): void
    {
        $owner = User::factory()->owner()->create();
        ['integration' => $integration, 'secret' => $secret] = $this->makeIntegration($owner);

        $quotation = PrintingQuotation::factory()->accepted()->create([
            'created_by' => $owner->id,
        ]);

        $payment = Payment::query()->create([
            'customer_id' => $quotation->customer_id,
            'order_id' => null,
            'printing_quotation_id' => $quotation->id,
            'amount' => $quotation->total,
            'currency' => $quotation->currency,
            'payment_method' => PaymentMethod::BankTransfer,
            'status' => PaymentStatus::Pending,
            'provider' => PaymentMethod::BankTransfer->provider(),
        ]);

        $payload = [
            'event' => 'payment.confirm',
            'payment_id' => $payment->id,
        ];

        $first = $this->signedHeaders($secret, $payload);
        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $first,
            json_encode($payload),
        )->assertOk();

        $second = $this->signedHeaders($secret, $payload);
        $this->call(
            'POST',
            '/api/webhooks/inbound/'.$integration->id,
            [],
            [],
            [],
            $second,
            json_encode($payload),
        )->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(2, InboundWebhookReceipt::query()
            ->where('inbound_webhook_integration_id', $integration->id)
            ->where('event', 'payment.confirm')
            ->where('status', InboundWebhookReceipt::STATUS_PROCESSED)
            ->count());
    }

    public function test_non_owner_cannot_manage_inbound_webhooks(): void
    {
        $employee = User::factory()->webDeveloper()->create();
        $this->assertNotSame(UserRole::Owner, $employee->role);

        $this->asUser($employee)->postJson('/api/operations/inbound-webhooks', [
            'name' => 'Nope',
        ])->assertForbidden();
    }
}
