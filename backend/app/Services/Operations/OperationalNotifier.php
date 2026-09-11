<?php

namespace App\Services\Operations;

use App\Models\DelayedNotification;
use App\Models\User;
use App\Notifications\CalendarNotification;
use App\Services\Notifications\NotificationChannelManager;
use Illuminate\Notifications\Notification;

class OperationalNotifier
{
    public function __construct(
        private readonly QuietHoursService $quietHours,
        private readonly NotificationChannelManager $channels,
    ) {}

    /**
     * Send now, or queue into delayed_notifications when quiet hours apply.
     *
     * Delivery channels come from {@see NotificationChannelManager} (database only today).
     *
     * @param  'operational'|'digest'|'automation'|'explicit_calendar_reminder'|'critical_escalation'|string  $category
     */
    public function notify(User $user, Notification $notification, string $category): bool
    {
        if ($this->channels->enabled() === []) {
            return false;
        }

        if ($this->quietHours->shouldDelay($user, $category)) {
            $payload = method_exists($notification, 'toArray')
                ? $notification->toArray($user)
                : [];

            DelayedNotification::query()->create([
                'user_id' => $user->id,
                'notification_class' => $notification::class,
                'payload' => is_array($payload) ? $payload : [],
                'category' => $category,
                'deliver_after' => $this->quietHours->nextQuietEnd($user),
                'delivered_at' => null,
            ]);

            return false;
        }

        $user->notify($notification);

        return true;
    }

    /**
     * Convenience for calendar-style database notifications.
     *
     * @param  array<string, mixed>  $payload
     */
    public function notifyCalendar(User $user, array $payload, string $category = 'operational'): bool
    {
        return $this->notify($user, new CalendarNotification($payload), $category);
    }
}
