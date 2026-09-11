<?php

namespace App\Services\Operations\InboundWebhooks;

use App\Enums\UserRole;
use App\Models\InboundWebhookIntegration;
use App\Models\InboundWebhookReceipt;
use App\Models\User;
use App\Services\Operations\OperationsAuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InboundWebhookIntegrationService
{
    public function __construct(
        private readonly OperationsAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{integration: InboundWebhookIntegration, secret: string}
     */
    public function create(User $actor, array $attributes): array
    {
        $this->assertCanManage($actor);

        $type = (string) ($attributes['integration_type'] ?? InboundWebhookManager::TYPE_GENERIC_HMAC);
        if (! in_array($type, InboundWebhookManager::allowedTypes(), true)) {
            throw ValidationException::withMessages([
                'integration_type' => ['Integration type is not allowlisted.'],
            ]);
        }

        $secret = Str::random(40);
        $integration = InboundWebhookIntegration::query()->create([
            'name' => $attributes['name'],
            'integration_type' => $type,
            'secret_encrypted' => Crypt::encryptString($secret),
            'secret_hint' => substr($secret, -4),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'created_by' => $actor->id,
        ]);

        $this->audit->log($actor, 'inbound_webhook.created', $integration, [
            'integration_type' => $type,
        ]);

        return ['integration' => $integration, 'secret' => $secret];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, InboundWebhookIntegration $integration, array $attributes): InboundWebhookIntegration
    {
        $this->assertCanManage($actor);

        if (isset($attributes['integration_type'])) {
            throw ValidationException::withMessages([
                'integration_type' => ['Integration type cannot be changed after create.'],
            ]);
        }

        $integration->fill(collect($attributes)->only([
            'name',
            'is_active',
        ])->all())->save();

        $this->audit->log($actor, 'inbound_webhook.updated', $integration, [
            'changes' => array_keys($attributes),
        ]);

        return $integration->fresh() ?? $integration;
    }

    public function delete(User $actor, InboundWebhookIntegration $integration): void
    {
        $this->assertCanManage($actor);
        $integration->delete();
        $this->audit->log($actor, 'inbound_webhook.deleted', $integration);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(InboundWebhookIntegration $integration, ?string $secret = null): array
    {
        $data = [
            'id' => $integration->id,
            'name' => $integration->name,
            'integration_type' => $integration->integration_type,
            'secret_hint' => $integration->secret_hint,
            'is_active' => (bool) $integration->is_active,
            'created_by' => $integration->created_by,
            'created_at' => $integration->created_at?->toIso8601String(),
            'updated_at' => $integration->updated_at?->toIso8601String(),
            'endpoint' => '/api/webhooks/inbound/'.$integration->id,
            'allowed_events' => $integration->integration_type === InboundWebhookManager::TYPE_GENERIC_HMAC
                ? InboundWebhookManager::GENERIC_HMAC_EVENTS
                : [],
        ];

        if ($secret !== null) {
            $data['secret'] = $secret;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeReceipt(InboundWebhookReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'event' => $receipt->event,
            'delivery_id' => $receipt->delivery_id,
            'status' => $receipt->status,
            'response_status' => $receipt->response_status,
            'result_summary' => $receipt->result_summary,
            'payload_meta' => $receipt->payload_meta ?? [],
            'received_at' => $receipt->received_at?->toIso8601String(),
            'processed_at' => $receipt->processed_at?->toIso8601String(),
        ];
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canManageIntegrations()) {
            throw ValidationException::withMessages([
                'integration' => ['Only owners and integration managers can manage inbound webhooks.'],
            ]);
        }
    }
}
