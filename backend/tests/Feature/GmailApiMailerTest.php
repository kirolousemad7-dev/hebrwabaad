<?php

namespace Tests\Feature;

use App\Services\Mail\GmailApiMailer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class GmailApiMailerTest extends TestCase
{
    public function test_is_configured_requires_all_env_values(): void
    {
        config([
            'services.gmail.client_id' => null,
            'services.gmail.client_secret' => null,
            'services.gmail.refresh_token' => null,
            'services.gmail.sender_email' => null,
        ]);

        $this->assertFalse(app(GmailApiMailer::class)->isConfigured());

        config([
            'services.gmail.client_id' => 'id',
            'services.gmail.client_secret' => 'secret',
            'services.gmail.refresh_token' => 'refresh',
            'services.gmail.sender_email' => 'sender@example.com',
        ]);

        $this->assertTrue(app(GmailApiMailer::class)->isConfigured());
    }

    public function test_send_refreshes_token_and_posts_to_gmail_api(): void
    {
        config([
            'services.gmail.client_id' => 'id',
            'services.gmail.client_secret' => 'secret',
            'services.gmail.refresh_token' => 'refresh',
            'services.gmail.sender_email' => 'sender@example.com',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ], 200),
            'gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'abc'], 200),
        ]);

        app(GmailApiMailer::class)->send([
            'to' => 'customer@example.com',
            'subject' => 'Test',
            'html' => '<p>Hello</p>',
            'text' => 'Hello',
        ]);

        Http::assertSentCount(2);
    }

    public function test_invalid_refresh_token_fails_without_leaking_secrets_in_logs(): void
    {
        config([
            'services.gmail.client_id' => 'id',
            'services.gmail.client_secret' => 'super-secret-value',
            'services.gmail.refresh_token' => 'bad-refresh-token',
            'services.gmail.sender_email' => 'sender@example.com',
        ]);

        Log::spy();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        try {
            app(GmailApiMailer::class)->send([
                'to' => 'customer@example.com',
                'subject' => 'Test',
                'html' => '<p>Hello</p>',
            ]);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context) ?: '';

            return $message === 'gmail_api.token_refresh_failed'
                && ! str_contains($encoded, 'super-secret-value')
                && ! str_contains($encoded, 'bad-refresh-token');
        });
    }

    public function test_gmail_api_error_is_raised(): void
    {
        config([
            'services.gmail.client_id' => 'id',
            'services.gmail.client_secret' => 'secret',
            'services.gmail.refresh_token' => 'refresh',
            'services.gmail.sender_email' => 'sender@example.com',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ], 200),
            'gmail.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        app(GmailApiMailer::class)->send([
            'to' => 'customer@example.com',
            'subject' => 'Test',
            'html' => '<p>Hello</p>',
        ]);
    }
}
