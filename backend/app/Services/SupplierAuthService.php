<?php

namespace App\Services;

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Mail\SupplierLoginOtpMail;
use App\Models\Supplier;
use App\Models\SupplierLoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SupplierAuthService
{
    /**
     * @return array{user: User, supplier: Supplier, token: string}
     */
    public function loginWithPassword(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->role !== UserRole::Supplier || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        return $this->issueSession($user);
    }

    /**
     * @return array{status: string}
     */
    public function requestOtp(string $email): array
    {
        $user = User::query()->where('email', $email)->where('role', UserRole::Supplier)->first();

        // Always accept to avoid account enumeration.
        if ($user === null || $user->is_active === false) {
            return ['status' => 'accepted'];
        }

        $supplier = Supplier::query()->where('user_id', $user->id)->first();
        if ($supplier !== null && $supplier->status === SupplierStatus::Blocked) {
            return ['status' => 'accepted'];
        }

        $code = (string) random_int(100000, 999999);

        SupplierLoginOtp::query()->where('email', $email)->whereNull('consumed_at')->delete();

        SupplierLoginOtp::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($email)->queue(new SupplierLoginOtpMail(
            $code,
            $supplier?->publicDisplayName() ?? $user->name,
        ));

        return ['status' => 'accepted'];
    }

    /**
     * @return array{user: User, supplier: Supplier, token: string}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $otp = SupplierLoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($otp === null || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['رمز غير صالح أو منتهٍ.'],
            ]);
        }

        if ($otp->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => ['تم تجاوز عدد المحاولات. اطلب رمزاً جديداً.'],
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages([
                'code' => ['رمز غير صحيح.'],
            ]);
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        $user = User::query()->where('email', $email)->where('role', UserRole::Supplier)->first();
        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        return $this->issueSession($user);
    }

    /**
     * @return array{user: User, supplier: Supplier, token: string}
     */
    private function issueSession(User $user): array
    {
        if ($user->is_active === false) {
            throw ValidationException::withMessages([
                'email' => ['Account deactivated.'],
            ]);
        }

        $supplier = Supplier::query()->where('user_id', $user->id)->first();
        if ($supplier === null) {
            throw ValidationException::withMessages([
                'email' => ['لا يوجد ملف مورد مرتبط بهذا الحساب.'],
            ]);
        }

        if ($supplier->status === SupplierStatus::Blocked) {
            throw ValidationException::withMessages([
                'email' => ['تم حظر حساب المورد.'],
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return [
            'user' => $user,
            'supplier' => $supplier,
            'token' => $token,
        ];
    }
}
