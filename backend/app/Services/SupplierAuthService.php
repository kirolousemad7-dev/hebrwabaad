<?php

namespace App\Services;

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Mail\SupplierLoginOtpMail;
use App\Models\Supplier;
use App\Models\SupplierLoginOtp;
use App\Models\User;
use App\Services\Identity\SupplierVerificationSync;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SupplierAuthService
{
    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public const OTP_RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly SupplierVerificationSync $verificationSync,
    ) {}

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

        $latest = SupplierLoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($latest?->sent_at !== null
            && $latest->sent_at->copy()->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS)->isFuture()) {
            // Do not reveal existence; still return accepted but skip send.
            return ['status' => 'accepted'];
        }

        $code = (string) random_int(100000, 999999);

        SupplierLoginOtp::query()->where('email', $email)->whereNull('consumed_at')->delete();

        SupplierLoginOtp::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'sent_at' => now(),
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

        if ($otp->attempts >= self::OTP_MAX_ATTEMPTS) {
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

        SupplierLoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $user = User::query()->where('email', $email)->where('role', UserRole::Supplier)->first();
        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $supplier = Supplier::query()->where('user_id', $user->id)->first();
        if ($supplier !== null && $supplier->email_verified_at === null) {
            $supplier->forceFill(['email_verified_at' => now()])->save();
            $this->verificationSync->sync($supplier);
        }

        return $this->issueSession($user->refresh());
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
