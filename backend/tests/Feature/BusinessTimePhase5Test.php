<?php

namespace Tests\Feature;

use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\Department;
use App\Models\User;
use App\Services\Operations\BusinessTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTimePhase5Test extends TestCase
{
    use RefreshDatabase;

    private function gulfCalendar(): BusinessCalendar
    {
        return BusinessCalendar::query()->create([
            'name' => 'Gulf',
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'working_days' => [0, 1, 2, 3, 4],
            'work_start' => '09:00:00',
            'work_end' => '17:00:00',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_weekend_skip(): void
    {
        $calendar = $this->gulfCalendar();
        $service = app(BusinessTimeService::class);

        // Friday 2026-09-11 and Saturday 2026-09-12 are weekend in Gulf calendar.
        $friday = Carbon::parse('2026-09-11 10:00:00', 'Asia/Riyadh');
        $saturday = Carbon::parse('2026-09-12 10:00:00', 'Asia/Riyadh');
        $sunday = Carbon::parse('2026-09-13 10:00:00', 'Asia/Riyadh');

        $this->assertFalse($service->isWorkingDay($friday, $calendar));
        $this->assertFalse($service->isWorkingDay($saturday, $calendar));
        $this->assertTrue($service->isWorkingDay($sunday, $calendar));

        $result = $service->addBusinessMinutes($friday, 60, $calendar);
        $this->assertSame('2026-09-13', $result->timezone('Asia/Riyadh')->toDateString());
        $this->assertSame('10:00', $result->timezone('Asia/Riyadh')->format('H:i'));
    }

    public function test_holiday_skip(): void
    {
        $calendar = $this->gulfCalendar();
        BusinessCalendarHoliday::query()->create([
            'business_calendar_id' => $calendar->id,
            'date' => '2026-09-14',
            'name' => 'National Day',
        ]);

        $service = app(BusinessTimeService::class);
        $monday = Carbon::parse('2026-09-14 10:00:00', 'Asia/Riyadh');

        $this->assertFalse($service->isWorkingDay($monday, $calendar));

        $from = Carbon::parse('2026-09-13 16:00:00', 'Asia/Riyadh');
        $to = Carbon::parse('2026-09-15 10:00:00', 'Asia/Riyadh');
        // Sun 16:00–17:00 = 60m, Mon holiday skipped, Tue 09:00–10:00 = 60m → 120
        $this->assertSame(120, $service->businessMinutesBetween($from, $to, $calendar));
    }

    public function test_after_hours_start_snaps_to_next_open(): void
    {
        $calendar = $this->gulfCalendar();
        $service = app(BusinessTimeService::class);

        $evening = Carbon::parse('2026-09-13 20:00:00', 'Asia/Riyadh');
        $snapped = $service->snapToOpen($evening, $calendar);

        $this->assertSame('2026-09-14 09:00:00', $snapped->timezone('Asia/Riyadh')->format('Y-m-d H:i:s'));

        $result = $service->addBusinessMinutes($evening, 30, $calendar);
        $this->assertSame('2026-09-14 09:30:00', $result->timezone('Asia/Riyadh')->format('Y-m-d H:i:s'));
    }

    public function test_sla_spanning_days(): void
    {
        $calendar = $this->gulfCalendar();
        $service = app(BusinessTimeService::class);

        $from = Carbon::parse('2026-09-13 15:00:00', 'Asia/Riyadh'); // Sunday
        $to = Carbon::parse('2026-09-14 11:00:00', 'Asia/Riyadh'); // Monday
        // Sun 15–17 = 120, Mon 09–11 = 120 → 240
        $this->assertSame(240, $service->businessMinutesBetween($from, $to, $calendar));

        $end = $service->addBusinessMinutes($from, 240, $calendar);
        $this->assertSame('2026-09-14 11:00:00', $end->timezone('Asia/Riyadh')->format('Y-m-d H:i:s'));
    }

    public function test_timezone_asia_riyadh(): void
    {
        $calendar = $this->gulfCalendar();
        $service = app(BusinessTimeService::class);

        // 06:00 UTC = 09:00 Asia/Riyadh on a working Sunday
        $utc = Carbon::parse('2026-09-13 06:00:00', 'UTC');
        $this->assertTrue($service->isWorkingDay($utc, $calendar));

        $snapped = $service->snapToOpen($utc, $calendar);
        $this->assertSame('09:00', $snapped->timezone('Asia/Riyadh')->format('H:i'));

        $owner = User::factory()->owner()->create();
        $this->withToken($owner->createToken('auth')->plainTextToken)
            ->postJson('/api/operations/business-calendars', [
                'name' => 'Ops Gulf',
                'timezone' => 'Asia/Riyadh',
                'working_days' => [0, 1, 2, 3, 4],
                'work_start' => '09:00:00',
                'work_end' => '17:00:00',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.timezone', 'Asia/Riyadh');
    }

    public function test_department_calendar_overrides_default(): void
    {
        $default = $this->gulfCalendar();

        $department = Department::query()->create([
            'name' => 'Printing',
            'slug' => 'printing',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $deptCalendar = BusinessCalendar::query()->create([
            'name' => 'Printing nights',
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'working_days' => [0, 1, 2, 3, 4, 5], // includes Friday
            'work_start' => '10:00:00',
            'work_end' => '18:00:00',
            'is_default' => false,
            'is_active' => true,
            'department_id' => $department->id,
        ]);

        $service = app(BusinessTimeService::class);
        $friday = Carbon::parse('2026-09-11 12:00:00', 'Asia/Riyadh');

        $this->assertFalse($service->isWorkingDay($friday, $default));
        $resolved = $service->resolveCalendar(null, (int) $department->id);
        $this->assertSame($deptCalendar->id, $resolved->id);
        $this->assertTrue($service->isWorkingDay($friday, $resolved));
    }

    public function test_sla_rule_business_calendar_id_wins_over_department(): void
    {
        $this->gulfCalendar();

        $department = Department::query()->create([
            'name' => 'Design',
            'slug' => 'design',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        BusinessCalendar::query()->create([
            'name' => 'Dept calendar',
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'working_days' => [0, 1, 2, 3, 4],
            'work_start' => '09:00:00',
            'work_end' => '17:00:00',
            'is_default' => false,
            'is_active' => true,
            'department_id' => $department->id,
        ]);

        $explicit = BusinessCalendar::query()->create([
            'name' => 'Explicit SLA calendar',
            'timezone' => 'Asia/Riyadh',
            'week_start' => 0,
            'working_days' => [1, 2, 3, 4, 5],
            'work_start' => '08:00:00',
            'work_end' => '16:00:00',
            'is_default' => false,
            'is_active' => true,
        ]);

        $service = app(BusinessTimeService::class);
        $resolved = $service->resolveCalendar((int) $explicit->id, (int) $department->id);
        $this->assertSame($explicit->id, $resolved->id);
    }

    public function test_api_accepts_department_id_on_calendar_create(): void
    {
        $owner = User::factory()->owner()->create();
        $department = Department::query()->create([
            'name' => 'Events',
            'slug' => 'events',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->withToken($owner->createToken('auth')->plainTextToken)
            ->postJson('/api/operations/business-calendars', [
                'name' => 'Events calendar',
                'timezone' => 'Asia/Riyadh',
                'working_days' => [0, 1, 2, 3, 4],
                'work_start' => '09:00:00',
                'work_end' => '17:00:00',
                'department_id' => $department->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.department_id', $department->id);
    }
}
