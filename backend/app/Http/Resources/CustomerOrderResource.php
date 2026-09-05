<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-safe order payload.
 *
 * @mixin Order
 */
class CustomerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::from((string) $this->status);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $status->value,
            'status_label' => $status->label(),
            'progress' => $status->progressPercent(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'project' => $this->whenLoaded('project', fn () => $this->project === null ? null : [
                'id' => $this->project->id,
                'title' => $this->project->title,
            ]),
            'service' => $this->whenLoaded('service', fn () => $this->service === null ? null : [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ]),
            'package' => $this->whenLoaded('package', fn () => $this->package === null ? null : [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'slug' => $this->package->slug,
            ]),
            'package_tier' => $this->whenLoaded('packageTier', fn () => $this->packageTier === null ? null : [
                'id' => $this->packageTier->id,
                'name' => $this->packageTier->name,
                'slug' => $this->packageTier->slug,
            ]),
            'account_manager' => $this->whenLoaded('accountManager', fn () => $this->accountManager === null ? null : [
                'id' => $this->accountManager->id,
                'name' => $this->accountManager->name,
            ]),
            'payable' => app(PaymentService::class)->payablePayload($this->resource),
            'latest_payment' => $this->whenLoaded(
                'latestPayment',
                fn () => $this->latestPayment === null
                    ? null
                    : PaymentResource::make($this->latestPayment)->resolve($request),
            ),
            'timeline' => app(OrderService::class)->timeline($this->resource),
        ];
    }
}
