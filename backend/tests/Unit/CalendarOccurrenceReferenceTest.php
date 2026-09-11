<?php

namespace Tests\Unit;

use App\Support\Calendar\CalendarDateTime;
use App\Support\Calendar\CalendarOccurrenceReference;
use App\Support\Calendar\CalendarWorkloadRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalendarOccurrenceReferenceTest extends TestCase
{
    public function test_parses_real_and_virtual_ids(): void
    {
        $real = CalendarOccurrenceReference::parse('42');
        $this->assertSame(42, $real->itemId());
        $this->assertFalse($real->isVirtual());

        $virtual = CalendarOccurrenceReference::parse('42:2026-09-15');
        $this->assertSame(42, $virtual->itemId());
        $this->assertTrue($virtual->isVirtual());
        $this->assertSame('2026-09-15', $virtual->occurrenceDateString());
        $this->assertSame('42:2026-09-15', $virtual->toListId());
    }

    public function test_rejects_invalid_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalendarOccurrenceReference::parse('abc');
    }

    public function test_all_day_normalization_keeps_calendar_date(): void
    {
        $date = CalendarDateTime::allDayUtcDate('2026-09-10T21:00:00+03:00');
        $this->assertSame('2026-09-10', $date->toDateString());
        $this->assertSame('20260910', CalendarDateTime::icsAllDayDate($date));
    }

    public function test_workload_levels(): void
    {
        $this->assertSame('light', CalendarWorkloadRules::level(2));
        $this->assertSame('medium', CalendarWorkloadRules::level(6));
        $this->assertSame('heavy', CalendarWorkloadRules::level(3, urgentCount: 3, overdueCount: 2));
    }
}
