<?php

namespace Database\Factories;

use App\Enums\QuoteRequestSource;
use App\Enums\QuoteRequestStatus;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteRequest>
 */
class QuoteRequestFactory extends Factory
{
    protected $model = QuoteRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'QR-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => User::factory()->create(['role' => 'CUSTOMER'])->id,
            'order_id' => null,
            'source_type' => QuoteRequestSource::Service,
            'source_id' => fake()->numberBetween(1, 100),
            'title' => fake()->sentence(3),
            'status' => QuoteRequestStatus::New,
            'assigned_to' => null,
            'requested_at' => now(),
            'required_date' => now()->addDays(14)->toDateString(),
            'budget_min' => null,
            'budget_max' => null,
            'city' => 'الرياض',
            'customer_notes' => fake()->optional()->sentence(),
            'internal_notes' => null,
            'information_request' => null,
            'payload' => [
                'line_items' => [
                    [
                        'description' => 'خدمة تسويقية',
                        'quantity' => 1,
                        'unit_price' => '0.00',
                        'category' => 'CREATIVE',
                    ],
                ],
            ],
            'quotation_type' => 'COMMERCIAL',
            'quotation_id' => null,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => QuoteRequestStatus::UnderReview,
        ]);
    }
}
