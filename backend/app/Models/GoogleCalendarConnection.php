<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'user_id',
    'google_account_id',
    'google_email',
    'access_token_encrypted',
    'refresh_token_encrypted',
    'token_expires_at',
    'scopes',
    'calendar_id',
    'meet_enabled',
    'sync_enabled',
    'connected_at',
    'last_synced_at',
    'last_error',
])]
#[Hidden([
    'access_token_encrypted',
    'refresh_token_encrypted',
])]
class GoogleCalendarConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'meet_enabled' => 'boolean',
            'sync_enabled' => 'boolean',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setAccessToken(string $token): void
    {
        $this->access_token_encrypted = Crypt::encryptString($token);
    }

    public function setRefreshToken(?string $token): void
    {
        $this->refresh_token_encrypted = $token !== null && $token !== ''
            ? Crypt::encryptString($token)
            : null;
    }

    public function accessToken(): string
    {
        return Crypt::decryptString($this->access_token_encrypted);
    }

    public function refreshToken(): ?string
    {
        if ($this->refresh_token_encrypted === null || $this->refresh_token_encrypted === '') {
            return null;
        }

        return Crypt::decryptString($this->refresh_token_encrypted);
    }

    public function isExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->lessThanOrEqualTo(now()->addMinute());
    }

    /**
     * Safe public payload — never includes tokens.
     *
     * @return array<string, mixed>
     */
    public function publicStatus(): array
    {
        return [
            'connected' => true,
            'google_email' => $this->google_email,
            'calendar_id' => $this->calendar_id,
            'meet_enabled' => (bool) $this->meet_enabled,
            'sync_enabled' => (bool) $this->sync_enabled,
            'connected_at' => $this->connected_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
        ];
    }
}
