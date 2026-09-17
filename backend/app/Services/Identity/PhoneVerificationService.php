<?php

namespace App\Services\Identity;

use App\Models\PhoneVerificationOtp;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(
        private readonly SmsManager $sms,
        private readonly SupplierVerificationSync $sync,
    ) {}

    /**
     * @return array{status: string}
     */
    public function request(User $user, ?string $phone = null): array
    {
        $supplier = Supplier::query()->where('user_id', $user->id)->first();
        $target = $phone ?: ($supplier?->phone ?: $supplier?->whatsapp);

        if (! is_string($target) || trim($target) === '') {
            throw ValidationException::withMessages([
                'phone' => ['رقم الهاتف مطلوب.'],
            ]);
        }

        $target = trim($target);

        $latest = PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('phone', $target)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        $cooldown = (int) config('sms.otp.resend_cooldown_seconds', 60);
        if ($latest?->sent_at !== null && $latest->sent_at->copy()->addSeconds($cooldown)->isFuture()) {
            throw ValidationException::withMessages([
                'phone' => ['يرجى الانتظار قبل إعادة إرسال رمز الهاتف.'],
            ]);
        }

        $code = $this->generateCode();
        $ttl = (int) config('sms.otp.ttl_minutes', 10);

        PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->where('phone', $target)
            ->whereNull('consumed_at')
            ->delete();

        PhoneVerificationOtp::query()->create([
            'user_id' => $user->id,
            'phone' => $target,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttl),
            'sent_at' => now(),
        ]);

        if ($supplier !== null && $supplier->phone !== $target) {
            $supplier->forceFill(['phone' => $target])->save();
        }

        $this->sms->send($target, 'رمز التحقق لهبر وأبعاد: '.$code);

        return ['status' => 'accepted'];
    }

    public function verify(User $user, string $code, ?string $phone = null): Supplier
    {
        $supplier = Supplier::query()->where('user_id', $user->id)->firstOrFail();
        $target = $phone ?: ($supplier->phone ?: $supplier->whatsapp);

        $otp = PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->when(is_string($target) && $target !== '', fn ($q) => $q->where('phone', $target))
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($otp === null || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['رمز غير صالح أو منتهٍ.'],
            ]);
        }

        $maxAttempts = (int) config('sms.otp.max_attempts', 5);
        if ($otp->attempts >= $maxAttempts) {
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

        PhoneVerificationOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $supplier->forceFill([
            'phone' => $otp->phone,
            'phone_verified_at' => now(),
        ])->save();

        return $this->sync->sync($supplier);
    }

    private function generateCode(): string
    {
        $length = max(4, min(8, (int) config('sms.otp.length', 6)));
        $max = (10 ** $length) - 1;
        $min = 10 ** ($length - 1);

        return (string) random_int($min, $max);
    }
}
