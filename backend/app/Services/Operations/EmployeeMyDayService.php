<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Models\CalendarItem;
use App\Models\User;
use App\Services\Calendar\CalendarService;
use App\Services\Operations\Work\UnifiedWorkService;

class EmployeeMyDayService
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly UnifiedWorkService $unifiedWork,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $actor): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $upcomingEnd = now()->addDays(7)->endOfDay();

        $mine = function ($query) use ($actor): void {
            $query->where(function ($builder) use ($actor): void {
                $builder->where('created_by', $actor->id)
                    ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $actor->id));
            });
        };

        $unifiedTodayOverdue = $this->unifiedWork->list($actor, [
            'scope' => 'mine',
            'bucket' => 'today_overdue',
            'per_page' => 50,
            'page' => 1,
            'sort' => 'overdue_first',
        ]);

        $todayCalendar = CalendarItem::query()
            ->with(['assignees:id,name', 'creator:id,name', 'reminders'])
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->whereNotIn('status', [CalendarItemStatus::Cancelled->value])
            ->where('type', '!=', CalendarItemType::Task->value)
            ->tap($mine)
            ->get();

        $overdueCalendarNonTask = CalendarItem::query()
            ->with(['assignees:id,name', 'creator:id,name', 'reminders'])
            ->where('status', CalendarItemStatus::Overdue->value)
            ->where('type', '!=', CalendarItemType::Task->value)
            ->tap($mine)
            ->orderBy('starts_at')
            ->limit(30)
            ->get();

        $upcoming = CalendarItem::query()
            ->with(['assignees:id,name', 'creator:id,name', 'reminders'])
            ->where('starts_at', '>', $todayEnd)
            ->where('starts_at', '<=', $upcomingEnd)
            ->whereNotIn('status', [CalendarItemStatus::Cancelled->value, CalendarItemStatus::Completed->value])
            ->tap($mine)
            ->orderBy('starts_at')
            ->limit(30)
            ->get();

        $unifiedItems = $unifiedTodayOverdue['items'];
        $calendarExtras = $todayCalendar->concat($overdueCalendarNonTask)->unique('id')->values();
        $sortedExtras = $calendarExtras->sort(function (CalendarItem $a, CalendarItem $b): int {
            return $this->priorityScore($a) <=> $this->priorityScore($b)
                ?: strcmp(
                    (string) ($a->starts_at?->toIso8601String() ?? ''),
                    (string) ($b->starts_at?->toIso8601String() ?? ''),
                )
                ?: $a->id <=> $b->id;
        })->values();

        $items = array_merge(
            $unifiedItems,
            $sortedExtras->map(fn (CalendarItem $item) => $this->calendar->serialize($item))->all(),
        );

        $overdueUnified = array_values(array_filter(
            $unifiedItems,
            fn (array $row): bool => (bool) ($row['is_overdue'] ?? false),
        ));

        return [
            'date' => $todayStart->toDateString(),
            'items' => $items,
            'work_items' => $unifiedItems,
            'overdue' => array_merge(
                $overdueUnified,
                $overdueCalendarNonTask->map(fn (CalendarItem $item) => $this->calendar->serialize($item))->all(),
            ),
            'upcoming' => $upcoming->map(fn (CalendarItem $item) => $this->calendar->serialize($item))->all(),
            'counts' => [
                'today' => count(array_filter(
                    $unifiedItems,
                    function (array $row) use ($todayStart): bool {
                        $due = $row['due_at'] ?? ($row['starts_at'] ? substr((string) $row['starts_at'], 0, 10) : null);

                        return $due === $todayStart->toDateString() && ! ($row['is_overdue'] ?? false);
                    },
                )) + $todayCalendar->count(),
                'overdue' => count($overdueUnified) + $overdueCalendarNonTask->count(),
                'upcoming' => $upcoming->count(),
                'meetings_today' => $todayCalendar->filter(fn (CalendarItem $item) => in_array(
                    $item->type instanceof CalendarItemType ? $item->type->value : $item->type,
                    [CalendarItemType::Meeting->value, CalendarItemType::Appointment->value, CalendarItemType::Call->value],
                    true,
                ))->count(),
                'work_today_overdue' => count($unifiedItems),
            ],
        ];
    }

    private function priorityScore(CalendarItem $item): int
    {
        $status = $item->status instanceof CalendarItemStatus ? $item->status->value : (string) $item->status;
        $priority = $item->priority instanceof CalendarItemPriority ? $item->priority->value : (string) $item->priority;
        $type = $item->type instanceof CalendarItemType ? $item->type->value : (string) $item->type;

        $score = 50;
        if ($status === CalendarItemStatus::Overdue->value) {
            $score -= 40;
        }
        $score -= match ($priority) {
            CalendarItemPriority::Urgent->value => 30,
            CalendarItemPriority::High->value => 20,
            CalendarItemPriority::Medium->value => 10,
            default => 0,
        };
        if (in_array($type, [CalendarItemType::Meeting->value, CalendarItemType::Call->value], true)) {
            $score -= 5;
        }

        return $score;
    }
}
