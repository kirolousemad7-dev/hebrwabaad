<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackageTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageTier>
 */
class PackageTierFactory extends Factory
{
    protected $model = PackageTier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'name' => 'أساسية',
            'slug' => 'basic-'.fake()->unique()->numberBetween(1, 999999),
            'price' => null,
            'currency' => 'SAR',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function priced(float $price = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
