<?php

namespace App\Contracts;

use App\Enums\MeetingProvider;

interface VideoMeetingProvider
{
    public function provider(): MeetingProvider;

    public function isConfigured(): bool;

    /**
     * @param  array{
     *   title: string,
     *   description?: string|null,
     *   start_at: string,
     *   end_at?: string|null,
     *   timezone?: string|null,
     *   host_user_id?: int|null,
     *   attendee_emails?: list<string>,
     * }  $payload
     * @return array{
     *   meeting_id: string|null,
     *   join_url: string|null,
     *   host_url: string|null,
     *   external_event_id: string|null,
     *   meta?: array<string, mixed>
     * }
     */
    public function create(array $payload): array;

    /**
     * @param  array{
     *   title?: string,
     *   description?: string|null,
     *   start_at?: string,
     *   end_at?: string|null,
     *   timezone?: string|null,
     *   attendee_emails?: list<string>,
     * }  $payload
     * @return array{
     *   meeting_id: string|null,
     *   join_url: string|null,
     *   host_url: string|null,
     *   external_event_id: string|null,
     *   meta?: array<string, mixed>
     * }
     */
    public function update(string $meetingId, array $payload, ?string $externalEventId = null): array;

    public function cancel(string $meetingId, ?string $externalEventId = null): void;

    /**
     * @return array{join_url: string|null, host_url: string|null, meeting_id: string|null}
     */
    public function getJoinInfo(string $meetingId): array;
}
