<?php

namespace App\Services\Customer;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff-provisioned customers are User rows with role CUSTOMER.
 * They share the same Project.customer_id FK as self-registered customers.
 * A random unusable password is set; the customer can later claim access via password reset / OTP.
 */
class StaffCustomerService
{
    /**
     * @param  array{name: string, email: string}  $attributes
     */
    public function create(array $attributes): User
    {
        $email = strtolower(trim($attributes['email']));
        $name = trim($attributes['name']);

        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني مستخدم بالفعل.'],
            ]);
        }

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
            'role' => UserRole::Customer,
            'is_active' => true,
        ]);
    }
}
