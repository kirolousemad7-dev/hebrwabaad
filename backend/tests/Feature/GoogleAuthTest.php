<?php

namespace Tests\Feature;

use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\OAuthAccount;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierRegistrationPendingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
            'services.google.auth_redirect_uri' => 'http://localhost:8000/api/auth/google/callback',
            'app.frontend_url' => 'http://localhost:5173',
            'app.url' => 'http://localhost:8000',
        ]);
    }

    public function test_status_reports_configured_when_credentials_present(): void
    {
        $this->getJson('/api/auth/google/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.redirect_uri', 'http://localhost:8000/api/auth/google/callback');
    }

    public function test_redirect_sends_user_to_google_authorize_url(): void
    {
        $response = $this->get('/api/auth/google/redirect?intent=register&next=/dashboard');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('client_id=test-google-client-id', $location);
        $this->assertStringContainsString(urlencode('http://localhost:8000/api/auth/google/callback'), $location);
        $this->assertStringContainsString('scope='.urlencode('openid email profile'), $location);
        $this->assertStringNotContainsString('test-google-client-secret', $location);
    }

    public function test_callback_creates_customer_for_new_google_user(): void
    {
        $state = $this->seedState('register');
        $this->fakeGoogle('google-new-1', 'new.customer@example.com', 'New Customer');

        $response = $this->get('/api/auth/google/callback?code=auth-code&state='.$state);
        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith('http://localhost:5173/auth/google/callback?', $location);

        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $query);
        $this->assertNotEmpty($query['code'] ?? null);

        $user = User::query()->where('email', 'new.customer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-new-1',
        ]);
        $this->assertSame(1, User::query()->where('email', 'new.customer@example.com')->count());

        $exchange = $this->postJson('/api/auth/google/exchange', ['code' => $query['code']])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'new.customer@example.com')
            ->assertJsonPath('data.user.role', 'CUSTOMER')
            ->json('data');

        $this->assertNotEmpty($exchange['token']);
        $this->assertArrayNotHasKey('access_token', $exchange);
        $this->assertArrayNotHasKey('refresh_token', $exchange);

        $this->withToken($exchange['token'])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'new.customer@example.com');
    }

    public function test_callback_logs_in_existing_google_linked_user_without_duplicate(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'role' => UserRole::Customer,
        ]);
        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-linked-1',
            'email' => 'linked@example.com',
            'linked_at' => now(),
        ]);

        $state = $this->seedState('login');
        $this->fakeGoogle('google-linked-1', 'linked@example.com', 'Linked User');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)->assertRedirect();

        $this->assertSame(1, User::query()->where('email', 'linked@example.com')->count());
        $this->assertSame(1, OAuthAccount::query()->where('provider_user_id', 'google-linked-1')->count());
    }

    public function test_callback_links_google_identity_to_existing_email_account(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'email_verified_at' => null,
            'role' => UserRole::Customer,
        ]);

        $state = $this->seedState('login');
        $this->fakeGoogle('google-link-2', 'Existing@example.com', 'Existing User');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)->assertRedirect();

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('oauth_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-link-2',
        ]);
        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertSame(UserRole::Customer, $user->fresh()?->role);
    }

    public function test_callback_rejects_invalid_oauth_state(): void
    {
        $this->fakeGoogle('google-x', 'x@example.com', 'X');

        $this->get('/api/auth/google/callback?code=auth-code&state=bogus-state')
            ->assertRedirect('http://localhost:5173/login?google_error=invalid_state');
    }

    public function test_callback_handles_google_access_denied(): void
    {
        $this->get('/api/auth/google/callback?error=access_denied')
            ->assertRedirect('http://localhost:5173/login?google_error=cancelled');
    }

    public function test_callback_handles_token_exchange_failure(): void
    {
        $state = $this->seedState('login');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->get('/api/auth/google/callback?code=bad-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=oauth_failed');
    }

    public function test_inactive_user_cannot_complete_google_login(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
        ]);
        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-inactive',
            'email' => 'inactive@example.com',
            'linked_at' => now(),
        ]);

        $state = $this->seedState('login');
        $this->fakeGoogle('google-inactive', 'inactive@example.com', 'Inactive');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=account_deactivated');
    }

    public function test_blocked_supplier_cannot_complete_google_login(): void
    {
        $user = User::factory()->supplier()->create([
            'email' => 'blocked.supplier@example.com',
            'is_active' => true,
        ]);
        Supplier::factory()->create([
            'user_id' => $user->id,
            'email' => 'blocked.supplier@example.com',
            'status' => SupplierStatus::Blocked,
            'is_active' => false,
        ]);
        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-blocked-supplier',
            'email' => 'blocked.supplier@example.com',
            'linked_at' => now(),
        ]);

        $state = $this->seedState('supplier');
        $this->fakeGoogle('google-blocked-supplier', 'blocked.supplier@example.com', 'Blocked');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=account_deactivated');
    }

    public function test_suspended_supplier_cannot_complete_google_login(): void
    {
        $user = User::factory()->supplier()->create([
            'email' => 'suspended.supplier@example.com',
            'is_active' => true,
        ]);
        Supplier::factory()->create([
            'user_id' => $user->id,
            'email' => 'suspended.supplier@example.com',
            'status' => SupplierStatus::Suspended,
            'is_active' => false,
        ]);
        OAuthAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-suspended-supplier',
            'email' => 'suspended.supplier@example.com',
            'linked_at' => now(),
        ]);

        $state = $this->seedState('supplier');
        $this->fakeGoogle('google-suspended-supplier', 'suspended.supplier@example.com', 'Suspended');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=account_deactivated');
    }

    public function test_supplier_intent_creates_pending_supplier_and_never_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $state = $this->seedState('supplier');
        $this->fakeGoogle('google-supplier-1', 'vendor.google@example.com', 'Vendor Google');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)->assertRedirect();

        $user = User::query()->where('email', 'vendor.google@example.com')->firstOrFail();
        $this->assertSame(UserRole::Supplier, $user->role);
        $this->assertNotSame(UserRole::Owner, $user->role);
        $this->assertNotNull($user->email_verified_at);

        $supplier = $user->supplierProfile;
        $this->assertNotNull($supplier);
        $this->assertSame(SupplierStatus::Pending, $supplier->status);
        $this->assertFalse($supplier->is_active);

        Notification::assertSentTo($owner, SupplierRegistrationPendingNotification::class);
    }

    public function test_register_intent_never_creates_owner_role(): void
    {
        $state = $this->seedState('register');
        $this->fakeGoogle('google-owner-attempt', 'would-be-owner@example.com', 'Would Be Owner');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)->assertRedirect();

        $user = User::query()->where('email', 'would-be-owner@example.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertDatabaseMissing('users', [
            'email' => 'would-be-owner@example.com',
            'role' => UserRole::Owner->value,
        ]);
    }

    public function test_exchange_code_is_one_time_use(): void
    {
        $state = $this->seedState('login');
        $this->fakeGoogle('google-once', 'once@example.com', 'Once');

        $location = $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->headers
            ->get('Location');
        parse_str(parse_url((string) $location, PHP_URL_QUERY) ?: '', $query);

        $this->postJson('/api/auth/google/exchange', ['code' => $query['code']])->assertOk();
        $this->postJson('/api/auth/google/exchange', ['code' => $query['code']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $state = $this->seedState('login');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => 'google-unverified',
                'email' => 'unverified@example.com',
                'verified_email' => false,
                'name' => 'Unverified',
            ], 200),
        ]);

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=oauth_failed');

        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
    }

    public function test_customer_google_login_cannot_escalate_to_owner_account(): void
    {
        User::factory()->owner()->create([
            'email' => 'owner.google@example.com',
        ]);

        $state = $this->seedState('login');
        $this->fakeGoogle('google-owner-email', 'owner.google@example.com', 'Owner');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=account_blocked');

        $this->assertDatabaseMissing('oauth_accounts', [
            'provider_user_id' => 'google-owner-email',
        ]);
    }

    public function test_customer_google_login_rejects_existing_staff_email(): void
    {
        User::factory()->adminManager()->create([
            'email' => 'admin.google@example.com',
        ]);

        $state = $this->seedState('register');
        $this->fakeGoogle('google-admin-email', 'admin.google@example.com', 'Admin');

        $this->get('/api/auth/google/callback?code=auth-code&state='.$state)
            ->assertRedirect('http://localhost:5173/login?google_error=account_blocked');
    }

    private function seedState(string $intent, ?string $next = null): string
    {
        $state = 'test-state-'.uniqid();
        Cache::put('google_auth_oauth_state:'.$state, [
            'intent' => $intent,
            'next' => $next,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        return $state;
    }

    private function fakeGoogle(string $providerUserId, string $email, string $name): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'id' => $providerUserId,
                'email' => $email,
                'verified_email' => true,
                'name' => $name,
                'picture' => 'https://example.com/avatar.png',
            ], 200),
        ]);
    }
}
