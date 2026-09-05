<?php

namespace Database\Factories;

use App\Enums\PrintingDimensionUnit;
use App\Enums\PrintingMethod;
use App\Enums\PrintingRequestStatus;
use App\Enums\PrintingShape;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintingRequest>
 */
class PrintingRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_slug' => 'standard-business-cards',
            'product_name' => 'كروت شخصية قياسية',
            'width' => 9,
            'height' => 5,
            'dimension_unit' => PrintingDimensionUnit::Centimeter,
            'shape' => PrintingShape::Rectangle,
            'material' => 'ورق مطفي',
            'quantity' => 100,
            'printing_method' => PrintingMethod::Digital,
            'finishing' => ['NONE'],
            'file_path' => 'printing-requests/1/design.pdf',
            'original_filename' => 'design.pdf',
            'required_date' => now('Asia/Riyadh')->addDays(7)->toDateString(),
            'notes' => null,
            'status' => PrintingRequestStatus::Pending,
            'pricing_type' => null,
            'estimated_price' => null,
            'quoted_price' => null,
            'pricing_notes' => null,
            'quoted_at' => null,
            'quoted_by' => null,
        ];
    }

    public function customProduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_slug' => 'custom-printed-product',
            'product_name' => 'منتج مطبوع حسب الطلب',
        ]);
    }
}
