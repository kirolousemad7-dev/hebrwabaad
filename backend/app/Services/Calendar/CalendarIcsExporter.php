<?php

namespace App\Services\Calendar;

use App\Models\CalendarItem;
use App\Support\Calendar\CalendarDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarIcsExporter
{
    /**
     * @param  Collection<int, CalendarItem>|iterable<CalendarItem>  $items
     */
    public function exportMany(iterable $items, string $calendarName = 'Hebr Calendar'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Hebr Abaad//Work Calendar//AR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText($calendarName),
        ];

        foreach ($items as $item) {
            if (! $item instanceof CalendarItem) {
                continue;
            }
            $lines = array_merge($lines, $this->veventLines($item));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    public function exportOne(CalendarItem $item): string
    {
        return $this->exportMany([$item], (string) $item->title);
    }

    /**
     * @return list<string>
     */
    private function veventLines(CalendarItem $item): array
    {
        $uid = 'calendar-item-'.$item->id.'@hebr-abaad';
        $stamp = now('UTC')->format('Ymd\THis\Z');
        $starts = $this->formatDate($item->starts_at, (bool) $item->all_day);
        $ends = $item->ends_at !== null
            ? $this->formatDate($item->ends_at, (bool) $item->all_day)
            : ($item->all_day
                ? $this->formatDate(($item->starts_at ?? now())->copy()->addDay(), true)
                : $this->formatDate(($item->starts_at ?? now())->copy()->addHour(), false));

        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            ($item->all_day ? 'DTSTART;VALUE=DATE:' : 'DTSTART:').$starts,
            ($item->all_day ? 'DTEND;VALUE=DATE:' : 'DTEND:').$ends,
            'SUMMARY:'.$this->escapeText((string) $item->title),
        ];

        if (filled($item->description)) {
            $lines[] = 'DESCRIPTION:'.$this->escapeText((string) $item->description);
        }
        if (filled($item->location)) {
            $lines[] = 'LOCATION:'.$this->escapeText((string) $item->location);
        }
        if (filled($item->meeting_url)) {
            $lines[] = 'URL:'.$this->escapeText((string) $item->meeting_url);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function formatDate(?Carbon $date, bool $allDay): string
    {
        if ($allDay) {
            return CalendarDateTime::icsAllDayDate($date);
        }

        $date ??= now('UTC');

        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }
}
