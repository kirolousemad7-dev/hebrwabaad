<?php

namespace Database\Factories;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRequest>
 */
class EventRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_type' => 'افتتاح',
            'event_date' => now()->addMonth()->toDateString(),
            'city' => 'الرياض',
            'attendance' => 100,
            'venue' => null,
            'budget_range' => '10000_25000',
            'buy_or_rent' => 'rent',
            'notes' => null,
            'status' => EventRequest::STATUS_PENDING,
        ];
    }
}
