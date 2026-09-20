<?php

namespace Database\Factories;

use App\Models\OAuthAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthAccount>
 */
class OAuthAccountFactory extends Factory
{
    protected $model = OAuthAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'google',
            'provider_user_id' => (string) fake()->unique()->numerify('##############'),
            'email' => fake()->safeEmail(),
            'avatar_url' => null,
            'linked_at' => now(),
        ];
    }
}
