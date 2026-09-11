<?php

namespace App\Console\Commands;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\UserRole;
use App\Models\CalendarItem;
use App\Models\CalendarUserSetting;
use App\Models\User;
use App\Notifications\CalendarNotification;
use Illuminate\Console\Command;

class CalendarDailyDigest extends Command
{
    protected $signature = 'calendar:daily-digest';

    protected $description = 'Send daily calendar digests to users with daily_digest enabled';

    public function handle(): int
    {
        $settings = CalendarUserSetting::query()
            ->where('daily_digest', true)
            ->with('user')
            ->get();

        $sent = 0;

        foreach ($settings as $setting) {
            $user = $setting->user;
            if (! $user instanceof User || ! $user->is_active) {
                continue;
            }

            if (! ($user->role instanceof UserRole) || ! $user->role->canAccessWorkCalendar()) {
                continue;
            }

            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();

            $query = CalendarItem::query()
                ->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->whereNotIn('status', [CalendarItemStatus::Cancelled->value]);

            $query->where(function ($builder) use ($user): void {
                $builder->where('created_by', $user->id)
                    ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
            });

            $count = $query->count();
            $tasks = (clone $query)->where('type', CalendarItemType::Task->value)->count();
            $overdue = CalendarItem::query()
                ->where('status', CalendarItemStatus::Overdue->value)
                ->where(function ($builder) use ($user): void {
                    $builder->where('created_by', $user->id)
                        ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
                })
                ->count();

            if ($count === 0 && $overdue === 0) {
                continue;
            }

            $href = match (true) {
                $user->role === UserRole::Owner => '/owner/calendar',
                $user->role instanceof UserRole && $user->role->canAccessCrm() => '/crm/work-calendar',
                default => '/workspace/calendar',
            };

            $user->notify(new CalendarNotification([
                'type' => 'calendar_daily_digest',
                'title' => 'ملخص تقويم اليوم',
                'message' => "اليوم: {$count} عنصر ({$tasks} مهام)، متأخر: {$overdue}",
                'href' => $href,
            ]));
            $sent++;
        }

        $this->info("Sent {$sent} daily digest(s).");

        return self::SUCCESS;
    }
}
