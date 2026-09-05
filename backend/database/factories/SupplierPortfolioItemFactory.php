<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPortfolioItem>
 */
class SupplierPortfolioItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'image' => '/suppliers/portfolio/cards-1.svg',
            'category' => 'كروت شخصية',
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
