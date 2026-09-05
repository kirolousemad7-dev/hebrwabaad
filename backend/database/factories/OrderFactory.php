<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'HEBR-TST-'.fake()->unique()->numerify('######'),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'customer_id' => User::factory(),
            'account_manager_id' => User::factory()->accountManager(),
            'status' => OrderStatus::Received,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            if ($order->statusHistory()->doesntExist()) {
                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => $order->status instanceof OrderStatus
                        ? $order->status->value
                        : (string) $order->status,
                    'changed_by' => $order->account_manager_id,
                ]);
            }
        });
    }
}
