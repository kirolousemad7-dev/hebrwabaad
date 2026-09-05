<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'assigned_to' => User::factory()->webDeveloper(),
            'created_by' => User::factory()->accountManager(),
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Todo,
            'deadline' => now()->addDays(7)->toDateString(),
        ];
    }
}
