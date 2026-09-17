<?php

namespace Database\Factories;

use App\Enums\SupplierVisibility;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierDocument>
 */
class SupplierDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true).'.pdf';

        return [
            'supplier_id' => Supplier::factory(),
            'title' => fake()->sentence(3),
            'category' => fake()->optional()->randomElement(['license', 'contract', 'catalog', 'other']),
            'disk' => 'public',
            'path' => 'suppliers/documents/'.fake()->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
            'visibility' => SupplierVisibility::Internal,
            'metadata' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
