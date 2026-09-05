<?php

namespace Database\Factories;

use App\Models\PaymentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSetting>
 */
class PaymentSettingFactory extends Factory
{
    protected $model = PaymentSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_enabled' => true,
            'instapay_enabled' => true,
            'instapay_account_name' => 'حبر وأبعاد',
            'instapay_bank_name' => 'البنك الأهلي السعودي',
            'instapay_account_number' => 'SA0000000000000000000000',
            'instapay_instructions' => 'حوّل المبلغ عبر إنستاباي ثم أدخل رقم العملية هنا للمراجعة.',
        ];
    }

    /**
     * Owner-configured bank transfer account.
     */
    public function withBankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_transfer_enabled' => true,
            'bank_name' => 'البنك الأهلي السعودي',
            'bank_account_name' => 'حبر وأبعاد',
            'bank_account_number' => '00000000000000',
            'bank_iban' => 'SA0000000000000000000000',
            'bank_instructions' => 'حوّل المبلغ إلى الحساب البنكي ثم أدخل رقم الحوالة للمراجعة.',
        ]);
    }
}
