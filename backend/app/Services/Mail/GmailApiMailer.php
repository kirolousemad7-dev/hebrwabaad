<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sends mail via Gmail API using a dedicated sender OAuth refresh token.
 * Identity (Customer Google Login) credentials must never be used here.
 */
class GmailApiMailer
{
    public function isConfigured(): bool
    {
        return filled(config('services.gmail.client_id'))
            && filled(config('services.gmail.client_secret'))
            && filled(config('services.gmail.refresh_token'))
            && filled(config('services.gmail.sender_email'));
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'mail' => ['Gmail API sender is not configured.'],
            ]);
        }
    }

    /**
     * @param  array{to: string, subject: string, html: string, text?: string|null}  $message
     */
    public function send(array $message): void
    {
        $this->assertConfigured();

        $to = trim((string) ($message['to'] ?? ''));
        $subject = (string) ($message['subject'] ?? '');
        $html = (string) ($message['html'] ?? '');
        $text = isset($message['text']) ? (string) $message['text'] : strip_tags($html);

        if ($to === '' || $subject === '' || $html === '') {
            throw new RuntimeException('Gmail message is incomplete.');
        }

        $accessToken = $this->accessToken();
        $raw = $this->encodeRawMime(
            from: (string) config('services.gmail.sender_email'),
            to: $to,
            subject: $subject,
            html: $html,
            text: $text,
        );

        $response = Http::withToken($accessToken)
            ->connectTimeout(5)
            ->timeout(20)
            ->acceptJson()
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $raw,
            ]);

        if (! $response->successful()) {
            Log::warning('gmail_api.send_failed', [
                'status' => $response->status(),
                // Never log tokens, refresh secrets, or message bodies (may contain OTP).
            ]);

            throw new RuntimeException('Gmail API failed to send the message.');
        }
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.gmail.client_id'),
                'client_secret' => config('services.gmail.client_secret'),
                'refresh_token' => config('services.gmail.refresh_token'),
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            Log::warning('gmail_api.token_refresh_failed', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Unable to refresh Gmail API access token.');
        }

        $token = (string) ($response->json('access_token') ?? '');
        if ($token === '') {
            throw new RuntimeException('Gmail API did not return an access token.');
        }

        return $token;
    }

    private function encodeRawMime(string $from, string $to, string $subject, string $html, string $text): string
    {
        $boundary = 'hebr_'.bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';

        $mime = "From: {$from}\r\n"
            ."To: {$to}\r\n"
            ."Subject: {$encodedSubject}\r\n"
            ."MIME-Version: 1.0\r\n"
            ."Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n"
            ."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: text/plain; charset=UTF-8\r\n"
            ."Content-Transfer-Encoding: base64\r\n"
            ."\r\n"
            .chunk_split(base64_encode($text))
            ."--{$boundary}\r\n"
            ."Content-Type: text/html; charset=UTF-8\r\n"
            ."Content-Transfer-Encoding: base64\r\n"
            ."\r\n"
            .chunk_split(base64_encode($html))
            ."--{$boundary}--";

        return rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    }
}
