<?php

namespace App\Services\GoogleCalendar;

use App\Models\GoogleCalendarConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleCalendarApiClient
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauth,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createEvent(GoogleCalendarConnection $connection, array $payload, bool $withMeet = false): array
    {
        $calendarId = rawurlencode($connection->calendar_id ?: 'primary');
        $query = $withMeet ? '?conferenceDataVersion=1' : '';

        $response = $this->http($connection)
            ->post("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events{$query}", $payload);

        return $this->decode($response->status(), $response->json(), 'create');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateEvent(GoogleCalendarConnection $connection, string $eventId, array $payload, bool $withMeet = false): array
    {
        $calendarId = rawurlencode($connection->calendar_id ?: 'primary');
        $encodedEvent = rawurlencode($eventId);
        $query = $withMeet ? '?conferenceDataVersion=1' : '';

        $response = $this->http($connection)
            ->patch("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$encodedEvent}{$query}", $payload);

        return $this->decode($response->status(), $response->json(), 'update');
    }

    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void
    {
        $calendarId = rawurlencode($connection->calendar_id ?: 'primary');
        $encodedEvent = rawurlencode($eventId);

        $response = $this->http($connection)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$encodedEvent}");

        if ($response->status() === 404 || $response->status() === 410) {
            return;
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'google' => ['Failed to delete Google Calendar event.'],
            ]);
        }
    }

    private function http(GoogleCalendarConnection $connection): PendingRequest
    {
        $token = $this->oauth->ensureAccessToken($connection);

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(15)
            ->retry(2, 200, throw: false);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function decode(int $status, ?array $json, string $action): array
    {
        if ($status >= 200 && $status < 300 && is_array($json)) {
            return $json;
        }

        $message = is_array($json) ? (string) data_get($json, 'error.message', 'Google Calendar API error') : 'Google Calendar API error';

        throw ValidationException::withMessages([
            'google' => ["Failed to {$action} Google Calendar event: {$message}"],
        ]);
    }
}
