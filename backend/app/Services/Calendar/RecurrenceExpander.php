<?php

namespace App\Services\Calendar;

use App\Models\CalendarItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Expands simple RRULE-like recurrence strings into occurrence starts (UTC).
 * Does not persist virtual instances. Hard-capped for production safety.
 */
class RecurrenceExpander
{
    public const MAX_OCCURRENCES_PER_EXPAND = 400;

    public const MAX_INTERVAL = 365;

    public const MAX_GUARD = 2000;

    private const DAY_MAP = [
        'SU' => CarbonInterface::SUNDAY,
        'MO' => CarbonInterface::MONDAY,
        'TU' => CarbonInterface::TUESDAY,
        'WE' => CarbonInterface::WEDNESDAY,
        'TH' => CarbonInterface::THURSDAY,
        'FR' => CarbonInterface::FRIDAY,
        'SA' => CarbonInterface::SATURDAY,
    ];

    /**
     * @return list<Carbon>
     */
    public function expand(CalendarItem $master, Carbon $from, Carbon $to): array
    {
        $rule = strtoupper(trim((string) $master->recurrence_rule));
        if ($rule === '' || $master->starts_at === null) {
            return [];
        }

        $parsed = $this->parseRule($rule);
        if ($parsed['freq'] === null) {
            return [];
        }

        if ($parsed['interval'] > self::MAX_INTERVAL) {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'فاصل التكرار غير مسموح.',
            ]);
        }

        $exceptions = collect($master->recurrence_exceptions ?? [])
            ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
            ->all();

        $starts = $master->starts_at->copy();
        $until = $master->recurrence_until?->copy();
        $countLimit = $master->recurrence_count !== null
            ? min((int) $master->recurrence_count, self::MAX_OCCURRENCES_PER_EXPAND)
            : null;
        $interval = max(1, min(self::MAX_INTERVAL, $parsed['interval']));
        $byDays = $parsed['byday'];

        $occurrences = [];
        $generated = 0;
        $cursor = $starts->copy();
        $guard = 0;

        while ($guard++ < self::MAX_GUARD) {
            if ($until !== null && $cursor->greaterThan($until)) {
                break;
            }
            if ($countLimit !== null && $generated >= $countLimit) {
                break;
            }
            if ($cursor->greaterThan($to)) {
                break;
            }
            if (count($occurrences) >= self::MAX_OCCURRENCES_PER_EXPAND) {
                break;
            }

            $matchesByday = $byDays === [] || in_array($cursor->dayOfWeek, $byDays, true);
            $dateKey = $cursor->toDateString();

            if ($matchesByday && ! in_array($dateKey, $exceptions, true)) {
                $generated++;
                if ($cursor->betweenIncluded($from, $to)) {
                    $occurrences[] = $cursor->copy();
                }
            }

            $cursor = $this->advance($cursor, $parsed['freq'], $interval, $starts);
        }

        return $occurrences;
    }

    /**
     * @return array{freq: ?string, interval: int, byday: list<int>}
     */
    public function parseRule(string $rule): array
    {
        $parts = [];
        foreach (explode(';', strtoupper(trim($rule))) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || ! str_contains($segment, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $segment, 2));
            $parts[strtoupper($key)] = strtoupper($value);
        }

        $freq = $parts['FREQ'] ?? null;
        if ($freq === 'BIWEEKLY') {
            $freq = 'WEEKLY';
            $parts['INTERVAL'] = (string) (max(1, (int) ($parts['INTERVAL'] ?? 1)) * 2);
        }

        $byday = [];
        if (! empty($parts['BYDAY'])) {
            foreach (explode(',', $parts['BYDAY']) as $day) {
                $day = trim($day);
                if (isset(self::DAY_MAP[$day])) {
                    $byday[] = self::DAY_MAP[$day];
                }
            }
        }

        return [
            'freq' => in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true) ? $freq : null,
            'interval' => max(1, (int) ($parts['INTERVAL'] ?? 1)),
            'byday' => $byday,
        ];
    }

    private function advance(Carbon $cursor, string $freq, int $interval, Carbon $anchor): Carbon
    {
        return match ($freq) {
            'DAILY' => $cursor->copy()->addDays($interval),
            'WEEKLY' => $cursor->copy()->addWeeks($interval),
            'MONTHLY' => $this->addMonthsClamped($cursor, $interval, $anchor->day),
            'YEARLY' => $cursor->copy()->addYears($interval),
            default => $cursor->copy()->addDays($interval),
        };
    }

    private function addMonthsClamped(Carbon $cursor, int $interval, int $anchorDay): Carbon
    {
        $next = $cursor->copy()->startOfMonth()->addMonthsNoOverflow($interval);
        $day = min($anchorDay, $next->daysInMonth);

        return $next->day($day)->setTimeFrom($cursor);
    }
}
