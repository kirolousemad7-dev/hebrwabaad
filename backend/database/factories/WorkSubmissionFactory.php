<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\PortfolioCategory;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSubmission>
 */
class WorkSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::WebDeveloper]),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => PortfolioCategory::Web,
            'tags' => ['laravel'],
            'tools' => ['React'],
            'client_label' => 'عميل تجريبي',
            'status' => ContentStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Submitted,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }
}
