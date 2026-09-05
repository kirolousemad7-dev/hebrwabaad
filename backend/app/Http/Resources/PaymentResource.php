<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function __construct($resource, private readonly bool $forOwner = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof PaymentStatus
            ? $this->status
            : PaymentStatus::from((string) $this->status);
        $method = $this->payment_method instanceof PaymentMethod
            ? $this->payment_method
            : PaymentMethod::from((string) $this->payment_method);

        return [
            'id' => $this->id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'currency' => $this->currency,
            'payment_method' => $method->value,
            'payment_method_label' => $method->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'provider' => $this->provider,
            'provider_transaction_id' => $this->provider_transaction_id,
            'reference_number' => $this->reference_number,
            'payer_name' => $this->payer_name,
            'notes' => $this->when($this->forOwner, $this->notes),
            'failure_reason' => $this->failure_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->when($this->forOwner, $this->customer->email),
            ]),
            'order' => $this->whenLoaded('order', fn () => $this->order === null ? null : [
                'id' => $this->order->id,
                'reference' => $this->order->reference,
                'title' => $this->order->title,
                'project' => $this->order->project === null ? null : [
                    'id' => $this->order->project->id,
                    'title' => $this->order->project->title,
                ],
            ]),
            'verified_by' => $this->when($this->forOwner, function () {
                if (! $this->relationLoaded('verifier') || $this->verifier === null) {
                    return null;
                }

                return [
                    'id' => $this->verifier->id,
                    'name' => $this->verifier->name,
                ];
            }),
        ];
    }
}
