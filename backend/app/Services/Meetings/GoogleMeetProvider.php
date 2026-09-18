<?php

namespace App\Services\Meetings;

use App\Contracts\VideoMeetingProvider;
use App\Enums\MeetingProvider;
use App\Models\User;
use App\Services\GoogleCalendar\GoogleCalendarApiClient;
use App\Services\GoogleCalendar\GoogleCalendarOAuthService;
use Illuminate\Validation\ValidationException;

class GoogleMeetProvider implements VideoMeetingProvider
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauth,
        private readonly GoogleCalendarApiClient $api,
    ) {}

    public function provider(): MeetingProvider
    {
        return MeetingProvider::GoogleMeet;
    }

    public function isConfigured(): bool
    {
        return $this->oauth->isConfigured();
    }

    public function create(array $payload): array
    {
        $hostId = (int) ($payload['host_user_id'] ?? 0);
        $host = User::query()->find($hostId);
        if ($host === null) {
            throw ValidationException::withMessages([
                'provider' => ['Google Meet requires a host with a connected Google account.'],
            ]);
        }

        $connection = $this->oauth->connectionFor($host);
        if ($connection === null || ! $connection->sync_enabled) {
            throw ValidationException::withMessages([
                'provider' => ['Connect Google Calendar before creating a Google Meet.'],
            ]);
        }

        $eventPayload = $this->eventPayload($payload, withConference: true);
        $event = $this->api->createEvent($connection, $eventPayload, withMeet: true);

        return $this->normalizeEvent($event);
    }

    public function update(string $meetingId, array $payload, ?string $externalEventId = null): array
    {
        $hostId = (int) ($payload['host_user_id'] ?? 0);
        $host = User::query()->find($hostId);
        $connection = $host ? $this->oauth->connectionFor($host) : null;
        if ($connection === null) {
            throw ValidationException::withMessages([
                'provider' => ['Google Calendar connection missing for update.'],
            ]);
        }

        $eventId = $externalEventId ?: $meetingId;
        $eventPayload = $this->eventPayload($payload, withConference: true);
        $event = $this->api->updateEvent($connection, $eventId, $eventPayload, withMeet: true);

        return $this->normalizeEvent($event);
    }

    public function cancel(string $meetingId, ?string $externalEventId = null): void
    {
        // Cancellation is handled by MeetingService with the host connection context.
        // Providers that need host context receive external_event_id; host is resolved upstream.
    }

    public function cancelForHost(User $host, string $meetingId, ?string $externalEventId = null): void
    {
        $connection = $this->oauth->connectionFor($host);
        if ($connection === null) {
            return;
        }

        $eventId = $externalEventId ?: $meetingId;
        $this->api->deleteEvent($connection, $eventId);
    }

    public function getJoinInfo(string $meetingId): array
    {
        return [
            'meeting_id' => $meetingId,
            'join_url' => null,
            'host_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function eventPayload(array $payload, bool $withConference): array
    {
        $timezone = $payload['timezone'] ?? config('app.timezone', 'UTC');
        $start = $payload['start_at'] ?? now()->toIso8601String();
        $end = $payload['end_at'] ?? now()->addHour()->toIso8601String();

        $attendees = [];
        foreach ($payload['attendee_emails'] ?? [] as $email) {
            if (is_string($email) && $email !== '') {
                $attendees[] = ['email' => $email];
            }
        }

        $body = [
            'summary' => (string) ($payload['title'] ?? 'Meeting'),
            'description' => $payload['description'] ?? null,
            'start' => ['dateTime' => $start, 'timeZone' => $timezone],
            'end' => ['dateTime' => $end, 'timeZone' => $timezone],
            'attendees' => $attendees,
        ];

        if ($withConference) {
            $body['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'meet-'.md5($start.(string) ($payload['title'] ?? '').microtime(true)),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{meeting_id: string|null, join_url: string|null, host_url: string|null, external_event_id: string|null, meta: array<string, mixed>}
     */
    private function normalizeEvent(array $event): array
    {
        $join = $event['hangoutLink']
            ?? data_get($event, 'conferenceData.entryPoints.0.uri')
            ?? null;
        $conferenceId = data_get($event, 'conferenceData.conferenceId')
            ?? data_get($event, 'conferenceData.entryPoints.0.meetingCode')
            ?? null;

        return [
            'meeting_id' => $conferenceId ? (string) $conferenceId : (string) ($event['id'] ?? ''),
            'join_url' => is_string($join) ? $join : null,
            'host_url' => is_string($join) ? $join : null,
            'external_event_id' => isset($event['id']) ? (string) $event['id'] : null,
            'meta' => [
                'google_html_link' => $event['htmlLink'] ?? null,
                'conference_id' => $conferenceId,
                'hangout_link' => $event['hangoutLink'] ?? null,
            ],
        ];
    }
}
