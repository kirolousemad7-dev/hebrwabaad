<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => function (array $attributes) {
                $order = Order::query()->find($attributes['order_id']);

                return $order?->customer_id ?? User::factory();
            },
            'amount' => '1500.00',
            'currency' => 'SAR',
            'payment_method' => PaymentMethod::Card,
            'status' => PaymentStatus::Pending,
            'provider' => PaymentMethod::Card->provider(),
        ];
    }

    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Card,
            'provider' => PaymentMethod::Card->provider(),
        ]);
    }

    public function instapay(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Instapay,
            'provider' => PaymentMethod::Instapay->provider(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Instapay,
            'provider' => PaymentMethod::Instapay->provider(),
            'status' => PaymentStatus::PendingVerification,
            'reference_number' => 'IP-'.fake()->numerify('######'),
        ]);
    }
}
