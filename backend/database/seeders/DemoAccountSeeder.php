<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    /**
     * Synthetic review accounts for a shared demo. Not real customers.
     */
    public function run(): void
    {
        $password = (string) env('DEMO_PASSWORD', 'DemoPass123!');

        $accounts = [
            [
                'email' => 'owner.demo@hebr.test',
                'name' => 'مالك تجريبي',
                'role' => UserRole::Owner,
            ],
            [
                'email' => 'customer.demo@hebr.test',
                'name' => 'عميل تجريبي',
                'role' => UserRole::Customer,
            ],
            [
                'email' => 'manager.demo@hebr.test',
                'name' => 'مدير حساب تجريبي',
                'role' => UserRole::AccountManager,
            ],
            [
                'email' => 'employee.demo@hebr.test',
                'name' => 'موظف تجريبي',
                'role' => UserRole::GraphicDesigner,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);
            $user->fill([
                'name' => $account['name'],
                'role' => $account['role'],
                'is_active' => true,
            ]);

            if (! $user->exists) {
                $user->password = $password;
            }

            $user->save();
        }

        $settings = PaymentSetting::current();

        if (! $settings->bank_transfer_enabled && blank($settings->bank_account_number)) {
            $settings->fill([
                'bank_transfer_enabled' => true,
                'bank_name' => 'بنك تجريبي',
                'bank_account_name' => 'حبر وأبعاد — حساب تجريبي',
                'bank_account_number' => '00000000000000',
                'bank_iban' => 'SA0000000000000000000000',
                'bank_instructions' => 'هذا حساب تجريبي للمراجعة فقط. أدخل أي رقم حوالة بعد التحويل.',
            ]);
        }

        if ($settings->instapay_enabled && blank($settings->instapay_account_name)) {
            $settings->fill([
                'instapay_account_name' => 'حبر وأبعاد — حساب تجريبي',
                'instapay_bank_name' => 'بنك تجريبي',
                'instapay_account_number' => 'SA0000000000000000000000',
                'instapay_handle' => '@hebr-demo',
                'instapay_instructions' => 'هذا حساب إنستاباي تجريبي للمراجعة فقط. أدخل أي رقم عملية بعد التحويل.',
            ]);
        }

        $settings->save();
    }
}
