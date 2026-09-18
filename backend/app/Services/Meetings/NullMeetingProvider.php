<?php

namespace App\Services\Meetings;

use App\Contracts\VideoMeetingProvider;
use App\Enums\MeetingProvider;

class NullMeetingProvider implements VideoMeetingProvider
{
    public function provider(): MeetingProvider
    {
        return MeetingProvider::None;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function create(array $payload): array
    {
        return [
            'meeting_id' => null,
            'join_url' => null,
            'host_url' => null,
            'external_event_id' => null,
            'meta' => [],
        ];
    }

    public function update(string $meetingId, array $payload, ?string $externalEventId = null): array
    {
        return [
            'meeting_id' => $meetingId !== '' ? $meetingId : null,
            'join_url' => null,
            'host_url' => null,
            'external_event_id' => $externalEventId,
            'meta' => [],
        ];
    }

    public function cancel(string $meetingId, ?string $externalEventId = null): void
    {
        // No remote session.
    }

    public function getJoinInfo(string $meetingId): array
    {
        return [
            'meeting_id' => $meetingId,
            'join_url' => null,
            'host_url' => null,
        ];
    }
}
