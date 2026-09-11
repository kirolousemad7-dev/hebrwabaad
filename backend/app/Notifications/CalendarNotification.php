<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationChannelManager;
use Illuminate\Notifications\Notification;

class CalendarNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return app(NotificationChannelManager::class)->enabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
