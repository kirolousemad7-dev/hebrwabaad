<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'service_id' => null,
            'description' => 'خدمة تصميم',
            'quantity' => '1.00',
            'unit_price' => '1000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'line_total' => '1000.00',
            'sort_order' => 0,
        ];
    }
}
