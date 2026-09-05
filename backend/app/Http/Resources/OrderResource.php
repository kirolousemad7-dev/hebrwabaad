<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Internal order payload for Owner and Account Manager.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'allowed_transitions' => array_map(
                fn (OrderStatus $next) => [
                    'status' => $next->value,
                    'label' => $next->label(),
                ],
                $status->allowedTransitions(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]),
            'account_manager' => $this->whenLoaded('accountManager', fn () => $this->accountManager === null ? null : [
                'id' => $this->accountManager->id,
                'name' => $this->accountManager->name,
            ]),
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
            ]),
            'timeline' => app(OrderService::class)->timeline($this->resource),
            'history' => $this->whenLoaded('statusHistory', function () {
                return $this->statusHistory->map(function ($item) {
                    $to = $item->to_status instanceof OrderStatus
                        ? $item->to_status
                        : OrderStatus::from((string) $item->to_status);

                    return [
                        'from_status' => $item->from_status instanceof OrderStatus
                            ? $item->from_status->value
                            : $item->from_status,
                        'to_status' => $to->value,
                        'to_status_label' => $to->label(),
                        'changed_by' => $item->changedBy === null ? null : [
                            'id' => $item->changedBy->id,
                            'name' => $item->changedBy->name,
                        ],
                        'created_at' => $item->created_at?->toIso8601String(),
                    ];
                })->values()->all();
            }),
        ];
    }
}
