<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'display_name' => $name,
            'legal_name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'logo' => '/suppliers/logos/ufuq.svg',
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'specialties' => ['الطباعة التجارية'],
            'services' => ['كروت شخصية'],
            'location' => 'الرياض',
            'is_active' => true,
            'is_featured' => false,
            'is_published' => true,
            'show_public_contact' => false,
            'profile_status' => ContentStatus::Published,
            'status' => SupplierStatus::Active,
            'verification_status' => SupplierVerificationStatus::Unverified,
            'onboarding_status' => SupplierOnboardingStatus::Completed,
            'category' => 'الطباعة التجارية',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => SupplierStatus::Suspended,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'profile_status' => ContentStatus::Draft,
            'status' => SupplierStatus::Pending,
            'onboarding_status' => SupplierOnboardingStatus::Draft,
        ]);
    }
}
