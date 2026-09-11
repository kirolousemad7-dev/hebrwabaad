<?php

namespace Database\Factories;

use App\Models\RecommendationGoal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecommendationGoal>
 */
class RecommendationGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name_ar' => $name,
            'explanation' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
