<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\Operations\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookPhase5Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_create_returns_secret_once(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->asUser($owner)->postJson('/api/operations/webhooks', [
            'name' => 'CRM hooks',
            'url' => 'https://example.com/hooks/hebr',
            'events' => [WorkflowTrigger::OrderCreated->value],
        ])->assertCreated();

        $response->assertJsonPath('data.name', 'CRM hooks');
        $this->assertNotEmpty($response->json('data.secret'));
        $this->assertSame(4, strlen((string) $response->json('data.secret_hint')));
        $this->assertStringEndsWith(
            (string) $response->json('data.secret_hint'),
            (string) $response->json('data.secret'),
        );
        $this->assertArrayNotHasKey('secret_encrypted', $response->json('data'));
    }

    public function test_show_does_not_include_secret(): void
    {
        $owner = User::factory()->owner()->create();

        $created = $this->asUser($owner)->postJson('/api/operations/webhooks', [
            'name' => 'Hooks',
            'url' => 'https://example.com/hooks',
            'events' => [WorkflowTrigger::OrderStatusChanged->value],
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->asUser($owner)->getJson('/api/operations/webhooks/'.$id)
            ->assertOk()
            ->assertJsonMissingPath('data.secret')
            ->assertJsonPath('data.secret_hint', $created->json('data.secret_hint'));
    }

    public function test_ssrf_localhost_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->asUser($owner)->postJson('/api/operations/webhooks', [
            'name' => 'Bad',
            'url' => 'http://127.0.0.1/hook',
            'events' => [WorkflowTrigger::OrderCreated->value],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['url']);

        $this->asUser($owner)->postJson('/api/operations/webhooks', [
            'name' => 'Bad2',
            'url' => 'http://localhost/hook',
            'events' => [WorkflowTrigger::OrderCreated->value],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_http_fake_successful_delivery_includes_signature(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $owner = User::factory()->owner()->create();
        $secret = 'test-webhook-secret-value-1234567890';

        $webhook = OutboundWebhook::query()->create([
            'name' => 'Delivery',
            'url' => 'https://example.com/hooks/hebr',
            'events' => [WorkflowTrigger::OrderCreated->value],
            'secret_encrypted' => Crypt::encryptString($secret),
            'secret_hint' => substr($secret, -4),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $delivery = $dispatcher->queueDelivery(
            $webhook,
            WorkflowTrigger::OrderCreated->value,
            [
                'event' => WorkflowTrigger::OrderCreated->value,
                'id' => 42,
                'status' => 'pending',
                'title' => 'Order',
                'reference' => 'ORD-42',
            ],
            'order:42:test',
        );

        // Avoid double-processing if the sync queue already ran the job.
        $delivery->refresh();
        if ($delivery->status === WebhookDelivery::STATUS_PENDING) {
            $dispatcher->attempt($delivery->id);
        }

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame(200, $delivery->response_status);

        Http::assertSent(function ($request) use ($secret, $delivery) {
            $body = $request->body();
            $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

            return $request->url() === 'https://example.com/hooks/hebr'
                && $request->hasHeader('X-Hebr-Event', WorkflowTrigger::OrderCreated->value)
                && $request->hasHeader('X-Hebr-Delivery', $delivery->delivery_id)
                && $request->header('X-Hebr-Signature')[0] === $expected;
        });
    }

    public function test_retry_marks_failed_after_max(): void
    {
        Http::fake([
            'example.com/*' => Http::response('nope', 500),
        ]);

        $owner = User::factory()->owner()->create();
        $webhook = OutboundWebhook::query()->create([
            'name' => 'Failing',
            'url' => 'https://example.com/fail',
            'events' => [WorkflowTrigger::OrderCreated->value],
            'secret_encrypted' => Crypt::encryptString('secret-for-fail-tests-abcdef'),
            'secret_hint' => 'cdef',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $dispatcher = app(WebhookDispatcher::class);
        $delivery = WebhookDelivery::query()->create([
            'delivery_id' => (string) Str::uuid(),
            'outbound_webhook_id' => $webhook->id,
            'event' => WorkflowTrigger::OrderCreated->value,
            'idempotency_key' => $webhook->id.'|order.created|manual-fail',
            'payload' => ['event' => 'order.created', 'id' => 1],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);

        for ($i = 0; $i < WebhookDispatcher::MAX_ATTEMPTS; $i++) {
            $delivery->refresh();
            $delivery->forceFill(['next_retry_at' => now()->subSecond(), 'status' => WebhookDelivery::STATUS_PENDING])->save();
            $dispatcher->attempt($delivery->id);
        }

        $delivery->refresh();
        $this->assertSame(WebhookDispatcher::MAX_ATTEMPTS, $delivery->attempt_count);
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
    }

    public function test_unauthorized_forbidden(): void
    {
        $employee = User::factory()->graphicDesigner()->create();

        $this->asUser($employee)->postJson('/api/operations/webhooks', [
            'name' => 'Nope',
            'url' => 'https://example.com/hooks',
            'events' => [WorkflowTrigger::OrderCreated->value],
        ])->assertForbidden();

        $sales = User::factory()->create([
            'role' => UserRole::SalesRepresentative,
            'is_active' => true,
        ]);

        $this->asUser($sales)->getJson('/api/operations/webhooks')->assertForbidden();
    }
}
