<?php

namespace App\Services\GoogleCalendar;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleCalendarOAuthService
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect_uri'));
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'google' => ['Google Calendar is not configured.'],
            ]);
        }
    }

    /**
     * @return array{authorize_url: string, state: string}
     */
    public function beginConnect(User $user): array
    {
        $this->assertConfigured();

        $state = Str::random(40);
        Cache::put($this->stateKey($state), [
            'user_id' => $user->id,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return [
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
            'state' => $state,
        ];
    }

    public function handleCallback(string $code, string $state): GoogleCalendarConnection
    {
        $this->assertConfigured();

        $payload = Cache::pull($this->stateKey($state));
        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw ValidationException::withMessages([
                'state' => ['حالة التحقق من Google غير صالحة أو منتهية.'],
            ]);
        }

        $user = User::query()->findOrFail((int) $payload['user_id']);
        $tokens = $this->exchangeCode($code);
        $profile = $this->fetchUserInfo((string) $tokens['access_token']);

        $connection = GoogleCalendarConnection::query()->firstOrNew(['user_id' => $user->id]);
        $connection->google_account_id = $profile['id'] ?? $connection->google_account_id;
        $connection->google_email = $profile['email'] ?? $connection->google_email;
        $connection->setAccessToken((string) $tokens['access_token']);
        if (! empty($tokens['refresh_token'])) {
            $connection->setRefreshToken((string) $tokens['refresh_token']);
        } elseif ($connection->refresh_token_encrypted === null) {
            throw ValidationException::withMessages([
                'google' => ['Google did not return a refresh token. Disconnect and reconnect with consent.'],
            ]);
        }
        $connection->token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
        $connection->scopes = is_string($tokens['scope'] ?? null) ? $tokens['scope'] : implode(' ', self::SCOPES);
        $connection->calendar_id = $connection->calendar_id ?: 'primary';
        $connection->sync_enabled = true;
        $connection->connected_at = $connection->connected_at ?? now();
        $connection->last_error = null;
        $connection->save();

        return $connection;
    }

    public function connectionFor(User $user): ?GoogleCalendarConnection
    {
        return GoogleCalendarConnection::query()->where('user_id', $user->id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function statusFor(User $user): array
    {
        $connection = $this->connectionFor($user);
        if ($connection === null) {
            return [
                'connected' => false,
                'configured' => $this->isConfigured(),
            ];
        }

        return [
            ...$connection->publicStatus(),
            'configured' => $this->isConfigured(),
        ];
    }

    public function disconnect(User $user): void
    {
        $connection = $this->connectionFor($user);
        if ($connection === null) {
            return;
        }

        try {
            $token = $connection->accessToken();
            Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
        } catch (\Throwable) {
            // Best-effort revoke.
        }

        $connection->delete();
    }

    /**
     * Ensure a valid access token, refreshing when needed.
     */
    public function ensureAccessToken(GoogleCalendarConnection $connection): string
    {
        if (! $connection->isExpired()) {
            return $connection->accessToken();
        }

        $refresh = $connection->refreshToken();
        if ($refresh === null) {
            $connection->update(['last_error' => 'Missing refresh token']);
            throw ValidationException::withMessages([
                'google' => ['Google connection expired. Reconnect your account.'],
            ]);
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            $connection->update(['last_error' => 'Token refresh failed']);
            throw ValidationException::withMessages([
                'google' => ['Unable to refresh Google access token.'],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $connection->setAccessToken((string) $data['access_token']);
        $connection->token_expires_at = now()->addSeconds((int) ($data['expires_in'] ?? 3600));
        if (! empty($data['refresh_token'])) {
            $connection->setRefreshToken((string) $data['refresh_token']);
        }
        $connection->last_error = null;
        $connection->save();

        return $connection->accessToken();
    }

    public function updateSettings(User $user, array $data): GoogleCalendarConnection
    {
        $connection = $this->connectionFor($user);
        if ($connection === null) {
            throw ValidationException::withMessages([
                'google' => ['Google Calendar is not connected.'],
            ]);
        }

        $connection->fill([
            'meet_enabled' => array_key_exists('meet_enabled', $data)
                ? (bool) $data['meet_enabled']
                : $connection->meet_enabled,
            'sync_enabled' => array_key_exists('sync_enabled', $data)
                ? (bool) $data['sync_enabled']
                : $connection->sync_enabled,
            'calendar_id' => $data['calendar_id'] ?? $connection->calendar_id,
        ])->save();

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect_uri'),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'google' => ['تعذر تبادل رمز تفويض Google.'],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->connectTimeout(3)
            ->timeout(8)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    private function stateKey(string $state): string
    {
        return 'google_oauth_state:'.$state;
    }
}
