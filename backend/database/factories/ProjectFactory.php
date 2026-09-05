<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'customer_id' => User::factory(),
            'account_manager_id' => User::factory()->accountManager(),
            'status' => ProjectStatus::Planning,
            'started_at' => now()->toDateString(),
            'deadline' => now()->addDays(21)->toDateString(),
        ];
    }
}
