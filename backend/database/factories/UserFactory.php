<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Customer,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
        ]);
    }

    public function adminManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::AdminManager,
        ]);
    }

    public function printingSpecialist(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::PrintingSpecialist,
        ]);
    }

    public function webDeveloper(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::WebDeveloper,
        ]);
    }

    public function graphicDesigner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::GraphicDesigner,
        ]);
    }

    public function marketingSpecialist(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::MarketingSpecialist,
        ]);
    }

    public function eventSpecialist(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::EventSpecialist,
        ]);
    }

    public function videoEditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::VideoEditor,
        ]);
    }

    public function mediaBuyer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::MediaBuyer,
        ]);
    }

    public function accountManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::AccountManager,
        ]);
    }

    public function hr(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Hr,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
