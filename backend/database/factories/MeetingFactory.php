<?php

namespace Database\Factories;

use App\Enums\MeetingProvider;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'provider' => MeetingProvider::None,
            'meeting_id' => null,
            'join_url' => null,
            'host_url' => null,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'timezone' => 'Africa/Cairo',
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'status' => MeetingStatus::Scheduled,
            'external_event_id' => null,
            'created_by' => User::factory()->accountManager(),
            'include_customer' => false,
            'meta' => [],
        ];
    }

    public function zoom(): static
    {
        return $this->state(fn (): array => [
            'provider' => MeetingProvider::Zoom,
            'meeting_id' => (string) fake()->numerify('########'),
            'join_url' => 'https://zoom.us/j/'.fake()->numerify('########'),
            'host_url' => 'https://zoom.us/s/'.fake()->numerify('########'),
        ]);
    }
}
