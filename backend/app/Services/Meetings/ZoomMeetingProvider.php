<?php

namespace App\Services\Meetings;

use App\Contracts\VideoMeetingProvider;
use App\Enums\MeetingProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Zoom Server-to-Server OAuth provider (account-level credentials from env).
 */
class ZoomMeetingProvider implements VideoMeetingProvider
{
    public function provider(): MeetingProvider
    {
        return MeetingProvider::Zoom;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.zoom.client_id'))
            && filled(config('services.zoom.client_secret'))
            && filled(config('services.zoom.account_id'));
    }

    public function create(array $payload): array
    {
        $this->assertConfigured();

        $timezone = $payload['timezone'] ?? config('app.timezone', 'UTC');
        $start = $payload['start_at'] ?? now()->toIso8601String();
        $end = $payload['end_at'] ?? null;
        $duration = 60;
        if (is_string($end) && $end !== '') {
            $duration = max(15, (int) round((strtotime($end) - strtotime($start)) / 60));
        }

        $response = $this->http()
            ->post('https://api.zoom.us/v2/users/me/meetings', [
                'topic' => (string) ($payload['title'] ?? 'Meeting'),
                'type' => 2,
                'start_time' => gmdate('Y-m-d\TH:i:s\Z', strtotime($start) ?: time()),
                'duration' => $duration,
                'timezone' => $timezone,
                'agenda' => $payload['description'] ?? null,
                'settings' => [
                    'join_before_host' => false,
                    'waiting_room' => true,
                ],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'zoom' => ['Failed to create Zoom meeting.'],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $this->normalize($data);
    }

    public function update(string $meetingId, array $payload, ?string $externalEventId = null): array
    {
        $this->assertConfigured();

        $body = [];
        if (isset($payload['title'])) {
            $body['topic'] = $payload['title'];
        }
        if (array_key_exists('description', $payload)) {
            $body['agenda'] = $payload['description'];
        }
        if (isset($payload['start_at'])) {
            $body['start_time'] = gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $payload['start_at']) ?: time());
        }
        if (isset($payload['timezone'])) {
            $body['timezone'] = $payload['timezone'];
        }
        if (isset($payload['start_at'], $payload['end_at'])) {
            $body['duration'] = max(15, (int) round((strtotime((string) $payload['end_at']) - strtotime((string) $payload['start_at'])) / 60));
        }

        $response = $this->http()
            ->patch('https://api.zoom.us/v2/meetings/'.rawurlencode($meetingId), $body);

        if (! $response->successful() && $response->status() !== 204) {
            throw ValidationException::withMessages([
                'zoom' => ['Failed to update Zoom meeting.'],
            ]);
        }

        return $this->getJoinInfo($meetingId);
    }

    public function cancel(string $meetingId, ?string $externalEventId = null): void
    {
        $this->assertConfigured();

        $response = $this->http()
            ->delete('https://api.zoom.us/v2/meetings/'.rawurlencode($meetingId));

        if (! in_array($response->status(), [200, 204, 404], true)) {
            throw ValidationException::withMessages([
                'zoom' => ['Failed to cancel Zoom meeting.'],
            ]);
        }
    }

    public function getJoinInfo(string $meetingId): array
    {
        $this->assertConfigured();

        $response = $this->http()
            ->get('https://api.zoom.us/v2/meetings/'.rawurlencode($meetingId));

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'zoom' => ['Failed to fetch Zoom meeting.'],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $this->normalize($data);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'zoom' => ['Zoom is not configured.'],
            ]);
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(15);
    }

    private function accessToken(): string
    {
        $cached = Cache::get('zoom_s2s_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.zoom.client_id'),
                (string) config('services.zoom.client_secret'),
            )
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://zoom.us/oauth/token', [
                'grant_type' => 'account_credentials',
                'account_id' => config('services.zoom.account_id'),
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'zoom' => ['Unable to obtain Zoom access token.'],
            ]);
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        Cache::put('zoom_s2s_access_token', $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{meeting_id: string|null, join_url: string|null, host_url: string|null, external_event_id: string|null, meta: array<string, mixed>}
     */
    private function normalize(array $data): array
    {
        $id = isset($data['id']) ? (string) $data['id'] : null;

        return [
            'meeting_id' => $id,
            'join_url' => isset($data['join_url']) ? (string) $data['join_url'] : null,
            'host_url' => isset($data['start_url']) ? (string) $data['start_url'] : null,
            'external_event_id' => $id,
            'meta' => [
                'password' => $data['password'] ?? null,
                'uuid' => $data['uuid'] ?? null,
            ],
        ];
    }
}
