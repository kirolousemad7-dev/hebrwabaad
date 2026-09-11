<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\CalendarItem;
use App\Models\Department;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowAutomationRun;
use Carbon\Carbon;

class OperationsInsightsService
{
    public function __construct(
        private readonly ProjectHealthService $health,
        private readonly SlaEvaluationService $sla,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(Carbon $from, Carbon $to): array
    {
        return $this->build($from, $to, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function forPeriod(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 7;
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        return $this->build($from, $to, $days);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Carbon $from, Carbon $to, ?int $periodDays): array
    {
        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay();

        $calendarCompleted = CalendarItem::query()
            ->where('type', CalendarItemType::Task->value)
            ->where('status', CalendarItemStatus::Completed->value)
            ->where(function ($query) use ($rangeStart, $rangeEnd): void {
                $query->whereBetween('completed_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($inner) use ($rangeStart, $rangeEnd): void {
                        $inner->whereNull('completed_at')
                            ->whereBetween('updated_at', [$rangeStart, $rangeEnd]);
                    });
            })
            ->count();

        $workspaceCompleted = Task::query()
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('updated_at', [$rangeStart, $rangeEnd])
            ->count();

        $completedWork = $calendarCompleted + $workspaceCompleted;

        $calendarOverdue = CalendarItem::query()
            ->where('type', CalendarItemType::Task->value)
            ->where('status', CalendarItemStatus::Overdue->value)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->count();

        $workspaceOverdue = Task::query()
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', now()->toDateString())
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->count();

        $calendarTotal = CalendarItem::query()
            ->where('type', CalendarItemType::Task->value)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->count();

        $workspaceTotal = Task::query()
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->count();

        $totalWork = max(1, $calendarTotal + $workspaceTotal);
        $overdueTotal = $calendarOverdue + $workspaceOverdue;
        $overdueRate = round(($overdueTotal / $totalWork) * 100, 1);

        $byDepartment = [];
        $departments = Department::query()->orderBy('sort_order')->get(['id', 'name']);
        foreach ($departments as $department) {
            $deptCalendar = CalendarItem::query()
                ->where('type', CalendarItemType::Task->value)
                ->where('department_id', $department->id)
                ->whereBetween('starts_at', [$rangeStart, $rangeEnd]);

            $deptCompleted = (clone $deptCalendar)->where('status', CalendarItemStatus::Completed->value)->count();
            $deptOverdue = (clone $deptCalendar)->where('status', CalendarItemStatus::Overdue->value)->count();
            $deptTotal = (clone $deptCalendar)->count();

            $employeeIds = User::query()->where('department_id', $department->id)->pluck('id');
            $wsCompleted = Task::query()
                ->whereIn('assigned_to', $employeeIds)
                ->where('status', TaskStatus::Completed->value)
                ->whereBetween('updated_at', [$rangeStart, $rangeEnd])
                ->count();
            $wsOverdue = Task::query()
                ->whereIn('assigned_to', $employeeIds)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString())
                ->count();
            $wsTotal = Task::query()
                ->whereIn('assigned_to', $employeeIds)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->count();

            $byDepartment[] = [
                'department_id' => $department->id,
                'name' => $department->name,
                'completed' => $deptCompleted + $wsCompleted,
                'overdue' => $deptOverdue + $wsOverdue,
                'total' => $deptTotal + $wsTotal,
            ];
        }

        $byEmployee = $this->byEmployee($rangeStart, $rangeEnd);

        $projectHealth = ['on_track' => 0, 'needs_attention' => 0, 'overdue' => 0];
        $projects = Project::query()
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->limit(100)
            ->get();
        foreach ($projects as $project) {
            $status = $this->health->evaluate($project)['status'] ?? 'on_track';
            if (isset($projectHealth[$status])) {
                $projectHealth[$status]++;
            }
        }

        $printingLateness = PrintingRequest::query()
            ->whereNotNull('required_date')
            ->whereDate('required_date', '<', now()->toDateString())
            ->whereNull('quoted_at')
            ->count();

        $automationSuccess = WorkflowAutomationRun::query()
            ->where('status', WorkflowRunStatus::Success->value)
            ->whereBetween('executed_at', [$rangeStart, $rangeEnd])
            ->count();
        $automationFailed = WorkflowAutomationRun::query()
            ->where('status', WorkflowRunStatus::Failed->value)
            ->whereBetween('executed_at', [$rangeStart, $rangeEnd])
            ->count();

        $unassigned = CalendarItem::query()
            ->where('type', CalendarItemType::Task->value)
            ->whereNull('department_id')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->count();

        return [
            'period_days' => $periodDays,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'completed_work' => $completedWork,
            'tasks_completed' => $calendarCompleted,
            'workspace_tasks_completed' => $workspaceCompleted,
            'tasks_overdue' => $calendarOverdue,
            'workspace_tasks_overdue' => $workspaceOverdue,
            'overdue_rate' => $overdueRate,
            'tasks_total' => $calendarTotal,
            'work_total' => $calendarTotal + $workspaceTotal,
            'by_department' => $byDepartment,
            'by_employee' => $byEmployee,
            'without_department' => $unassigned,
            'project_health' => $projectHealth,
            'printing_lateness' => $printingLateness,
            'automation' => [
                'success' => $automationSuccess,
                'failed' => $automationFailed,
            ],
            'sla' => $this->sla->complianceSummary(),
        ];
    }

    /**
     * Transparent counts only — no productivity score.
     *
     * @return list<array<string, mixed>>
     */
    private function byEmployee(Carbon $from, Carbon $to): array
    {
        $rows = [];

        $calendarRows = CalendarItem::query()
            ->where('type', CalendarItemType::Task->value)
            ->whereBetween('starts_at', [$from, $to])
            ->with('assignees:id,name')
            ->limit(300)
            ->get();

        foreach ($calendarRows as $item) {
            $status = $item->status instanceof CalendarItemStatus
                ? $item->status->value
                : (string) $item->status;
            $assignees = $item->assignees;
            if ($assignees->isEmpty()) {
                continue;
            }
            foreach ($assignees as $user) {
                $id = (int) $user->id;
                if (! isset($rows[$id])) {
                    $rows[$id] = [
                        'user_id' => $id,
                        'name' => $user->name,
                        'completed' => 0,
                        'overdue' => 0,
                        'total' => 0,
                    ];
                }
                $rows[$id]['total']++;
                if ($status === CalendarItemStatus::Completed->value) {
                    $rows[$id]['completed']++;
                }
                if ($status === CalendarItemStatus::Overdue->value) {
                    $rows[$id]['overdue']++;
                }
            }
        }

        $tasks = Task::query()
            ->with('assignee:id,name')
            ->whereNotNull('assigned_to')
            ->whereBetween('created_at', [$from, $to])
            ->limit(300)
            ->get();

        foreach ($tasks as $task) {
            $id = (int) $task->assigned_to;
            if (! isset($rows[$id])) {
                $rows[$id] = [
                    'user_id' => $id,
                    'name' => $task->assignee?->name ?? ('#'.$id),
                    'completed' => 0,
                    'overdue' => 0,
                    'total' => 0,
                ];
            }
            $rows[$id]['total']++;
            $status = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
            if ($status === TaskStatus::Completed->value) {
                $rows[$id]['completed']++;
            }
            if ($task->isOverdue()) {
                $rows[$id]['overdue']++;
            }
        }

        $list = array_values($rows);
        usort($list, fn (array $a, array $b): int => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));

        return array_slice($list, 0, 40);
    }
}
