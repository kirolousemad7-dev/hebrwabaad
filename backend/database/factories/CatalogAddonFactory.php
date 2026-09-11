<?php

namespace Database\Factories;

use App\Enums\CatalogPricingMode;
use App\Models\CatalogAddon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogAddon>
 */
class CatalogAddonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'name' => $name,
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'pricing_mode' => CatalogPricingMode::Quote,
            'price' => null,
            'currency' => 'SAR',
            'is_urgent' => false,
            'requires_capacity' => false,
            'capacity_available' => false,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    public function urgentUnavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_urgent' => true,
            'requires_capacity' => true,
            'capacity_available' => false,
        ]);
    }
}
