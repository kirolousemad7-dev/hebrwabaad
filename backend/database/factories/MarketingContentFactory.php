<?php

namespace Database\Factories;

use App\Models\MarketingContent;
use App\Models\MarketingSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingContent>
 */
class MarketingContentFactory extends Factory
{
    protected $model = MarketingContent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marketing_section_id' => MarketingSection::factory(),
            'content_key' => fake()->unique()->slug(2),
            'value_text' => fake()->sentence(),
            'value_html' => null,
            'media_id' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_enabled' => true,
            'metadata' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }
}
