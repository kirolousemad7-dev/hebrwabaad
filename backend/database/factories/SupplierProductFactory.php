<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\SupplierVisibility;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierProduct>
 */
class SupplierProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'supplier_id' => Supplier::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'category' => 'علب',
            'contact_for_price' => true,
            'availability' => 'CONTACT',
            'status' => ContentStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
            'visibility' => SupplierVisibility::Public,
            'published_at' => now(),
            'is_featured' => false,
        ]);
    }
}
