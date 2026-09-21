<?php

namespace Database\Factories;

use App\Enums\MarketingSectionType;
use App\Models\MarketingSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingSection>
 */
class MarketingSectionFactory extends Factory
{
    protected $model = MarketingSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'key' => $key,
            'admin_title' => fake()->sentence(3),
            'type' => MarketingSectionType::Custom,
            'is_enabled' => true,
            'sort_order' => fake()->numberBetween(0, 50),
            'config' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
