<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Platform\ProductionDataCleanupService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Production bootstrap only: ensure the single Owner account exists.
 * Does not create customers, employees, orders, CRM, or other demo runtime data.
 */
class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrNew(['email' => ProductionDataCleanupService::OWNER_EMAIL]);
        $owner->fill([
            'name' => ProductionDataCleanupService::OWNER_NAME,
            'role' => UserRole::Owner,
            'is_active' => true,
        ]);
        $owner->email_verified_at = $owner->email_verified_at ?? now();

        if (! $owner->exists || blank($owner->password)) {
            $owner->password = Hash::make(ProductionDataCleanupService::OWNER_PASSWORD);
        }

        $owner->save();
    }
}
