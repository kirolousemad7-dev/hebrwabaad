<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'HEBR-CS-TST-'.fake()->unique()->numerify('######'),
            'subject' => fake()->sentence(4),
            'status' => ConversationStatus::Open,
            'customer_id' => User::factory(),
            'assigned_to' => User::factory()->accountManager(),
            'last_message_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Closed,
        ]);
    }
}
