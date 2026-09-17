<?php

namespace Tests\Feature;

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Mail\SupplierLoginOtpMail;
use App\Models\Supplier;
use App\Models\SupplierLoginOtp;
use App\Models\User;
use App\Notifications\SupplierRegistrationPendingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupplierPortalOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_supplier_can_self_register_as_pending_and_notifies_owner(): void
    {
        Notification::fake();
        Mail::fake();
        $owner = User::factory()->owner()->create();

        $response = $this->postJson('/api/supplier/register', [
            'company_name' => 'مطبعة الأفق',
            'contact_person' => 'أحمد',
            'email' => 'supplier.new@example.com',
            'phone' => '0500000000',
            'whatsapp' => '0500000000',
            'country' => 'السعودية',
            'city' => 'الرياض',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'category' => 'طباعة',
            'services' => ['كروت'],
            'short_description' => 'مورد طباعة',
        ])->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Supplier->value)
            ->assertJsonPath('data.supplier.status', SupplierStatus::Pending->value)
            ->assertJsonPath('data.supplier.verification_status', 'UNVERIFIED');

        $this->assertDatabaseHas('users', [
            'email' => 'supplier.new@example.com',
            'role' => UserRole::Supplier->value,
        ]);

        Notification::assertSentTo($owner, SupplierRegistrationPendingNotification::class);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_owner_can_approve_reject_request_changes_and_block(): void
    {
        $owner = User::factory()->owner()->create();
        $supplierUser = User::factory()->supplier()->create();
        $supplier = Supplier::factory()->unpublished()->create([
            'user_id' => $supplierUser->id,
            'status' => SupplierStatus::Pending,
            'email' => $supplierUser->email,
        ]);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', SupplierStatus::Active->value)
            ->assertJsonPath('data.is_active', true);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/request-changes', [
            'notes' => 'أضف السجل التجاري',
        ])->assertOk()
            ->assertJsonPath('data.owner_change_request', 'أضف السجل التجاري');

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/reject', [
            'notes' => 'بيانات ناقصة',
        ])->assertOk()
            ->assertJsonPath('data.status', SupplierStatus::Rejected->value);

        $this->asUser($owner)->postJson('/api/admin/suppliers/'.$supplier->id.'/block', [
            'notes' => 'مخالفة',
        ])->assertOk()
            ->assertJsonPath('data.status', SupplierStatus::Blocked->value);

        $supplierUser->refresh();
        $this->assertFalse($supplierUser->is_active);
    }

    public function test_supplier_password_and_otp_login_work_and_isolation_holds(): void
    {
        Mail::fake();

        $aUser = User::factory()->supplier()->create([
            'email' => 'a@supplier.test',
            'password' => 'Password123!',
        ]);
        $a = Supplier::factory()->create([
            'user_id' => $aUser->id,
            'status' => SupplierStatus::Active,
            'is_active' => true,
        ]);

        $bUser = User::factory()->supplier()->create(['email' => 'b@supplier.test']);
        $b = Supplier::factory()->create([
            'user_id' => $bUser->id,
            'status' => SupplierStatus::Active,
            'is_active' => true,
        ]);
        $bContact = $b->contacts()->create(['name' => 'سر', 'is_primary' => true]);

        $login = $this->postJson('/api/supplier/login', [
            'email' => 'a@supplier.test',
            'password' => 'Password123!',
        ])->assertOk();

        $token = $login->json('data.token');
        $this->withToken($token)
            ->getJson('/api/supplier/dashboard')
            ->assertOk()
            ->assertJsonPath('data.supplier.id', $a->id);

        $this->withToken($token)
            ->putJson('/api/supplier/contacts/'.$bContact->id, ['name' => 'اختراق'])
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/admin/suppliers')
            ->assertForbidden();

        $this->postJson('/api/supplier/otp/request', ['email' => 'a@supplier.test'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        Mail::assertQueued(SupplierLoginOtpMail::class);

        $otp = SupplierLoginOtp::query()->where('email', 'a@supplier.test')->latest('id')->firstOrFail();
        // Recover plaintext by re-hashing known code in test via DB overwrite:
        SupplierLoginOtp::query()->whereKey($otp->id)->update([
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        $this->postJson('/api/supplier/otp/verify', [
            'email' => 'a@supplier.test',
            'code' => '123456',
        ])->assertOk()
            ->assertJsonPath('data.user.role', UserRole::Supplier->value);
    }

    public function test_owner_locked_fields_cannot_be_updated_by_supplier(): void
    {
        $owner = User::factory()->owner()->create();
        $supplierUser = User::factory()->supplier()->create();
        $supplier = Supplier::factory()->unpublished()->create([
            'user_id' => $supplierUser->id,
            'name' => 'اسم أصلي',
            'short_description' => 'وصف',
            'status' => SupplierStatus::Active,
            'is_active' => true,
            'is_published' => false,
        ]);

        $this->asUser($owner)->putJson('/api/admin/suppliers/'.$supplier->id.'/locked-fields', [
            'locked_fields' => ['name'],
        ])->assertOk()
            ->assertJsonPath('data.locked_fields.0', 'name');

        $this->asUser($supplierUser)->putJson('/api/supplier/profile', [
            'name' => 'محاولة تغيير',
            'short_description' => 'وصف محدث',
            'location' => 'جدة',
        ])->assertOk();

        $supplier->refresh();
        $this->assertSame('اسم أصلي', $supplier->name);
        $this->assertSame('وصف محدث', $supplier->short_description);
    }

    public function test_customer_and_owner_cannot_use_supplier_portal_routes(): void
    {
        $customer = User::factory()->create();
        $owner = User::factory()->owner()->create();

        $this->asUser($customer)->getJson('/api/supplier/dashboard')->assertForbidden();
        $this->asUser($owner)->getJson('/api/supplier/dashboard')->assertForbidden();

        $this->postJson('/api/supplier/login', [
            'email' => $owner->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_profile_completion_endpoint_returns_percent(): void
    {
        $supplierUser = User::factory()->supplier()->create();
        Supplier::factory()->create([
            'user_id' => $supplierUser->id,
            'status' => SupplierStatus::Active,
            'is_active' => true,
            'short_description' => 'وصف',
            'country' => 'السعودية',
            'city' => 'الرياض',
            'email' => $supplierUser->email,
            'phone' => '0500',
            'contact_person' => 'شخص',
        ]);

        $this->asUser($supplierUser)
            ->getJson('/api/supplier/completion')
            ->assertOk()
            ->assertJsonStructure(['data' => ['percent', 'sections', 'missing']]);
    }
}
