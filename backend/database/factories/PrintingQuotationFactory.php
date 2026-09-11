<?php

namespace Database\Factories;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingQuotationStatus;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintingQuotation>
 */
class PrintingQuotationFactory extends Factory
{
    protected $model = PrintingQuotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = '500.00';
        $tax = '0.00';
        $discount = '0.00';
        $total = '500.00';

        return [
            'reference' => 'PQ-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'revision' => 1,
            'printing_request_id' => PrintingRequest::factory(),
            'customer_id' => function (array $attributes) {
                $request = PrintingRequest::query()->find($attributes['printing_request_id']);

                return $request?->user_id ?? User::factory();
            },
            'created_by' => User::factory()->printingSpecialist(),
            'status' => PrintingQuotationStatus::Draft,
            'currency' => 'SAR',
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total' => $total,
            'deposit_required' => null,
            'payment_policy' => PrintingPaymentPolicy::Full,
            'valid_until' => now()->addDays(14)->toDateString(),
            'notes' => null,
            'terms' => null,
            'rejection_reason' => null,
            'public_token_hash' => null,
            'public_token_hint' => null,
            'token_revoked_at' => null,
            'sent_at' => null,
            'viewed_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'expired_at' => null,
            'supersedes_id' => null,
            'snapshot' => null,
            'tracking_token_hash' => null,
            'tracking_token_hint' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(function (array $attributes): array {
            $raw = str_repeat('a', 64);

            return [
                'status' => PrintingQuotationStatus::Sent,
                'sent_at' => now(),
                'public_token_hash' => PrintingQuotation::hashToken($raw),
                'public_token_hint' => substr($raw, -8),
            ];
        });
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PrintingQuotationStatus::Accepted,
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
            'snapshot' => [
                'reference' => $attributes['reference'] ?? 'PQ-TEST',
                'total' => $attributes['total'] ?? '500.00',
                'currency' => $attributes['currency'] ?? 'SAR',
            ],
        ]);
    }
}
