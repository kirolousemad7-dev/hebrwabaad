<?php

namespace Database\Factories;

use App\Enums\CommercialQuotationStatus;
use App\Enums\PrintingPaymentPolicy;
use App\Models\CommercialQuotation;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommercialQuotation>
 */
class CommercialQuotationFactory extends Factory
{
    protected $model = CommercialQuotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CQ-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'revision' => 1,
            'quote_request_id' => QuoteRequest::factory(),
            'customer_id' => fn (array $attrs) => QuoteRequest::query()->find($attrs['quote_request_id'])?->customer_id
                ?? User::factory()->create(['role' => 'CUSTOMER'])->id,
            'order_id' => null,
            'created_by' => User::factory()->create(['role' => 'OWNER'])->id,
            'status' => CommercialQuotationStatus::Draft,
            'currency' => 'SAR',
            'subtotal' => '1000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'shipping_amount' => '0.00',
            'rental_amount' => '0.00',
            'total' => '1000.00',
            'deposit_required' => '0.00',
            'payment_policy' => PrintingPaymentPolicy::None,
            'valid_until' => now()->addDays(14)->toDateString(),
            'execution_duration' => '14 يوم',
            'revision_count' => 2,
            'notes' => null,
            'terms' => 'الشروط والأحكام',
            'delivery_terms' => null,
            'internal_notes' => 'ملاحظات داخلية',
            'snapshot' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(function (): array {
            $raw = str_repeat('b', 64);

            return [
                'status' => CommercialQuotationStatus::Sent,
                'sent_at' => now(),
                'public_token_hash' => CommercialQuotation::hashToken($raw),
                'public_token_hint' => substr($raw, -8),
            ];
        });
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => CommercialQuotationStatus::Accepted,
            'accepted_at' => now(),
            'sent_at' => now()->subDay(),
            'snapshot' => [
                'reference' => 'CQ-TEST',
                'total' => '1000.00',
                'items' => [],
                'payment_policy' => 'NONE',
            ],
        ]);
    }
}
