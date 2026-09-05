<?php

namespace Database\Factories;

use App\Enums\CatalogPricingMode;
use App\Enums\PackageCategory;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(PackageCategory::cases()),
            'price' => fake()->numberBetween(2000, 40000),
            'discount_amount' => 0,
            'currency' => 'SAR',
            'pricing_mode' => CatalogPricingMode::Fixed,
            'duration_days' => fake()->numberBetween(7, 60),
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    /**
     * A package the owner has not priced yet.
     */
    public function quote(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'discount_amount' => 0,
            'pricing_mode' => CatalogPricingMode::Quote,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
