<?php

namespace App\Console\Commands;

use App\Models\DelayedNotification;
use App\Models\User;
use App\Notifications\CalendarNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Throwable;

class DeliverDelayedNotifications extends Command
{
    protected $signature = 'operations:deliver-delayed-notifications';

    protected $description = 'Deliver delayed notifications whose quiet-hours window has ended';

    public function handle(): int
    {
        $rows = DelayedNotification::query()
            ->whereNull('delivered_at')
            ->where('deliver_after', '<=', now())
            ->orderBy('id')
            ->limit(200)
            ->get();

        $delivered = 0;
        foreach ($rows as $row) {
            $user = User::query()->find($row->user_id);
            if ($user === null || ! $user->is_active) {
                $row->update(['delivered_at' => now()]);

                continue;
            }

            try {
                $notification = $this->instantiate($row->notification_class, $row->payload ?? []);
                if ($notification !== null) {
                    $user->notify($notification);
                }
            } catch (Throwable) {
                // Mark delivered to avoid poison-pill loops; payload can be inspected later.
            }

            $row->update(['delivered_at' => now()]);
            $delivered++;
        }

        $this->info("Delivered {$delivered} delayed notification(s).");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function instantiate(string $class, array $payload): ?Notification
    {
        if ($class === CalendarNotification::class || is_a($class, CalendarNotification::class, true)) {
            return new CalendarNotification($payload);
        }

        if (! class_exists($class) || ! is_subclass_of($class, Notification::class)) {
            return new CalendarNotification($payload);
        }

        try {
            return new $class($payload);
        } catch (Throwable) {
            return new CalendarNotification($payload);
        }
    }
}
