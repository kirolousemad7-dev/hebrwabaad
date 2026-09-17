<?php

namespace Database\Factories;

use App\Enums\SupplierPricingModel;
use App\Models\Supplier;
use App\Models\SupplierService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierService>
 */
class SupplierServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'pricing_model' => fake()->randomElement(SupplierPricingModel::cases()),
            'minimum_price' => fake()->optional()->randomFloat(2, 50, 500),
            'maximum_price' => fake()->optional()->randomFloat(2, 500, 5000),
            'currency' => 'SAR',
            'delivery_time' => fake()->optional()->randomElement(['1-3 أيام', 'أسبوع', 'حسب الطلب']),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
