<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerAccountAuthTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_owner_can_update_email_without_changing_id_or_role(): void
    {
        $owner = User::factory()->owner()->create([
            'email' => 'hassan@gmail.com',
            'name' => 'Hassan',
        ]);
        $originalId = $owner->id;

        $this->asUser($owner)
            ->putJson('/api/auth/account', [
                'email' => 'hebrwabaad@gmail.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $originalId)
            ->assertJsonPath('data.email', 'hebrwabaad@gmail.com')
            ->assertJsonPath('data.role', UserRole::Owner->value)
            ->assertJsonMissingPath('data.password');

        $owner->refresh();
        $this->assertSame($originalId, $owner->id);
        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertSame('hebrwabaad@gmail.com', $owner->email);
        $this->assertSame('Hassan', $owner->name);
    }

    public function test_duplicate_email_is_rejected_on_account_update(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'owner@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->asUser($owner)
            ->putJson('/api/auth/account', [
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame('owner@example.com', $owner->fresh()->email);
        $this->assertSame(UserRole::Owner, $owner->fresh()->role);
    }

    public function test_guest_cannot_update_account_or_password(): void
    {
        $this->putJson('/api/auth/account', [
            'email' => 'hebrwabaad@gmail.com',
        ])->assertUnauthorized();

        $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_change_password_with_current_password(): void
    {
        $owner = User::factory()->owner()->create([
            'email' => 'owner@example.com',
            'password' => 'old-password-123',
        ]);
        $otherToken = $owner->createToken('other')->plainTextToken;
        $session = $this->asUser($owner);

        $session
            ->putJson('/api/auth/password', [
                'current_password' => 'old-password-123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'updated')
            ->assertJsonMissingPath('data.password');

        $owner->refresh();
        $this->assertTrue(Hash::check('new-password-123', $owner->password));
        $this->assertFalse(Hash::check('old-password-123', $owner->password));
        $this->assertSame(UserRole::Owner, $owner->role);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken($otherToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $owner = User::factory()->owner()->create([
            'password' => 'correct-password-123',
        ]);

        $this->asUser($owner)
            ->putJson('/api/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('correct-password-123', $owner->fresh()->password));
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $owner = User::factory()->owner()->create([
            'password' => 'correct-password-123',
        ]);

        $this->asUser($owner)
            ->putJson('/api/auth/password', [
                'current_password' => 'correct-password-123',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_customer_can_update_own_email_but_not_role(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
            'role' => UserRole::Customer,
        ]);

        $this->asUser($customer)
            ->putJson('/api/auth/account', [
                'email' => 'customer-new@example.com',
                'role' => UserRole::Owner->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'customer-new@example.com')
            ->assertJsonPath('data.role', UserRole::Customer->value);

        $this->assertSame(UserRole::Customer, $customer->fresh()->role);
    }

    public function test_password_change_response_never_includes_password_hash(): void
    {
        $owner = User::factory()->owner()->create([
            'password' => 'old-password-123',
        ]);

        $response = $this->asUser($owner)
            ->putJson('/api/auth/password', [
                'current_password' => 'old-password-123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk()
            ->json();

        $encoded = json_encode($response);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('new-password-123', $encoded);
        $this->assertStringNotContainsString('$2y$', $encoded);
    }
}
