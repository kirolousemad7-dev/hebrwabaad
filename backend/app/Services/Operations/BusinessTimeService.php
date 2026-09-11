<?php

namespace App\Services\Operations;

use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessTimeService
{
    /** @var list<int> Sunday–Thursday (Gulf default). */
    public const DEFAULT_WORKING_DAYS = [0, 1, 2, 3, 4];

    public const DEFAULT_TIMEZONE = 'Asia/Riyadh';

    public const DEFAULT_WORK_START = '09:00:00';

    public const DEFAULT_WORK_END = '17:00:00';

    /** @var array<int, array<string, true>> */
    private array $holidayCache = [];

    public function hasActiveCalendar(): bool
    {
        return BusinessCalendar::query()->where('is_active', true)->exists();
    }

    /**
     * Resolve the default active calendar, creating a Gulf Sun–Thu calendar on first use.
     */
    public function defaultCalendar(): BusinessCalendar
    {
        $existing = BusinessCalendar::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $anyActive = BusinessCalendar::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($anyActive !== null) {
            return $anyActive;
        }

        return BusinessCalendar::query()->create([
            'name' => 'Default (Gulf)',
            'timezone' => self::DEFAULT_TIMEZONE,
            'week_start' => 0,
            'working_days' => self::DEFAULT_WORKING_DAYS,
            'work_start' => self::DEFAULT_WORK_START,
            'work_end' => self::DEFAULT_WORK_END,
            'is_default' => true,
            'is_active' => true,
            'created_by' => null,
            'department_id' => null,
        ]);
    }

    /**
     * Resolve calendar for SLA / business-time evaluation.
     *
     * Priority:
     * 1. Explicit business_calendar_id (SLA rule override)
     * 2. Active department calendar for department_id
     * 3. Company default (is_default)
     */
    public function resolveCalendar(?int $businessCalendarId = null, ?int $departmentId = null): BusinessCalendar
    {
        if ($businessCalendarId !== null) {
            $explicit = BusinessCalendar::query()
                ->whereKey($businessCalendarId)
                ->where('is_active', true)
                ->first();

            if ($explicit !== null) {
                return $explicit;
            }
        }

        if ($departmentId !== null) {
            $departmentCalendar = BusinessCalendar::query()
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();

            if ($departmentCalendar !== null) {
                return $departmentCalendar;
            }
        }

        return $this->defaultCalendar();
    }

    public function isWorkingDay(CarbonInterface $date, ?BusinessCalendar $calendar = null): bool
    {
        $calendar ??= $this->defaultCalendar();
        $local = $this->inCalendarTz($date, $calendar);
        $dow = (int) $local->dayOfWeek;
        $workingDays = $this->workingDays($calendar);

        if (! in_array($dow, $workingDays, true)) {
            return false;
        }

        $day = $local->toDateString();
        $holidays = $this->holidayDates($calendar);

        return ! isset($holidays[$day]);
    }

    public function addBusinessMinutes(CarbonInterface $start, int $minutes, ?BusinessCalendar $calendar = null): Carbon
    {
        $calendar ??= $this->defaultCalendar();
        $remaining = max(0, $minutes);
        $cursor = $this->snapToOpen($this->inCalendarTz($start, $calendar), $calendar);

        if ($remaining === 0) {
            return $cursor->copy()->utc();
        }

        $guard = 0;
        while ($remaining > 0 && $guard < 10000) {
            $guard++;

            if (! $this->isWorkingDay($cursor, $calendar)) {
                $cursor = $this->nextOpenStart($cursor, $calendar);

                continue;
            }

            $dayEnd = $this->workEndOn($cursor, $calendar);
            if ($cursor->gte($dayEnd)) {
                $cursor = $this->nextOpenStart($cursor, $calendar);

                continue;
            }

            $available = (int) $cursor->diffInMinutes($dayEnd, false);
            if ($available <= 0) {
                $cursor = $this->nextOpenStart($cursor, $calendar);

                continue;
            }

            if ($remaining <= $available) {
                return $cursor->copy()->addMinutes($remaining)->utc();
            }

            $remaining -= $available;
            $cursor = $this->nextOpenStart($cursor, $calendar);
        }

        return $cursor->utc();
    }

    public function businessMinutesBetween(CarbonInterface $from, CarbonInterface $to, ?BusinessCalendar $calendar = null): int
    {
        $calendar ??= $this->defaultCalendar();
        $start = $this->inCalendarTz($from, $calendar);
        $end = $this->inCalendarTz($to, $calendar);

        if ($end->lte($start)) {
            return 0;
        }

        $cursor = $start->copy();
        $total = 0;
        $guard = 0;

        while ($cursor->lt($end) && $guard < 10000) {
            $guard++;

            if (! $this->isWorkingDay($cursor, $calendar)) {
                $cursor = $this->nextOpenStart($cursor, $calendar);
                if ($cursor->gte($end)) {
                    break;
                }

                continue;
            }

            $open = $this->workStartOn($cursor, $calendar);
            $close = $this->workEndOn($cursor, $calendar);

            if ($cursor->lt($open)) {
                $cursor = $open->copy();
            }

            if ($cursor->gte($close)) {
                $cursor = $this->nextOpenStart($cursor, $calendar);

                continue;
            }

            $segmentEnd = $end->lt($close) ? $end->copy() : $close->copy();
            if ($cursor->lt($segmentEnd)) {
                $total += (int) $cursor->diffInMinutes($segmentEnd, false);
            }

            $cursor = $this->nextOpenStart($cursor, $calendar);
        }

        return max(0, $total);
    }

    /**
     * Snap a timestamp to the next (or current) open business moment.
     */
    public function snapToOpen(CarbonInterface $moment, ?BusinessCalendar $calendar = null): Carbon
    {
        $calendar ??= $this->defaultCalendar();
        $cursor = $this->inCalendarTz($moment, $calendar);

        $guard = 0;
        while ($guard < 400) {
            $guard++;

            if (! $this->isWorkingDay($cursor, $calendar)) {
                $cursor = $this->nextOpenStart($cursor, $calendar);

                continue;
            }

            $open = $this->workStartOn($cursor, $calendar);
            $close = $this->workEndOn($cursor, $calendar);

            if ($cursor->lt($open)) {
                return $open->copy();
            }

            if ($cursor->lt($close)) {
                return $cursor->copy();
            }

            $cursor = $this->nextOpenStart($cursor, $calendar);
        }

        return $cursor;
    }

    private function inCalendarTz(CarbonInterface $moment, BusinessCalendar $calendar): Carbon
    {
        return Carbon::parse($moment)->timezone($calendar->timezone ?: self::DEFAULT_TIMEZONE);
    }

    /**
     * @return list<int>
     */
    private function workingDays(BusinessCalendar $calendar): array
    {
        $days = $calendar->working_days;
        if (! is_array($days) || $days === []) {
            return self::DEFAULT_WORKING_DAYS;
        }

        return array_values(array_map('intval', $days));
    }

    /**
     * @return array<string, true>
     */
    private function holidayDates(BusinessCalendar $calendar): array
    {
        $id = (int) $calendar->id;
        if (isset($this->holidayCache[$id])) {
            return $this->holidayCache[$id];
        }

        $dates = [];
        foreach (
            BusinessCalendarHoliday::query()
                ->where('business_calendar_id', $id)
                ->pluck('date') as $date
        ) {
            $key = $date instanceof CarbonInterface
                ? $date->toDateString()
                : Carbon::parse((string) $date)->toDateString();
            $dates[$key] = true;
        }

        return $this->holidayCache[$id] = $dates;
    }

    private function workStartOn(CarbonInterface $day, BusinessCalendar $calendar): Carbon
    {
        return $this->combineTime($day, (string) ($calendar->work_start ?: self::DEFAULT_WORK_START), $calendar);
    }

    private function workEndOn(CarbonInterface $day, BusinessCalendar $calendar): Carbon
    {
        return $this->combineTime($day, (string) ($calendar->work_end ?: self::DEFAULT_WORK_END), $calendar);
    }

    private function combineTime(CarbonInterface $day, string $time, BusinessCalendar $calendar): Carbon
    {
        $tz = $calendar->timezone ?: self::DEFAULT_TIMEZONE;
        $time = strlen($time) === 5 ? $time.':00' : $time;

        return Carbon::parse($day->toDateString().' '.$time, $tz);
    }

    private function nextOpenStart(CarbonInterface $from, BusinessCalendar $calendar): Carbon
    {
        $cursor = $this->inCalendarTz($from, $calendar)->startOfDay()->addDay();

        $guard = 0;
        while ($guard < 400) {
            $guard++;
            if ($this->isWorkingDay($cursor, $calendar)) {
                return $this->workStartOn($cursor, $calendar);
            }
            $cursor = $cursor->addDay();
        }

        return $this->workStartOn($cursor, $calendar);
    }
}
