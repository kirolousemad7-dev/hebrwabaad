<?php

namespace App\Services\Auth;

use App\Enums\ContentStatus;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Models\OAuthAccount;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierRegistrationPendingNotification;
use App\Services\PlatformNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthService
{
    public const PROVIDER = 'google';

    public const INTENT_LOGIN = 'login';

    public const INTENT_REGISTER = 'register';

    public const INTENT_SUPPLIER = 'supplier';

    private const SCOPES = [
        'openid',
        'email',
        'profile',
    ];

    public function __construct(
        private readonly PlatformNotifier $notifier,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled($this->redirectUri());
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'google' => ['Google sign-in is not configured.'],
            ]);
        }
    }

    public function redirectUri(): string
    {
        $uri = config('services.google.auth_redirect_uri');

        if (filled($uri)) {
            return (string) $uri;
        }

        return rtrim((string) config('app.url'), '/').'/api/auth/google/callback';
    }

    /**
     * @return array{authorize_url: string, state: string}
     */
    public function begin(string $intent = self::INTENT_LOGIN, ?string $next = null): array
    {
        $this->assertConfigured();

        $intent = $this->normalizeIntent($intent);
        $state = Str::random(40);

        Cache::put($this->stateKey($state), [
            'intent' => $intent,
            'next' => $this->sanitizeNext($next),
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account',
            'state' => $state,
        ]);

        return [
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
            'state' => $state,
        ];
    }

    /**
     * Exchange Google code, resolve/create user, return one-time frontend exchange code.
     *
     * @return array{exchange_code: string, next: string|null}
     */
    public function handleCallback(string $code, string $state): array
    {
        $this->assertConfigured();

        $payload = Cache::pull($this->stateKey($state));
        if (! is_array($payload) || ! isset($payload['intent'])) {
            throw ValidationException::withMessages([
                'state' => ['Invalid or expired OAuth state.'],
            ]);
        }

        $intent = $this->normalizeIntent((string) $payload['intent']);
        $next = $this->sanitizeNext(isset($payload['next']) ? (string) $payload['next'] : null);

        $tokens = $this->exchangeCode($code);
        $profile = $this->fetchUserInfo((string) ($tokens['access_token'] ?? ''));

        $providerUserId = (string) ($profile['id'] ?? '');
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $emailVerified = filter_var($profile['verified_email'] ?? $profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $name = trim((string) ($profile['name'] ?? '')) ?: ($email !== '' ? Str::before($email, '@') : 'Google User');
        $avatar = isset($profile['picture']) ? (string) $profile['picture'] : null;

        if ($providerUserId === '' || $email === '') {
            throw ValidationException::withMessages([
                'google' => ['Google did not return a usable identity.'],
            ]);
        }

        if (! $emailVerified) {
            throw ValidationException::withMessages([
                'google' => ['Google email is not verified.'],
            ]);
        }

        $user = $this->resolveUser($intent, $providerUserId, $email, $name, $avatar);

        if ($user->is_active === false) {
            throw ValidationException::withMessages([
                'account' => ['Account deactivated.'],
            ]);
        }

        if ($user->role === UserRole::Supplier) {
            $supplier = $user->supplierProfile;
            if ($supplier !== null && in_array($supplier->status, [
                SupplierStatus::Blocked,
                SupplierStatus::Rejected,
                SupplierStatus::Suspended,
            ], true)) {
                throw ValidationException::withMessages([
                    'account' => ['Supplier account is not allowed to sign in.'],
                ]);
            }
        }

        $plainToken = $user->createToken('auth')->plainTextToken;
        $exchangeCode = Str::random(64);

        Cache::put($this->exchangeKey($exchangeCode), [
            'token' => $plainToken,
            'user_id' => $user->id,
            'next' => $next,
        ], now()->addMinutes(5));

        return [
            'exchange_code' => $exchangeCode,
            'next' => $next,
        ];
    }

    /**
     * @return array{user: array<string, mixed>, token: string, next: string|null}
     */
    public function exchange(string $code): array
    {
        $payload = Cache::pull($this->exchangeKey($code));
        if (! is_array($payload) || empty($payload['token']) || empty($payload['user_id'])) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired Google sign-in code.'],
            ]);
        }

        $user = User::query()->find((int) $payload['user_id']);
        if ($user === null || $user->is_active === false) {
            throw ValidationException::withMessages([
                'account' => ['Account deactivated.'],
            ]);
        }

        return [
            'user' => UserResource::make($user)->resolve(),
            'token' => (string) $payload['token'],
            'next' => isset($payload['next']) ? $this->sanitizeNext((string) $payload['next']) : null,
        ];
    }

    private function resolveUser(
        string $intent,
        string $providerUserId,
        string $email,
        string $name,
        ?string $avatar,
    ): User {
        return DB::transaction(function () use ($intent, $providerUserId, $email, $name, $avatar): User {
            $linked = OAuthAccount::query()
                ->where('provider', self::PROVIDER)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($linked !== null) {
                $user = $linked->user()->firstOrFail();
                $this->assertIntentAllowsRole($intent, $user);

                $linked->forceFill([
                    'email' => $email,
                    'avatar_url' => $avatar,
                    'linked_at' => $linked->linked_at ?? now(),
                ])->save();

                if ($user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                return $user->fresh() ?? $user;
            }

            $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($existing !== null) {
                $this->assertIntentAllowsRole($intent, $existing);
                $this->linkAccount($existing, $providerUserId, $email, $avatar);

                if ($existing->email_verified_at === null) {
                    $existing->forceFill(['email_verified_at' => now()])->save();
                }

                if ($intent === self::INTENT_SUPPLIER && $existing->role === UserRole::Supplier) {
                    // keep pending approval workflow; do not recreate
                }

                return $existing->fresh() ?? $existing;
            }

            if ($intent === self::INTENT_SUPPLIER) {
                return $this->createSupplierUser($providerUserId, $email, $name, $avatar);
            }

            // login / register → Customer only (never Owner/Staff)
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::password(32),
                'role' => UserRole::Customer,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->linkAccount($user, $providerUserId, $email, $avatar);

            return $user;
        });
    }

    private function createSupplierUser(
        string $providerUserId,
        string $email,
        string $name,
        ?string $avatar,
    ): User {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(32),
            'role' => UserRole::Supplier,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $companyName = $name !== '' ? $name : 'مورد جديد';

        $supplier = Supplier::query()->create([
            'user_id' => $user->id,
            'name' => $companyName,
            'display_name' => $companyName,
            'legal_name' => $companyName,
            'contact_person' => $name,
            'slug' => Supplier::uniqueSlug($companyName),
            'logo' => '/brand/logo.png',
            'short_description' => 'تم التسجيل عبر Google — يرجى إكمال الملف.',
            'description' => null,
            'specialties' => [],
            'services' => [],
            'location' => '',
            'email' => $email,
            'status' => SupplierStatus::Pending,
            'verification_status' => SupplierVerificationStatus::Unverified,
            'onboarding_status' => SupplierOnboardingStatus::Submitted,
            'is_active' => false,
            'is_published' => false,
            'show_public_contact' => false,
            'profile_status' => ContentStatus::Draft,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $supplier->forceFill([
            'supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT),
            'email_verified_at' => now(),
        ])->save();

        Notification::send(
            $this->notifier->supplierRegistrationReviewers(),
            new SupplierRegistrationPendingNotification($supplier->fresh() ?? $supplier),
        );

        $this->linkAccount($user, $providerUserId, $email, $avatar);

        return $user;
    }

    private function linkAccount(User $user, string $providerUserId, string $email, ?string $avatar): void
    {
        OAuthAccount::query()->updateOrCreate(
            [
                'provider' => self::PROVIDER,
                'provider_user_id' => $providerUserId,
            ],
            [
                'user_id' => $user->id,
                'email' => $email,
                'avatar_url' => $avatar,
                'linked_at' => now(),
            ],
        );
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
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'google' => ['Failed to exchange Google authorization code.'],
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
        if ($accessToken === '') {
            return [];
        }

        $response = Http::withToken($accessToken)
            ->connectTimeout(3)
            ->timeout(8)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'google' => ['Failed to load Google profile.'],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    private function normalizeIntent(string $intent): string
    {
        return match ($intent) {
            self::INTENT_SUPPLIER => self::INTENT_SUPPLIER,
            self::INTENT_REGISTER => self::INTENT_REGISTER,
            default => self::INTENT_LOGIN,
        };
    }

    /**
     * Customer Google login must not escalate into Owner/Staff sessions.
     * Supplier intent is limited to Supplier accounts.
     */
    private function assertIntentAllowsRole(string $intent, User $user): void
    {
        if ($intent === self::INTENT_SUPPLIER) {
            if ($user->role !== UserRole::Supplier) {
                throw ValidationException::withMessages([
                    'privilege' => ['This Google account is not registered as a supplier. Use the matching portal for your role.'],
                ]);
            }

            return;
        }

        // login / register — customers only
        if ($user->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'privilege' => ['This Google account belongs to a staff or supplier user. Use the appropriate portal instead of customer Google sign-in.'],
            ]);
        }
    }

    private function sanitizeNext(?string $next): ?string
    {
        if ($next === null || $next === '') {
            return null;
        }

        if (! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return null;
        }

        return $next;
    }

    private function stateKey(string $state): string
    {
        return 'google_auth_oauth_state:'.$state;
    }

    private function exchangeKey(string $code): string
    {
        return 'google_auth_exchange:'.$code;
    }
}
