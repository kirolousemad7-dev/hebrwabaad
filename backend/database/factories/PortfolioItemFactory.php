<?php

namespace Database\Factories;

use App\Enums\PortfolioCategory;
use App\Models\PortfolioItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortfolioItem>
 */
class PortfolioItemFactory extends Factory
{
    protected $model = PortfolioItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'نموذج عرض — '.$this->faker->unique()->words(2, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numerify('###'),
            'category' => fake()->randomElement(PortfolioCategory::cases()),
            'description' => 'نموذج بصري للعرض فقط، وليس مشروعاً لعميل حقيقي.',
            'short_description' => 'نموذج مختصر للمعرض.',
            'tags' => ['نموذج عرض'],
            'image_url' => '/brand/logo.png',
            'is_sample' => true,
            'is_published' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
