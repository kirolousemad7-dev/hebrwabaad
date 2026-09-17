<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierContact>
 */
class SupplierContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->name(),
            'position' => fake()->optional()->jobTitle(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('05########'),
            'whatsapp' => fake()->optional()->numerify('05########'),
            'is_primary' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
