<?php

namespace App\Services\Identity;

use App\Mail\SupplierEmailVerificationMail;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public const TTL_MINUTES = 60;

    public function __construct(
        private readonly SupplierVerificationSync $sync,
    ) {}

    /**
     * @return array{status: string, verified: bool, expires_at: string|null, sent_at: string|null}
     */
    public function status(User $user): array
    {
        if ($user->email_verified_at !== null) {
            return [
                'status' => 'verified',
                'verified' => true,
                'expires_at' => null,
                'sent_at' => $user->email_verification_sent_at?->toIso8601String(),
            ];
        }

        $sentAt = $user->email_verification_sent_at;
        if ($sentAt === null) {
            return [
                'status' => 'pending',
                'verified' => false,
                'expires_at' => null,
                'sent_at' => null,
            ];
        }

        $expiresAt = $sentAt->copy()->addMinutes(self::TTL_MINUTES);
        if ($expiresAt->isPast()) {
            return [
                'status' => 'expired',
                'verified' => false,
                'expires_at' => $expiresAt->toIso8601String(),
                'sent_at' => $sentAt->toIso8601String(),
            ];
        }

        return [
            'status' => 'pending',
            'verified' => false,
            'expires_at' => $expiresAt->toIso8601String(),
            'sent_at' => $sentAt->toIso8601String(),
        ];
    }

    public function send(User $user, bool $force = false): array
    {
        if ($user->email_verified_at !== null) {
            return $this->status($user);
        }

        if (! $force && $user->email_verification_sent_at !== null) {
            $cooldownEnds = $user->email_verification_sent_at->copy()->addSeconds(60);
            if ($cooldownEnds->isFuture()) {
                throw ValidationException::withMessages([
                    'email' => ['يرجى الانتظار قبل إعادة الإرسال.'],
                ]);
            }
        }

        $user->forceFill(['email_verification_sent_at' => now()])->save();

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $signedApi = URL::temporarySignedRoute(
            'supplier.email.verify',
            now()->addMinutes(self::TTL_MINUTES),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ],
        );

        $query = parse_url($signedApi, PHP_URL_QUERY) ?: '';
        parse_str($query, $params);
        $verificationUrl = $frontend.'/verify-email?'.http_build_query($params);

        $supplier = Supplier::query()->where('user_id', $user->id)->first();

        Mail::to($user->email)->queue(new SupplierEmailVerificationMail(
            $supplier?->publicDisplayName() ?? $user->name,
            $verificationUrl,
            self::TTL_MINUTES,
        ));

        return $this->status($user->refresh());
    }

    public function verify(int $userId, string $hash): User
    {
        $user = User::query()->findOrFail($userId);

        if (! hash_equals(sha1($user->email), $hash)) {
            throw ValidationException::withMessages([
                'hash' => ['رابط التحقق غير صالح.'],
            ]);
        }

        if ($user->email_verified_at !== null) {
            return $user;
        }

        $status = $this->status($user);
        if ($status['status'] === 'expired') {
            throw ValidationException::withMessages([
                'hash' => ['انتهت صلاحية رابط التحقق. اطلب رابطاً جديداً.'],
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $supplier = Supplier::query()->where('user_id', $user->id)->first();
        if ($supplier !== null) {
            $supplier->forceFill(['email_verified_at' => now()])->save();
            $this->sync->sync($supplier);
        }

        return $user->refresh();
    }
}
