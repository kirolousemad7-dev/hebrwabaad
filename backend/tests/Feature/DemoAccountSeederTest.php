<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PaymentSetting;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_synthetic_review_accounts_and_is_idempotent(): void
    {
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'owner.demo@hebr.test')->count());
        $this->assertSame(UserRole::Owner, User::query()->where('email', 'owner.demo@hebr.test')->first()?->role);
        $this->assertSame(UserRole::Customer, User::query()->where('email', 'customer.demo@hebr.test')->first()?->role);
        $this->assertSame(UserRole::AccountManager, User::query()->where('email', 'manager.demo@hebr.test')->first()?->role);
        $this->assertSame(UserRole::GraphicDesigner, User::query()->where('email', 'employee.demo@hebr.test')->first()?->role);

        $settings = PaymentSetting::current();
        $this->assertTrue($settings->bank_transfer_enabled);
        $this->assertSame('00000000000000', $settings->bank_account_number);
    }

    public function test_it_does_not_overwrite_an_existing_demo_password(): void
    {
        $owner = User::factory()->owner()->create([
            'email' => 'owner.demo@hebr.test',
            'password' => 'OriginalPass123!',
        ]);

        $this->seed(DemoAccountSeeder::class);

        $this->assertTrue(Hash::check('OriginalPass123!', $owner->fresh()?->password ?? ''));
    }
}
