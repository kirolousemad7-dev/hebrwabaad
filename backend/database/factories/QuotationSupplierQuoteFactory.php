<?php

namespace Database\Factories;

use App\Enums\SupplierQuoteStatus;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationItem;
use App\Models\QuotationSupplierQuote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationSupplierQuote>
 */
class QuotationSupplierQuoteFactory extends Factory
{
    protected $model = QuotationSupplierQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quotation = CommercialQuotation::factory()->create();
        $item = CommercialQuotationItem::query()->create([
            'commercial_quotation_id' => $quotation->id,
            'description' => 'بند توريد',
            'quantity' => 1,
            'unit_price' => '5000.00',
            'subtotal' => '5000.00',
            'category' => 'PRODUCTION',
            'sort_order' => 0,
        ]);

        return [
            'commercial_quotation_id' => $quotation->id,
            'commercial_quotation_item_id' => $item->id,
            'supplier_id' => Supplier::factory(),
            'cost' => null,
            'currency' => 'EGP',
            'valid_until' => now()->addDays(14)->toDateString(),
            'delivery_days' => null,
            'notes' => null,
            'attachments' => [],
            'status' => SupplierQuoteStatus::Requested,
            'requested_by' => User::factory()->create(['role' => 'OWNER'])->id,
            'requested_at' => now(),
        ];
    }

    public function received(string $cost = '3000.00'): static
    {
        return $this->state(fn (): array => [
            'status' => SupplierQuoteStatus::Received,
            'cost' => $cost,
            'delivery_days' => 7,
            'notes' => 'عرض مورد',
            'received_at' => now(),
        ]);
    }

    public function selected(string $cost = '3000.00'): static
    {
        return $this->received($cost)->state(fn (): array => [
            'status' => SupplierQuoteStatus::Selected,
            'selected_at' => now(),
        ]);
    }
}
