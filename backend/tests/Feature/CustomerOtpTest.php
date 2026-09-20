<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\CustomerLoginOtpMail;
use App\Models\CustomerLoginOtp;
use App\Models\User;
use App\Services\Mail\GmailApiMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_otp_returns_generic_accepted_for_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/otp/request', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('customer_login_otps', 0);
    }

    public function test_request_otp_sends_mail_for_customer_and_never_logs_code_in_response(): void
    {
        Mail::fake();
        $customer = User::factory()->create([
            'email' => 'customer.otp@example.com',
            'role' => UserRole::Customer,
        ]);

        $response = $this->postJson('/api/auth/otp/request', ['email' => $customer->email])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->json();

        $this->assertArrayNotHasKey('code', $response['data'] ?? []);
        $encoded = json_encode($response) ?: '';
        $this->assertStringNotContainsString('654321', $encoded);

        Mail::assertQueued(CustomerLoginOtpMail::class);
        $this->assertDatabaseCount('customer_login_otps', 1);

        $otp = CustomerLoginOtp::query()->firstOrFail();
        $this->assertNotSame('', $otp->code_hash);
    }

    public function test_verify_otp_authenticates_customer(): void
    {
        Mail::fake();
        $customer = User::factory()->create([
            'email' => 'verify.otp@example.com',
            'role' => UserRole::Customer,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/auth/otp/request', ['email' => $customer->email])->assertOk();

        $plain = '654321';
        CustomerLoginOtp::query()->where('email', $customer->email)->update([
            'code_hash' => Hash::make($plain),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        $payload = $this->postJson('/api/auth/otp/verify', [
            'email' => $customer->email,
            'code' => $plain,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $customer->email)
            ->assertJsonPath('data.user.role', 'CUSTOMER')
            ->json('data');

        $this->assertNotEmpty($payload['token']);
        $this->assertNotNull($customer->fresh()?->email_verified_at);

        $this->withToken($payload['token'])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email);
    }

    public function test_wrong_expired_and_reused_otp_are_rejected(): void
    {
        $customer = User::factory()->create([
            'email' => 'fail.otp@example.com',
            'role' => UserRole::Customer,
        ]);

        CustomerLoginOtp::query()->create([
            'email' => $customer->email,
            'code_hash' => Hash::make('111111'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'email' => $customer->email,
            'code' => '000000',
        ])->assertStatus(422);

        CustomerLoginOtp::query()->where('email', $customer->email)->update([
            'expires_at' => now()->subMinute(),
            'code_hash' => Hash::make('222222'),
            'attempts' => 0,
            'consumed_at' => null,
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'email' => $customer->email,
            'code' => '222222',
        ])->assertStatus(422);

        $row = CustomerLoginOtp::query()->create([
            'email' => $customer->email,
            'code_hash' => Hash::make('333333'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'email' => $customer->email,
            'code' => '333333',
        ])->assertOk();

        $this->postJson('/api/auth/otp/verify', [
            'email' => $customer->email,
            'code' => '333333',
        ])->assertStatus(422);

        $this->assertNotNull($row->fresh()?->consumed_at);
    }

    public function test_staff_accounts_do_not_receive_customer_otp(): void
    {
        Mail::fake();
        User::factory()->owner()->create(['email' => 'owner.otp@example.com']);

        $this->postJson('/api/auth/otp/request', ['email' => 'owner.otp@example.com'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('customer_login_otps', 0);
    }

    public function test_gmail_path_used_when_configured_without_queueing_mailable(): void
    {
        Mail::fake();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1'], 200),
        ]);

        config([
            'services.gmail.client_id' => 'gmail-client',
            'services.gmail.client_secret' => 'gmail-secret',
            'services.gmail.refresh_token' => 'gmail-refresh',
            'services.gmail.sender_email' => 'noreply@example.com',
        ]);

        $customer = User::factory()->create([
            'email' => 'gmail.otp@example.com',
            'role' => UserRole::Customer,
        ]);

        $this->postJson('/api/auth/otp/request', ['email' => $customer->email])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        Mail::assertNothingQueued();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail.googleapis.com'));
        $this->assertTrue(app(GmailApiMailer::class)->isConfigured());
    }
}
