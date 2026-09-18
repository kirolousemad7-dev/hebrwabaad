<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => User::factory()->state(['role' => UserRole::Customer]),
            'crm_company_id' => null,
            'commercial_quotation_id' => null,
            'order_id' => null,
            'project_id' => null,
            'created_by' => User::factory()->owner(),
            'status' => InvoiceStatus::Draft,
            'currency' => 'SAR',
            'issue_date' => null,
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal' => '1000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => '1000.00',
            'amount_paid' => '0.00',
            'amount_due' => '1000.00',
            'notes' => null,
            'terms' => 'الشروط والأحكام',
            'internal_notes' => 'ملاحظات داخلية',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Issued,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'sent_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->subDays(30)->toDateString(),
            'issued_at' => now()->subDays(30),
            'sent_at' => now()->subDays(30),
            'due_date' => now()->subDay()->toDateString(),
        ]);
    }
}
