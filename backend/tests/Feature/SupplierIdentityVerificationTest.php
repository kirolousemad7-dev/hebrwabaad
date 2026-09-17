<?php

namespace Tests\Feature;

use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\UserRole;
use App\Mail\SupplierEmailVerificationMail;
use App\Mail\SupplierLoginOtpMail;
use App\Models\PhoneVerificationOtp;
use App\Models\Supplier;
use App\Models\SupplierLoginOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SupplierIdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{user: User, supplier: Supplier}
     */
    private function makeSupplierAccount(array $userAttrs = [], array $supplierAttrs = []): array
    {
        $user = User::factory()->supplier()->create(array_merge([
            'email' => 'verify@supplier.test',
            'email_verified_at' => null,
            'password' => 'Password123!',
        ], $userAttrs));

        $supplier = Supplier::factory()->create(array_merge([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '0501112233',
            'status' => SupplierStatus::Active,
            'is_active' => true,
            'verification_status' => SupplierVerificationStatus::Unverified,
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ], $supplierAttrs));

        return ['user' => $user, 'supplier' => $supplier];
    }

    public function test_registration_sends_verification_email_and_status_is_pending(): void
    {
        Mail::fake();
        User::factory()->owner()->create();

        $this->postJson('/api/supplier/register', [
            'company_name' => 'مورد التحقق',
            'contact_person' => 'سارة',
            'email' => 'new.verify@supplier.test',
            'phone' => '0509998877',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'short_description' => 'وصف',
            'services' => ['طباعة'],
        ])->assertCreated();

        Mail::assertQueued(SupplierEmailVerificationMail::class);

        $user = User::query()->where('email', 'new.verify@supplier.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->email_verification_sent_at);

        $this->asUser($user)
            ->getJson('/api/supplier/email/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.verified', false);
    }

    public function test_email_verification_link_succeeds_and_marks_verified(): void
    {
        Mail::fake();
        ['user' => $user, 'supplier' => $supplier] = $this->makeSupplierAccount();

        $user->forceFill(['email_verification_sent_at' => now()])->save();

        $url = URL::temporarySignedRoute(
            'supplier.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertNotNull($supplier->refresh()->email_verified_at);
        $this->assertSame(
            SupplierVerificationStatus::EmailVerified,
            $supplier->verification_status,
        );
    }

    public function test_expired_email_verification_link_is_rejected(): void
    {
        ['user' => $user] = $this->makeSupplierAccount();
        $user->forceFill(['email_verification_sent_at' => now()->subHours(2)])->save();

        $url = URL::temporarySignedRoute(
            'supplier.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->getJson($url)->assertStatus(422);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_invalid_signature_email_verification_is_forbidden(): void
    {
        ['user' => $user] = $this->makeSupplierAccount();

        $this->getJson('/api/supplier/email/verify?id='.$user->id.'&hash='.sha1($user->email).'&signature=bad&expires=9999999999')
            ->assertForbidden();
    }

    public function test_email_resend_cooldown_and_status_expired(): void
    {
        Mail::fake();
        ['user' => $user] = $this->makeSupplierAccount();
        $user->forceFill(['email_verification_sent_at' => now()])->save();

        $this->asUser($user)
            ->postJson('/api/supplier/email/resend')
            ->assertStatus(429);

        $user->forceFill(['email_verification_sent_at' => now()->subHours(2)])->save();

        $this->asUser($user)
            ->getJson('/api/supplier/email/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->asUser($user)
            ->postJson('/api/supplier/email/resend')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        Mail::assertQueued(SupplierEmailVerificationMail::class);
    }

    public function test_phone_otp_correct_code_wrong_code_expiration_and_reuse(): void
    {
        config(['sms.default' => 'null']);

        ['user' => $user, 'supplier' => $supplier] = $this->makeSupplierAccount();

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/request', ['phone' => '0501112233'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $otp = PhoneVerificationOtp::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        PhoneVerificationOtp::query()->whereKey($otp->id)->update([
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/verify', ['code' => '000000', 'phone' => '0501112233'])
            ->assertStatus(422);

        $otp->refresh();
        $this->assertSame(1, $otp->attempts);

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/verify', ['code' => '123456', 'phone' => '0501112233'])
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $supplier->refresh();
        $this->assertNotNull($supplier->phone_verified_at);
        $this->assertContains(
            $supplier->verification_status,
            [SupplierVerificationStatus::PhoneVerified, SupplierVerificationStatus::FullyVerified],
        );

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/verify', ['code' => '123456', 'phone' => '0501112233'])
            ->assertStatus(422);

        PhoneVerificationOtp::query()->create([
            'user_id' => $user->id,
            'phone' => '0501112233',
            'code_hash' => Hash::make('654321'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
            'sent_at' => now()->subMinutes(5),
            'consumed_at' => null,
        ]);

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/verify', ['code' => '654321', 'phone' => '0501112233'])
            ->assertStatus(422);
    }

    public function test_phone_otp_attempt_limit_blocks_brute_force(): void
    {
        config(['sms.default' => 'null']);

        ['user' => $user] = $this->makeSupplierAccount(['email' => 'brute@supplier.test']);

        PhoneVerificationOtp::query()->create([
            'user_id' => $user->id,
            'phone' => '0501112233',
            'code_hash' => Hash::make('111111'),
            'attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
            'consumed_at' => null,
        ]);

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/verify', ['code' => '111111'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'تم تجاوز عدد المحاولات. اطلب رمزاً جديداً.');
    }

    public function test_email_otp_login_anti_enumeration_expiration_wrong_code_and_reuse(): void
    {
        Mail::fake();

        ['user' => $user] = $this->makeSupplierAccount([
            'email' => 'otp.login@supplier.test',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/supplier/otp/request', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        Mail::assertNothingQueued();

        $this->postJson('/api/supplier/otp/request', ['email' => 'otp.login@supplier.test'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        Mail::assertQueued(SupplierLoginOtpMail::class);

        $otp = SupplierLoginOtp::query()->where('email', 'otp.login@supplier.test')->latest('id')->firstOrFail();
        SupplierLoginOtp::query()->whereKey($otp->id)->update([
            'code_hash' => Hash::make('424242'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        $this->postJson('/api/supplier/otp/verify', [
            'email' => 'otp.login@supplier.test',
            'code' => '000000',
        ])->assertStatus(422);

        $login = $this->postJson('/api/supplier/otp/verify', [
            'email' => 'otp.login@supplier.test',
            'code' => '424242',
        ])->assertOk();

        $this->assertNotEmpty($login->json('data.token'));
        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->postJson('/api/supplier/otp/verify', [
            'email' => 'otp.login@supplier.test',
            'code' => '424242',
        ])->assertStatus(422);

        SupplierLoginOtp::query()->create([
            'email' => 'otp.login@supplier.test',
            'code_hash' => Hash::make('999999'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
            'sent_at' => now()->subMinutes(5),
            'consumed_at' => null,
        ]);

        $this->postJson('/api/supplier/otp/verify', [
            'email' => 'otp.login@supplier.test',
            'code' => '999999',
        ])->assertStatus(422);
    }

    public function test_email_otp_resend_cooldown_is_silent(): void
    {
        Mail::fake();
        $this->makeSupplierAccount(['email' => 'cooldown@supplier.test']);

        $this->postJson('/api/supplier/otp/request', ['email' => 'cooldown@supplier.test'])
            ->assertOk();
        Mail::assertQueued(SupplierLoginOtpMail::class, 1);

        $this->postJson('/api/supplier/otp/request', ['email' => 'cooldown@supplier.test'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        Mail::assertQueued(SupplierLoginOtpMail::class, 1);
    }

    public function test_supplier_can_access_portal_after_identity_verification(): void
    {
        ['user' => $user, 'supplier' => $supplier] = $this->makeSupplierAccount([
            'email' => 'access@supplier.test',
            'email_verified_at' => now(),
        ], [
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'verification_status' => SupplierVerificationStatus::FullyVerified,
            'status' => SupplierStatus::Active,
            'is_active' => true,
        ]);

        $this->asUser($user)
            ->getJson('/api/supplier/dashboard')
            ->assertOk()
            ->assertJsonPath('data.supplier.id', $supplier->id)
            ->assertJsonPath('data.supplier.verification_status', SupplierVerificationStatus::FullyVerified->value)
            ->assertJsonPath('data.access.is_active_supplier', true);

        $this->assertSame(UserRole::Supplier, $user->role);
    }

    public function test_phone_otp_rate_limit_when_forced(): void
    {
        config([
            'testing.force_rate_limits' => true,
            'sms.default' => 'null',
        ]);

        ['user' => $user] = $this->makeSupplierAccount(['email' => 'ratelimit@supplier.test']);

        for ($i = 0; $i < 3; $i++) {
            PhoneVerificationOtp::query()->where('user_id', $user->id)->delete();
            $this->asUser($user)
                ->postJson('/api/supplier/phone/otp/request', ['phone' => '0501112233'])
                ->assertOk();
        }

        $this->asUser($user)
            ->postJson('/api/supplier/phone/otp/request', ['phone' => '0501112233'])
            ->assertStatus(429);
    }
}
