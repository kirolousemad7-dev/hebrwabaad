<?php

namespace Database\Factories;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarSource;
use App\Enums\CalendarVisibility;
use App\Models\CalendarItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarItem>
 */
class CalendarItemFactory extends Factory
{
    protected $model = CalendarItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = now()->addDay()->setTime(10, 0);

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => CalendarItemType::Meeting,
            'status' => CalendarItemStatus::Scheduled,
            'priority' => CalendarItemPriority::Medium,
            'visibility' => CalendarVisibility::Participants,
            'source' => CalendarSource::Manual,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'all_day' => false,
            'created_by' => User::factory()->owner(),
        ];
    }
}
