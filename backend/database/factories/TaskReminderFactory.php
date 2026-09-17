<?php

namespace Database\Factories;

use App\Enums\CalendarReminderOffset;
use App\Models\Task;
use App\Models\TaskReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReminder>
 */
class TaskReminderFactory extends Factory
{
    protected $model = TaskReminder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'offset' => CalendarReminderOffset::Minutes15->value,
            'custom_minutes' => null,
            'remind_at' => now()->subMinute(),
            'sent_at' => null,
        ];
    }
}
