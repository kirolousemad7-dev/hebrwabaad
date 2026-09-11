<?php

namespace App\Http\Resources;

use App\Enums\CatalogPricingMode;
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
            'is_custom_package' => (bool) $this->is_custom_package,
            'requires_quote' => (bool) $this->requires_quote,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'service' => $item->relationLoaded('service') && $item->service
                    ? ['id' => $item->service->id, 'name' => $item->service->name, 'slug' => $item->service->slug]
                    : null,
                'quantity' => $item->quantity,
                'pricing_mode' => $item->pricing_mode instanceof CatalogPricingMode
                    ? $item->pricing_mode->value
                    : $item->pricing_mode,
                'unit_price' => $item->unit_price,
                'currency' => $item->currency,
                'notes' => $item->notes,
                'addons' => $item->relationLoaded('addons')
                    ? $item->addons->map(fn ($row) => [
                        'id' => $row->catalog_addon_id,
                        'slug' => $row->addon?->slug,
                        'name' => $row->addon?->name,
                        'quantity' => $row->quantity,
                    ])->values()->all()
                    : [],
            ])->values()->all()),
            'package_addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($row) => [
                'id' => $row->catalog_addon_id,
                'slug' => $row->addon?->slug,
                'name' => $row->addon?->name,
                'quantity' => $row->quantity,
            ])->values()->all()),
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
