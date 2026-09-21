<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Mail\CustomerLoginOtpMail;
use App\Models\CustomerLoginOtp;
use App\Models\User;
use App\Services\Mail\GmailApiMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CustomerOtpService
{
    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public const OTP_RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly GmailApiMailer $gmail,
    ) {}

    /**
     * @return array{status: string}
     */
    public function requestOtp(string $email): array
    {
        $email = strtolower(trim($email));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', UserRole::Customer)
            ->first();

        // Always accept to avoid account enumeration.
        if ($user === null || $user->is_active === false) {
            return ['status' => 'accepted'];
        }

        $latest = CustomerLoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($latest?->sent_at !== null
            && $latest->sent_at->copy()->addSeconds(self::OTP_RESEND_COOLDOWN_SECONDS)->isFuture()) {
            return ['status' => 'accepted'];
        }

        $code = (string) random_int(100000, 999999);

        CustomerLoginOtp::query()->where('email', $email)->whereNull('consumed_at')->delete();

        CustomerLoginOtp::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'sent_at' => now(),
        ]);

        $this->dispatchOtpEmail($email, $code, $user->name);

        return ['status' => 'accepted'];
    }

    /**
     * @return array{user: array<string, mixed>, token: string}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $email = strtolower(trim($email));

        $otp = CustomerLoginOtp::query()
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

        CustomerLoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', UserRole::Customer)
            ->first();

        if ($user === null || $user->is_active === false) {
            throw ValidationException::withMessages([
                'email' => [__('messages.invalid_credentials')],
            ]);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $token = $user->createToken('auth')->plainTextToken;

        return [
            'user' => UserResource::make($user->fresh() ?? $user)->resolve(),
            'token' => $token,
        ];
    }

    private function dispatchOtpEmail(string $email, string $code, string $name): void
    {
        $subject = 'رمز الدخول — هبر وأبعاد';
        $html = '<p>مرحباً '.e($name).'،</p>'
            .'<p>رمز الدخول لمرة واحدة هو: <strong>'.e($code).'</strong></p>'
            .'<p>صالح لمدة 10 دقائق. إذا لم تطلب هذا الرمز فتجاهل الرسالة.</p>';
        $text = "مرحباً {$name}،\nرمز الدخول لمرة واحدة هو: {$code}\nصالح لمدة 10 دقائق.";

        if ($this->gmail->isConfigured()) {
            $this->gmail->send([
                'to' => $email,
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ]);

            return;
        }

        Mail::to($email)->queue(new CustomerLoginOtpMail($code, $name));
    }
}
