<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Carbon\Carbon;

/**
 * Quiet-hours policy for operational notifications.
 *
 * Delayed categories (respect quiet hours when enabled):
 * - operational — calendar assignments / general ops alerts
 * - digest — daily digests
 * - automation — workflow notify_user actions
 *
 * Never delayed:
 * - explicit_calendar_reminder — user-configured calendar reminder offsets
 * - critical_escalation — overdue escalation levels that must surface immediately
 */
class QuietHoursService
{
    public function isQuiet(User $user): bool
    {
        $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();
        if ($prefs === null || ! $prefs->quiet_hours_enabled) {
            return false;
        }

        $timezone = is_string($prefs->quiet_hours_timezone) && $prefs->quiet_hours_timezone !== ''
            ? $prefs->quiet_hours_timezone
            : config('app.timezone', 'UTC');

        $now = Carbon::now($timezone);
        $start = $this->parseTime($prefs->quiet_hours_start, $timezone);
        $end = $this->parseTime($prefs->quiet_hours_end, $timezone);

        if ($start === null || $end === null) {
            return false;
        }

        $startToday = $now->copy()->setTimeFromTimeString($start);
        $endToday = $now->copy()->setTimeFromTimeString($end);

        if ($startToday->equalTo($endToday)) {
            return false;
        }

        // Overnight window (e.g. 22:00 → 07:00)
        if ($endToday->lessThanOrEqualTo($startToday)) {
            return $now->greaterThanOrEqualTo($startToday) || $now->lessThan($endToday);
        }

        return $now->greaterThanOrEqualTo($startToday) && $now->lessThan($endToday);
    }

    /**
     * @param  'operational'|'digest'|'automation'|'explicit_calendar_reminder'|'critical_escalation'|string  $category
     */
    public function shouldDelay(User $user, string $category): bool
    {
        if (in_array($category, ['explicit_calendar_reminder', 'critical_escalation'], true)) {
            return false;
        }

        if (! in_array($category, ['operational', 'digest', 'automation'], true)) {
            return false;
        }

        return $this->isQuiet($user);
    }

    public function nextQuietEnd(User $user): Carbon
    {
        $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();
        $timezone = is_string($prefs?->quiet_hours_timezone) && $prefs->quiet_hours_timezone !== ''
            ? $prefs->quiet_hours_timezone
            : config('app.timezone', 'UTC');

        $now = Carbon::now($timezone);
        $end = $this->parseTime($prefs?->quiet_hours_end, $timezone) ?? '07:00';
        $endToday = $now->copy()->setTimeFromTimeString($end);

        if ($endToday->lessThanOrEqualTo($now)) {
            $endToday->addDay();
        }

        return $endToday->utc();
    }

    private function parseTime(mixed $value, string $timezone): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $string = (string) $value;
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $string) !== 1) {
            return null;
        }

        return strlen($string) === 5 ? $string.':00' : $string;
    }
}
