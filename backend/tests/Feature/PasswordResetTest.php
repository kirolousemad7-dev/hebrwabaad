<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_always_returns_the_same_success_payload(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);
        $unknown = $this->postJson('/api/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $known->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.status', 'accepted');
        $unknown->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.status', 'accepted');
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_email_unknown_or_inactive_accounts(): void
    {
        Notification::fake();

        User::factory()->inactive()->create(['email' => 'inactive@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'inactive@example.com'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_reset_link_points_at_the_frontend_and_expires_with_the_broker(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.hebr.test', 'auth.passwords.users.expire' => 60]);

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $url = $notification->resetUrl($user);

            $this->assertStringStartsWith('https://app.hebr.test/reset-password?', $url);
            $this->assertStringContainsString('token=', $url);
            $this->assertStringContainsString('email=reset%40example.com', $url);
            $this->assertSame(60, (int) config('auth.passwords.users.expire'));

            return true;
        });
    }

    public function test_valid_token_resets_password_and_revokes_existing_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-ok@example.com',
            'password' => 'old-password-123',
        ]);
        $existingToken = $user->createToken('auth')->plainTextToken;

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $resetToken = $this->resetTokenFor($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reset');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('old-password-123', $user->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($existingToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'once@example.com']);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
        $resetToken = $this->resetTokenFor($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'first-pass-123',
            'password_confirmation' => 'first-pass-123',
        ])->assertOk();

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'second-pass-123',
            'password_confirmation' => 'second-pass-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to reset password.');
    }

    public function test_invalid_token_and_mismatched_confirmation_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'bad-token@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unable to reset password.');

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_inactive_account_cannot_reset_password(): void
    {
        Notification::fake();

        $user = User::factory()->inactive()->create([
            'email' => 'locked@example.com',
            'password' => 'old-password-123',
        ]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unable to reset password.');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    private function resetTokenFor(User $user): string
    {
        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertIsString($token);

        return $token;
    }
}
