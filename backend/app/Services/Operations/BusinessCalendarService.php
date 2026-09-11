<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessCalendarService
{
    public function __construct(
        private readonly OperationsAuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return BusinessCalendar::query()
            ->withCount('holidays')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (BusinessCalendar $calendar) => $this->serialize($calendar))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): BusinessCalendar
    {
        $this->assertCanManage($actor);

        return DB::transaction(function () use ($actor, $attributes) {
            $isDefault = (bool) ($attributes['is_default'] ?? false);
            if ($isDefault) {
                BusinessCalendar::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $calendar = BusinessCalendar::query()->create([
                'name' => $attributes['name'],
                'timezone' => $attributes['timezone'] ?? BusinessTimeService::DEFAULT_TIMEZONE,
                'week_start' => (int) ($attributes['week_start'] ?? 0),
                'working_days' => $attributes['working_days'] ?? BusinessTimeService::DEFAULT_WORKING_DAYS,
                'work_start' => $attributes['work_start'] ?? BusinessTimeService::DEFAULT_WORK_START,
                'work_end' => $attributes['work_end'] ?? BusinessTimeService::DEFAULT_WORK_END,
                'is_default' => $isDefault,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'created_by' => $actor->id,
                'department_id' => $attributes['department_id'] ?? null,
            ]);

            $this->audit->log($actor, 'business_calendar.created', $calendar, [
                'timezone' => $calendar->timezone,
                'working_days' => $calendar->working_days,
            ]);

            return $calendar->loadCount('holidays');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, BusinessCalendar $calendar, array $attributes): BusinessCalendar
    {
        $this->assertCanManage($actor);

        return DB::transaction(function () use ($actor, $calendar, $attributes) {
            if (array_key_exists('is_default', $attributes) && (bool) $attributes['is_default']) {
                BusinessCalendar::query()
                    ->where('is_default', true)
                    ->where('id', '!=', $calendar->id)
                    ->update(['is_default' => false]);
            }

            $calendar->fill(collect($attributes)->only([
                'name',
                'timezone',
                'week_start',
                'working_days',
                'work_start',
                'work_end',
                'is_default',
                'is_active',
                'department_id',
            ])->all())->save();

            $this->audit->log($actor, 'business_calendar.updated', $calendar, [
                'changes' => array_keys($attributes),
            ]);

            return $calendar->fresh()->loadCount('holidays') ?? $calendar;
        });
    }

    public function delete(User $actor, BusinessCalendar $calendar): void
    {
        $this->assertCanManage($actor);
        $calendar->delete();
        $this->audit->log($actor, 'business_calendar.deleted', $calendar);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addHoliday(User $actor, BusinessCalendar $calendar, array $attributes): BusinessCalendarHoliday
    {
        $this->assertCanManage($actor);

        $holiday = $calendar->holidays()->create([
            'date' => $attributes['date'],
            'name' => $attributes['name'],
        ]);

        $this->audit->log($actor, 'business_calendar.holiday_added', $calendar, [
            'holiday_id' => $holiday->id,
            'date' => (string) $holiday->date?->toDateString(),
            'name' => $holiday->name,
        ]);

        return $holiday;
    }

    public function removeHoliday(User $actor, BusinessCalendar $calendar, BusinessCalendarHoliday $holiday): void
    {
        $this->assertCanManage($actor);

        if ((int) $holiday->business_calendar_id !== (int) $calendar->id) {
            throw ValidationException::withMessages([
                'holiday' => ['Holiday does not belong to this calendar.'],
            ]);
        }

        $holiday->delete();
        $this->audit->log($actor, 'business_calendar.holiday_removed', $calendar, [
            'holiday_id' => $holiday->id,
        ]);
    }

    /**
     * Import holidays from CSV (date,name). Preview mode validates without writing.
     *
     * @return array{
     *     preview: bool,
     *     total_rows: int,
     *     valid: list<array{date: string, name: string}>,
     *     invalid: list<array{row: int, date: string|null, name: string|null, errors: list<string>}>,
     *     duplicates_skipped: int,
     *     imported: int
     * }
     */
    public function importHolidays(User $actor, BusinessCalendar $calendar, string $csv, bool $preview = false): array
    {
        $this->assertCanManage($actor);

        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $existing = $calendar->holidays()->pluck('date')->map(
            fn ($date): string => $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) $date
        )->all();
        $existingLookup = array_fill_keys($existing, true);
        $seenInFile = [];

        $valid = [];
        $invalid = [];
        $duplicatesSkipped = 0;

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 1;
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if ($rowNumber === 1 && preg_match('/^date\s*[,;]/i', $trimmed) === 1) {
                continue;
            }

            $parts = str_getcsv($trimmed);
            if (count($parts) < 2) {
                $parts = preg_split('/[;\t]/', $trimmed) ?: [];
            }

            $rawDate = isset($parts[0]) ? trim((string) $parts[0]) : '';
            $rawName = isset($parts[1]) ? trim((string) $parts[1]) : '';
            $errors = [];

            $normalizedDate = null;
            if ($rawDate === '') {
                $errors[] = 'Date is required.';
            } else {
                try {
                    $normalizedDate = Carbon::parse($rawDate)->toDateString();
                } catch (\Throwable) {
                    $errors[] = 'Invalid date.';
                }
            }

            if ($rawName === '') {
                $errors[] = 'Name is required.';
            } elseif (mb_strlen($rawName) > 255) {
                $errors[] = 'Name is too long.';
            }

            if ($normalizedDate !== null) {
                if (isset($seenInFile[$normalizedDate]) || isset($existingLookup[$normalizedDate])) {
                    $duplicatesSkipped++;
                    $errors[] = 'Duplicate date.';
                }
            }

            if ($errors !== []) {
                $invalid[] = [
                    'row' => $rowNumber,
                    'date' => $normalizedDate ?? ($rawDate !== '' ? $rawDate : null),
                    'name' => $rawName !== '' ? $rawName : null,
                    'errors' => $errors,
                ];

                continue;
            }

            $seenInFile[$normalizedDate] = true;
            $valid[] = [
                'date' => $normalizedDate,
                'name' => $rawName,
            ];
        }

        $imported = 0;
        if (! $preview && $valid !== []) {
            DB::transaction(function () use ($actor, $calendar, $valid, &$imported): void {
                foreach ($valid as $row) {
                    $calendar->holidays()->create([
                        'date' => $row['date'],
                        'name' => $row['name'],
                    ]);
                    $imported++;
                }

                $this->audit->log($actor, 'business_calendar.holidays_imported', $calendar, [
                    'imported' => $imported,
                ]);
            });
        }

        return [
            'preview' => $preview,
            'total_rows' => count($valid) + count($invalid),
            'valid' => $valid,
            'invalid' => $invalid,
            'duplicates_skipped' => $duplicatesSkipped,
            'imported' => $imported,
        ];
    }

    /**
     * Copy holidays from one calendar year to another (same month/day).
     *
     * @return array{from_year: int, to_year: int, copied: int, skipped: int}
     */
    public function copyYear(User $actor, BusinessCalendar $calendar, int $fromYear, int $toYear): array
    {
        $this->assertCanManage($actor);

        if ($fromYear < 2000 || $fromYear > 2100 || $toYear < 2000 || $toYear > 2100) {
            throw ValidationException::withMessages([
                'year' => ['Years must be between 2000 and 2100.'],
            ]);
        }

        if ($fromYear === $toYear) {
            throw ValidationException::withMessages([
                'to_year' => ['to_year must differ from from_year.'],
            ]);
        }

        $source = $calendar->holidays()
            ->whereYear('date', $fromYear)
            ->orderBy('date')
            ->get();

        $existingTarget = $calendar->holidays()
            ->whereYear('date', $toYear)
            ->pluck('date')
            ->map(fn ($date): string => $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) $date)
            ->all();
        $existingLookup = array_fill_keys($existingTarget, true);

        $copied = 0;
        $skipped = 0;

        DB::transaction(function () use ($actor, $calendar, $source, $fromYear, $toYear, $existingLookup, &$copied, &$skipped): void {
            foreach ($source as $holiday) {
                $sourceDate = $holiday->date?->toDateString();
                if ($sourceDate === null) {
                    $skipped++;

                    continue;
                }

                $targetDate = $toYear.substr($sourceDate, 4);
                if (isset($existingLookup[$targetDate])) {
                    $skipped++;

                    continue;
                }

                // Skip Feb 29 → non-leap target years.
                if (! checkdate((int) substr($targetDate, 5, 2), (int) substr($targetDate, 8, 2), $toYear)) {
                    $skipped++;

                    continue;
                }

                $calendar->holidays()->create([
                    'date' => $targetDate,
                    'name' => $holiday->name,
                ]);
                $existingLookup[$targetDate] = true;
                $copied++;
            }

            $this->audit->log($actor, 'business_calendar.holidays_copied_year', $calendar, [
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'copied' => $copied,
                'skipped' => $skipped,
            ]);
        });

        return [
            'from_year' => $fromYear,
            'to_year' => $toYear,
            'copied' => $copied,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(BusinessCalendar $calendar, bool $withHolidays = false): array
    {
        $data = [
            'id' => $calendar->id,
            'name' => $calendar->name,
            'timezone' => $calendar->timezone,
            'week_start' => $calendar->week_start,
            'working_days' => $calendar->working_days ?? [],
            'work_start' => $this->formatTime($calendar->work_start),
            'work_end' => $this->formatTime($calendar->work_end),
            'is_default' => (bool) $calendar->is_default,
            'is_active' => (bool) $calendar->is_active,
            'department_id' => $calendar->department_id,
            'created_by' => $calendar->created_by,
            'holidays_count' => $calendar->holidays_count ?? $calendar->holidays()->count(),
            'created_at' => $calendar->created_at?->toIso8601String(),
            'updated_at' => $calendar->updated_at?->toIso8601String(),
        ];

        if ($withHolidays) {
            $data['holidays'] = $calendar->holidays()
                ->orderBy('date')
                ->get()
                ->map(fn (BusinessCalendarHoliday $h) => [
                    'id' => $h->id,
                    'date' => $h->date?->toDateString(),
                    'name' => $h->name,
                ])
                ->all();
        }

        return $data;
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null) {
            return BusinessTimeService::DEFAULT_WORK_START;
        }

        if (is_string($value)) {
            return strlen($value) >= 8 ? substr($value, 0, 8) : $value;
        }

        return (string) $value;
    }

    private function assertCanManage(User $actor): void
    {
        if (! ($actor->role instanceof UserRole)
            || ! in_array($actor->role, [UserRole::Owner, UserRole::AdminManager], true)
        ) {
            throw ValidationException::withMessages([
                'calendar' => ['Only Owner or Admin Manager can manage business calendars.'],
            ]);
        }
    }
}
