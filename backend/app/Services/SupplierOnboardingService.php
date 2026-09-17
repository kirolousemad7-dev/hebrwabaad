<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierRegistrationPendingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SupplierOnboardingService
{
    public function __construct(
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{user: User, supplier: Supplier, token: string|null}
     */
    public function register(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $passwordless = (bool) ($payload['passwordless'] ?? false);
            $password = $passwordless
                ? Str::password(32)
                : (string) $payload['password'];

            $user = User::query()->create([
                'name' => $payload['contact_person'] ?: $payload['company_name'],
                'email' => $payload['email'],
                'password' => $password,
                'role' => UserRole::Supplier,
                'is_active' => true,
            ]);

            $companyName = (string) $payload['company_name'];
            $supplier = Supplier::query()->create([
                'user_id' => $user->id,
                'name' => $companyName,
                'display_name' => $companyName,
                'legal_name' => $companyName,
                'contact_person' => $payload['contact_person'] ?? null,
                'slug' => Supplier::uniqueSlug($companyName),
                'logo' => '/brand/logo.png',
                'short_description' => $payload['short_description'] ?? '',
                'description' => $payload['short_description'] ?? null,
                'specialties' => [],
                'services' => array_values(array_filter((array) ($payload['services'] ?? []))),
                'location' => trim(($payload['city'] ?? '').' '.($payload['country'] ?? '')) ?: ($payload['city'] ?? ''),
                'country' => $payload['country'] ?? null,
                'city' => $payload['city'] ?? null,
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'whatsapp' => $payload['whatsapp'] ?? null,
                'category' => $payload['category'] ?? null,
                'status' => SupplierStatus::Pending,
                'verification_status' => SupplierVerificationStatus::Unverified,
                'onboarding_status' => SupplierOnboardingStatus::Submitted,
                'is_active' => false,
                'is_published' => false,
                'show_public_contact' => false,
                'profile_status' => ContentStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $supplier->forceFill([
                'supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT),
            ])->save();

            if (! empty($payload['category_id'])) {
                $supplier->categories()->sync([(int) $payload['category_id']]);
            }

            Notification::send(
                $this->notifier->supplierRegistrationReviewers(),
                new SupplierRegistrationPendingNotification($supplier->fresh() ?? $supplier),
            );

            $token = null;
            if (! $passwordless) {
                $token = $user->createToken('auth')->plainTextToken;
            }

            return [
                'user' => $user,
                'supplier' => $supplier->fresh(['categories']) ?? $supplier,
                'token' => $token,
            ];
        });
    }
}
