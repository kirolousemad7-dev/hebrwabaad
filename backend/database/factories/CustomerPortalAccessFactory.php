<?php

namespace Database\Factories;

use App\Models\CustomerPortalAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerPortalAccess>
 */
class CustomerPortalAccessFactory extends Factory
{
    protected $model = CustomerPortalAccess::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $raw = Str::random(64);

        return [
            'customer_id' => User::factory(),
            'token_hash' => CustomerPortalAccess::hashToken($raw),
            'token_hint' => substr($raw, -8),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
            'last_accessed_at' => null,
            'created_by' => null,
        ];
    }
}
