<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\TaskStatus;
use App\Models\CalendarItem;
use App\Models\Project;
use App\Models\Task;

class ProjectHealthService
{
    /**
     * @return array{status: string, label: string, overdue_workspace_tasks: int, overdue_calendar_tasks: int, days_to_deadline: ?int}
     */
    public function evaluate(Project $project): array
    {
        $overdueWorkspace = Task::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', now()->toDateString())
            ->count();

        $overdueCalendar = CalendarItem::query()
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('type', CalendarItemType::Task->value)
            ->where(function ($query): void {
                $query->where('status', CalendarItemStatus::Overdue->value)
                    ->orWhere(function ($inner): void {
                        $inner->whereIn('status', [
                            CalendarItemStatus::Scheduled->value,
                            CalendarItemStatus::InProgress->value,
                        ])->where('starts_at', '<', now());
                    });
            })
            ->count();

        $daysToDeadline = null;
        if ($project->deadline !== null) {
            $daysToDeadline = (int) now()->startOfDay()->diffInDays($project->deadline->copy()->startOfDay(), false);
        }

        $status = 'on_track';
        if ($overdueWorkspace > 0 || $overdueCalendar > 0 || ($daysToDeadline !== null && $daysToDeadline < 0)) {
            $status = 'overdue';
        } elseif ($daysToDeadline !== null && $daysToDeadline <= 7) {
            $status = 'needs_attention';
        }

        $label = match ($status) {
            'overdue' => 'متأخر',
            'needs_attention' => 'يحتاج متابعة',
            default => 'على المسار',
        };

        return [
            'status' => $status,
            'label' => $label,
            'overdue_workspace_tasks' => $overdueWorkspace,
            'overdue_calendar_tasks' => $overdueCalendar,
            'days_to_deadline' => $daysToDeadline,
        ];
    }
}
