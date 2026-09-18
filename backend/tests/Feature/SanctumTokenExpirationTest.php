<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SanctumTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_personal_access_token_is_rejected(): void
    {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->owner()->create();
        $plain = $user->createToken('auth')->plainTextToken;
        $token = PersonalAccessToken::findToken($plain);
        $this->assertNotNull($token);

        $token->forceFill([
            'created_at' => now()->subMinutes(120),
        ])->save();

        $this->withToken($plain)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_fresh_token_still_works_with_expiration_configured(): void
    {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->owner()->create();
        $plain = $user->createToken('auth')->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
