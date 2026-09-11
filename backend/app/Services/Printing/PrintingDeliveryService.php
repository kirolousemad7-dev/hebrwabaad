<?php

namespace App\Services\Printing;

use App\Enums\PrintingDeliveryMethod;
use App\Enums\PrintingDeliveryStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\ManagedFile;
use App\Models\PrintingDelivery;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Delivery\DeliveryProviderManager;
use App\Services\Operations\PrintingStatusTransitionService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintingDeliveryService
{
    public function __construct(
        private readonly DeliveryProviderManager $providers,
        private readonly PrintingStatusTransitionService $transitions,
        private readonly WorkflowAutomationEngine $workflows,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForRequest(User $actor, PrintingRequest $request, array $data): PrintingDelivery
    {
        $this->assertCanManage($actor);

        $status = $request->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::from((string) $request->status);

        if ($status !== PrintingRequestStatus::ReadyForDelivery) {
            throw ValidationException::withMessages([
                'delivery' => ['Delivery can only be created when the request is ready for delivery.'],
            ]);
        }

        $method = PrintingDeliveryMethod::from((string) $data['method']);
        $provider = strtoupper((string) ($data['provider'] ?? $this->providers->defaultProvider()));
        $this->providers->assertConfigured($provider);

        $driver = $this->providers->driver($provider);
        $shipment = $driver->createShipment([
            'printing_request_id' => $request->id,
            'method' => $method->value,
            'recipient_name' => $data['recipient_name'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'scheduled_window' => $data['scheduled_window'] ?? null,
        ]);

        $delivery = PrintingDelivery::query()->create([
            'printing_request_id' => $request->id,
            'method' => $method,
            'provider' => $provider,
            'status' => PrintingDeliveryStatus::Pending,
            'recipient_name' => $data['recipient_name'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'scheduled_window' => $data['scheduled_window'] ?? null,
            'metadata' => array_merge(
                is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
                $shipment !== null ? ['shipment' => $shipment] : [],
            ) ?: null,
            'created_by' => $actor->id,
            'external_reference' => is_array($shipment) ? ($shipment['external_reference'] ?? null) : null,
            'tracking_url' => is_array($shipment) ? ($shipment['tracking_url'] ?? null) : null,
        ]);

        $request->update([
            'delivery_method' => $method->value,
            'delivery_notes' => $data['notes'] ?? $request->delivery_notes,
        ]);

        return $delivery->fresh() ?? $delivery;
    }

    /**
     * @param  array{notes?: string|null, proof_file_id?: int|null, received_by?: string|null}  $data
     */
    public function markDelivered(User $actor, PrintingDelivery $delivery, array $data = []): PrintingDelivery
    {
        $this->assertCanManage($actor);

        $notes = $data['notes'] ?? null;
        $proofFileId = isset($data['proof_file_id']) ? (int) $data['proof_file_id'] : null;

        if ($proofFileId !== null) {
            $this->assertProofFile($proofFileId);
        }

        $status = $delivery->status instanceof PrintingDeliveryStatus
            ? $delivery->status
            : PrintingDeliveryStatus::from((string) $delivery->status);

        if ($status === PrintingDeliveryStatus::Delivered) {
            if ($proofFileId !== null && $delivery->proof_file_id === null) {
                $delivery->update(['proof_file_id' => $proofFileId]);
            }

            return $delivery->fresh(['printingRequest', 'proofFile']) ?? $delivery->load('printingRequest');
        }

        if ($status === PrintingDeliveryStatus::Cancelled) {
            throw ValidationException::withMessages([
                'delivery' => ['Cancelled deliveries cannot be marked delivered.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $delivery, $notes, $proofFileId, $data): PrintingDelivery {
            $metadata = is_array($delivery->metadata) ? $delivery->metadata : [];
            if (isset($data['received_by']) && is_string($data['received_by']) && $data['received_by'] !== '') {
                $metadata['received_by'] = $data['received_by'];
            }

            $delivery->update([
                'status' => PrintingDeliveryStatus::Delivered,
                'delivered_at' => now(),
                'notes' => $notes ?? $delivery->notes,
                'proof_file_id' => $proofFileId ?? $delivery->proof_file_id,
                'metadata' => $metadata !== [] ? $metadata : $delivery->metadata,
            ]);

            $request = PrintingRequest::query()->findOrFail($delivery->printing_request_id);
            $requestStatus = $request->status instanceof PrintingRequestStatus
                ? $request->status
                : PrintingRequestStatus::from((string) $request->status);

            if ($requestStatus === PrintingRequestStatus::ReadyForDelivery
                && $this->transitions->canTransition($requestStatus, PrintingRequestStatus::Completed)) {
                $this->transitions->transition($actor, $request, PrintingRequestStatus::Completed->value, $notes);
            }

            $fresh = $delivery->fresh(['printingRequest', 'proofFile']) ?? $delivery;
            $this->dispatchDeliveryCompleted($actor, $fresh);

            return $fresh;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customerSafePayloadForRequest(PrintingRequest $request): ?array
    {
        $delivery = PrintingDelivery::query()
            ->where('printing_request_id', $request->id)
            ->orderByDesc('id')
            ->first();

        if ($delivery === null) {
            $method = $request->delivery_method;
            if ($method === null || $method === '') {
                return null;
            }

            $resolved = PrintingDeliveryMethod::tryFrom((string) $method);

            return [
                'method' => $resolved?->value ?? (string) $method,
                'method_label' => $resolved?->label() ?? (string) $method,
                'status' => null,
                'status_label' => null,
                'pickup_ready_message' => $resolved === PrintingDeliveryMethod::Pickup
                    && $request->status === PrintingRequestStatus::ReadyForDelivery
                    ? 'طلبكم جاهز للاستلام'
                    : null,
                'scheduled_window' => null,
                'delivered_at' => $request->delivered_at?->toIso8601String(),
            ];
        }

        $method = $delivery->method instanceof PrintingDeliveryMethod
            ? $delivery->method
            : PrintingDeliveryMethod::from((string) $delivery->method);

        $status = $delivery->status instanceof PrintingDeliveryStatus
            ? $delivery->status
            : PrintingDeliveryStatus::from((string) $delivery->status);

        $requestStatus = $request->status instanceof PrintingRequestStatus
            ? $request->status
            : PrintingRequestStatus::tryFrom((string) $request->status);

        return [
            'method' => $method->value,
            'method_label' => $method->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'pickup_ready_message' => $method === PrintingDeliveryMethod::Pickup
                && in_array($requestStatus, [PrintingRequestStatus::ReadyForDelivery, PrintingRequestStatus::Completed], true)
                ? 'طلبكم جاهز للاستلام'
                : null,
            'scheduled_window' => $delivery->scheduled_window,
            'delivered_at' => $delivery->delivered_at?->toIso8601String()
                ?? $request->delivered_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffPayload(PrintingDelivery $delivery): array
    {
        $method = $delivery->method instanceof PrintingDeliveryMethod
            ? $delivery->method
            : PrintingDeliveryMethod::from((string) $delivery->method);
        $status = $delivery->status instanceof PrintingDeliveryStatus
            ? $delivery->status
            : PrintingDeliveryStatus::from((string) $delivery->status);

        return [
            'id' => $delivery->id,
            'printing_request_id' => $delivery->printing_request_id,
            'method' => $method->value,
            'provider' => $delivery->provider,
            'status' => $status->value,
            'recipient_name' => $delivery->recipient_name,
            'contact_name' => $delivery->contact_name,
            'contact_phone' => $delivery->contact_phone,
            'notes' => $delivery->notes,
            'scheduled_at' => $delivery->scheduled_at?->toIso8601String(),
            'scheduled_window' => $delivery->scheduled_window,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'proof_file_id' => $delivery->proof_file_id,
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }

    private function assertProofFile(int $proofFileId): void
    {
        if (! ManagedFile::query()->whereKey($proofFileId)->exists()) {
            throw ValidationException::withMessages([
                'proof_file_id' => ['Proof file not found.'],
            ]);
        }
    }

    private function dispatchDeliveryCompleted(User $actor, PrintingDelivery $delivery): void
    {
        $request = $delivery->printingRequest;
        if ($request === null) {
            return;
        }

        try {
            $this->workflows->dispatch(WorkflowTrigger::PrintingDeliveryCompleted->value, [
                'source_type' => 'printing_delivery',
                'source_id' => $delivery->id,
                'actor_id' => $actor->id,
                'title' => 'اكتمال تسليم طباعة: '.$request->product_name,
                'related_type' => 'printing_request',
                'related_id' => $request->id,
                'printing_request_id' => $request->id,
                'payload' => [
                    'printing_delivery_id' => $delivery->id,
                    'printing_request_id' => $request->id,
                    'method' => $delivery->method instanceof \BackedEnum
                        ? $delivery->method->value
                        : (string) $delivery->method,
                ],
            ], (string) $delivery->id);
        } catch (\Throwable) {
            // Workflow hooks must never break delivery completion.
        }
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canReviewPrintingRequests()) {
            throw ValidationException::withMessages([
                'delivery' => ['You cannot manage printing deliveries.'],
            ]);
        }
    }
}
