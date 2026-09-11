<?php

namespace App\Console\Commands;

use App\Enums\CalendarItemStatus;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\CalendarItemActivity;
use App\Models\CalendarReminder;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\CalendarNotification;
use App\Services\Operations\OperationalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchCalendarReminders extends Command
{
    protected $signature = 'calendar:dispatch-reminders';

    protected $description = 'Send due calendar reminder notifications and overdue digests';

    public function handle(OperationalNotifier $notifier): int
    {
        $sent = $this->dispatchDueReminders($notifier);
        $overdue = $this->dispatchOverdueDigests($notifier);

        $this->info("Sent {$sent} reminder notification(s) and {$overdue} overdue digest(s).");

        return self::SUCCESS;
    }

    private function dispatchDueReminders(OperationalNotifier $notifier): int
    {
        $sent = 0;

        $dueIds = CalendarReminder::query()
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->whereHas('item', fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id')
            ->limit(200)
            ->pluck('id');

        foreach ($dueIds as $reminderId) {
            $claimed = DB::transaction(function () use ($reminderId) {
                $reminder = CalendarReminder::query()
                    ->whereKey($reminderId)
                    ->whereNull('sent_at')
                    ->lockForUpdate()
                    ->first();

                if ($reminder === null) {
                    return null;
                }

                $updated = CalendarReminder::query()
                    ->whereKey($reminder->id)
                    ->whereNull('sent_at')
                    ->update(['sent_at' => now()]);

                if ($updated !== 1) {
                    return null;
                }

                return $reminder->fresh(['item.assignees', 'item.creator']);
            });

            if ($claimed === null) {
                continue;
            }

            $item = $claimed->item;
            if ($item === null || $item->trashed() || ! $item->isOpen()) {
                continue;
            }

            $recipients = $item->assignees;
            if ($recipients->isEmpty() && $item->creator) {
                $recipients = collect([$item->creator]);
            }

            foreach ($recipients as $recipient) {
                if (! $recipient instanceof User || ! $recipient->is_active) {
                    continue;
                }

                if (! ($recipient->role instanceof UserRole) || ! $recipient->role->canAccessWorkCalendar()) {
                    continue;
                }

                $prefs = UserNotificationPreference::query()->where('user_id', $recipient->id)->first();
                if ($prefs !== null && ! $prefs->task_reminders) {
                    continue;
                }

                $notifier->notify($recipient, new CalendarNotification([
                    'type' => 'calendar_reminder',
                    'title' => 'تذكير: '.$item->title,
                    'message' => 'اقترب موعد "'.$item->title.'"',
                    'href' => $this->hrefFor($recipient, (int) $item->id),
                    'calendar_item_id' => $item->id,
                ]), 'explicit_calendar_reminder');
                $sent++;
            }
        }

        return $sent;
    }

    private function dispatchOverdueDigests(OperationalNotifier $notifier): int
    {
        $sent = 0;
        $dayStart = now()->startOfDay();

        $overdueItems = CalendarItem::query()
            ->with(['assignees', 'creator'])
            ->where('status', CalendarItemStatus::Overdue->value)
            ->whereNull('deleted_at')
            ->limit(200)
            ->get();

        foreach ($overdueItems as $item) {
            $already = CalendarItemActivity::query()
                ->where('calendar_item_id', $item->id)
                ->where('action', 'overdue_notified')
                ->where('created_at', '>=', $dayStart)
                ->exists();

            if ($already) {
                continue;
            }

            $recipients = $item->assignees;
            if ($recipients->isEmpty() && $item->creator) {
                $recipients = collect([$item->creator]);
            }

            foreach ($recipients as $recipient) {
                if (! $recipient instanceof User || ! $recipient->is_active) {
                    continue;
                }

                $prefs = UserNotificationPreference::query()->where('user_id', $recipient->id)->first();
                if ($prefs !== null && ! $prefs->overdue_alerts) {
                    continue;
                }

                $notifier->notify($recipient, new CalendarNotification([
                    'type' => 'calendar_overdue',
                    'title' => 'عنصر متأخر: '.$item->title,
                    'message' => 'ما زال "'.$item->title.'" متأخراً',
                    'href' => $this->hrefFor($recipient, (int) $item->id),
                    'calendar_item_id' => $item->id,
                ]), 'operational');
                $sent++;
            }

            CalendarItemActivity::query()->create([
                'calendar_item_id' => $item->id,
                'user_id' => null,
                'action' => 'overdue_notified',
                'summary' => 'تم إرسال تنبيه التأخير اليومي',
                'meta' => null,
                'created_at' => now(),
            ]);
        }

        return $sent;
    }

    private function hrefFor(User $recipient, int $itemId): string
    {
        return match (true) {
            $recipient->role === UserRole::Owner => '/owner/calendar?item='.$itemId,
            $recipient->role instanceof UserRole && $recipient->role->canAccessCrm() => '/crm/work-calendar?item='.$itemId,
            default => '/workspace/calendar?item='.$itemId,
        };
    }
}
