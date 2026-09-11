<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\PrintingRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\CalendarItem;
use App\Models\Department;
use App\Models\OperationalEscalationState;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Carbon\Carbon;

class EscalationService
{
    public function __construct(
        private readonly OperationalNotifier $notifier,
        private readonly ProjectHealthService $health,
    ) {}

    /**
     * @return array{calendar: int, workspace: int, projects: int, printing: int}
     */
    public function processOverdue(): array
    {
        return [
            'calendar' => $this->processCalendarTasks(),
            'workspace' => $this->processWorkspaceTasks(),
            'projects' => $this->processProjects(),
            'printing' => $this->processPrinting(),
        ];
    }

    private function processCalendarTasks(): int
    {
        $count = 0;
        $items = CalendarItem::query()
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
            ->with('assignees')
            ->limit(200)
            ->get();

        foreach ($items as $item) {
            $overdueStart = $item->starts_at instanceof Carbon
                ? $item->starts_at->copy()
                : Carbon::parse($item->starts_at);
            $daysLate = max(0, (int) $overdueStart->startOfDay()->diffInDays(now()->startOfDay(), false));
            $targetLevel = min(3, $daysLate);

            if ($this->advanceState(
                'calendar_item',
                (int) $item->id,
                'overdue_task',
                $targetLevel,
                function (int $level) use ($item): void {
                    $this->notifyCalendarLevel($item, $level);
                },
                ['days_late' => $daysLate, 'title' => $item->title],
            )) {
                $count++;
            }
        }

        return $count;
    }

    private function processWorkspaceTasks(): int
    {
        $count = 0;
        $tasks = Task::query()
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', now()->toDateString())
            ->with('assignee')
            ->limit(200)
            ->get();

        foreach ($tasks as $task) {
            $deadline = $task->deadline instanceof Carbon
                ? $task->deadline->copy()
                : Carbon::parse($task->deadline);
            $daysLate = max(0, (int) $deadline->startOfDay()->diffInDays(now()->startOfDay(), false));
            $targetLevel = min(3, $daysLate);

            if ($this->advanceState(
                'workspace_task',
                (int) $task->id,
                'overdue_task',
                $targetLevel,
                function (int $level) use ($task): void {
                    $this->notifyWorkspaceLevel($task, $level);
                },
                ['days_late' => $daysLate, 'title' => $task->title],
            )) {
                $count++;
            }
        }

        return $count;
    }

    private function processProjects(): int
    {
        $count = 0;
        $projects = Project::query()
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->limit(100)
            ->get();

        foreach ($projects as $project) {
            $health = $this->health->evaluate($project);
            $daysLate = $health['days_to_deadline'] !== null && $health['days_to_deadline'] < 0
                ? abs((int) $health['days_to_deadline'])
                : 0;

            if ($health['status'] !== 'overdue' && $daysLate <= 2) {
                continue;
            }

            $targetLevel = $daysLate > 2 ? 3 : 2;

            if ($this->advanceState(
                'project',
                (int) $project->id,
                'project_health',
                $targetLevel,
                function (int $level) use ($project): void {
                    $manager = User::query()->find($project->account_manager_id);
                    if ($manager === null || ! $manager->is_active) {
                        return;
                    }

                    $category = $level >= 2 ? 'critical_escalation' : 'operational';
                    $this->notifier->notifyCalendar($manager, [
                        'type' => 'project_escalation',
                        'title' => 'تصعيد مشروع: '.$project->title,
                        'message' => 'المشروع يحتاج متابعة عاجلة',
                        'href' => '/operations/projects/'.$project->id.'/workspace',
                        'level' => $level,
                    ], $category);
                },
                [
                    'days_late' => $daysLate,
                    'health' => $health['status'],
                    'command_center_severity' => 'high',
                ],
            )) {
                $count++;
            }
        }

        return $count;
    }

    private function processPrinting(): int
    {
        $count = 0;
        $today = now()->toDateString();
        $rows = PrintingRequest::query()
            ->whereIn('status', PrintingRequestStatus::openValues())
            ->whereDate('required_date', '<', $today)
            ->limit(100)
            ->get();

        foreach ($rows as $row) {
            $required = $row->required_date instanceof Carbon
                ? $row->required_date->copy()
                : Carbon::parse($row->required_date);
            $daysLate = max(0, (int) $required->startOfDay()->diffInDays(now()->startOfDay(), false));
            $targetLevel = min(3, $daysLate);

            if ($this->advanceState(
                'printing_request',
                (int) $row->id,
                'printing_overdue',
                $targetLevel,
                function (int $level) use ($row, $daysLate): void {
                    $recipientId = $row->assigned_to ?? $row->quoted_by;
                    if ($recipientId === null) {
                        return;
                    }

                    $user = User::query()->find($recipientId);
                    if ($user === null || ! $user->is_active) {
                        return;
                    }

                    $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();
                    if ($prefs !== null && $prefs->printing_alerts === false) {
                        return;
                    }

                    $category = $level >= 2 ? 'critical_escalation' : 'operational';
                    $this->notifier->notifyCalendar($user, [
                        'type' => 'printing_escalation',
                        'title' => 'تصعيد طباعة: '.$row->product_name,
                        'message' => 'طلب طباعة متأخر ('.$daysLate.' يوم)',
                        'href' => '/operations/printing?item='.$row->id,
                        'level' => $level,
                    ], $category);
                },
                [
                    'days_late' => $daysLate,
                    'command_center_severity' => $daysLate >= 2 ? 'critical' : ($daysLate === 1 ? 'high' : 'medium'),
                ],
            )) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  callable(int): void  $onLevel
     * @param  array<string, mixed>  $meta
     */
    private function advanceState(
        string $sourceType,
        int $sourceId,
        string $ruleKey,
        int $targetLevel,
        callable $onLevel,
        array $meta = [],
    ): bool {
        $state = OperationalEscalationState::query()->firstOrNew([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'rule_key' => $ruleKey,
        ]);

        // Spam / retry gate — do not re-notify while next_eligible_at is in the future.
        if ($state->exists && $state->next_eligible_at !== null && $state->next_eligible_at->isFuture()) {
            return false;
        }

        $currentLevel = $state->exists ? (int) $state->level : -1;

        // Already at or beyond the level warranted by days late — still refresh eligibility window.
        if ($currentLevel >= $targetLevel) {
            if ($state->last_notified_at !== null && $state->last_notified_at->isSameHour(now())) {
                return false;
            }

            $state->fill([
                'next_eligible_at' => now()->addHour(),
                'meta' => array_merge($state->meta ?? [], $meta, ['level' => $currentLevel]),
            ])->save();

            return false;
        }

        $nextLevel = min($targetLevel, $currentLevel + 1);
        if ($nextLevel < 0) {
            $nextLevel = 0;
        }

        $onLevel($nextLevel);

        $state->fill([
            'level' => $nextLevel,
            'last_notified_at' => now(),
            'next_eligible_at' => now()->addHour(),
            'meta' => array_merge($state->meta ?? [], $meta, [
                'level' => $nextLevel,
            ]),
        ])->save();

        return true;
    }

    private function notifyCalendarLevel(CalendarItem $item, int $level): void
    {
        if ($level <= 1) {
            foreach ($item->assignees as $assignee) {
                if (! $assignee->is_active) {
                    continue;
                }
                $this->notifier->notifyCalendar($assignee, [
                    'type' => 'escalation_calendar',
                    'title' => 'تصعيد مهمة تقويم',
                    'message' => $item->title,
                    'href' => '/workspace/calendar?item='.$item->id,
                    'level' => $level,
                ], $level >= 2 ? 'critical_escalation' : 'operational');
            }

            return;
        }

        if ($level === 2) {
            $manager = $this->resolveDepartmentManager($item);
            if ($manager !== null) {
                $this->notifier->notifyCalendar($manager, [
                    'type' => 'escalation_calendar_manager',
                    'title' => 'تصعيد لإدارة القسم',
                    'message' => $item->title,
                    'href' => '/workspace/calendar?item='.$item->id,
                    'level' => $level,
                ], 'critical_escalation');
            }

            return;
        }

        // Level 3: meta only (stored by advanceState) for command center.
    }

    private function notifyWorkspaceLevel(Task $task, int $level): void
    {
        if ($level <= 1) {
            $assignee = $task->assignee;
            if ($assignee !== null && $assignee->is_active) {
                $this->notifier->notifyCalendar($assignee, [
                    'type' => 'escalation_workspace_task',
                    'title' => 'تصعيد مهمة مساحة عمل',
                    'message' => $task->title,
                    'href' => '/workspace/tasks/'.$task->id,
                    'level' => $level,
                ], $level >= 2 ? 'critical_escalation' : 'operational');
            }

            return;
        }

        if ($level === 2) {
            $manager = null;
            if ($assignee = $task->assignee) {
                $manager = $this->resolveUserDepartmentManager($assignee);
            }
            if ($manager !== null) {
                $this->notifier->notifyCalendar($manager, [
                    'type' => 'escalation_workspace_manager',
                    'title' => 'تصعيد لإدارة القسم',
                    'message' => $task->title,
                    'href' => '/workspace/tasks/'.$task->id,
                    'level' => $level,
                ], 'critical_escalation');
            }
        }
    }

    private function resolveDepartmentManager(CalendarItem $item): ?User
    {
        if ($item->department_id !== null) {
            $deptManagerId = $item->department?->manager_id
                ?? Department::query()->whereKey($item->department_id)->value('manager_id');
            if ($deptManagerId) {
                $user = User::query()->find($deptManagerId);
                if ($user !== null && $user->is_active) {
                    return $user;
                }
            }
        }

        $assignee = $item->assignees->first();
        if ($assignee instanceof User) {
            return $this->resolveUserDepartmentManager($assignee);
        }

        return null;
    }

    private function resolveUserDepartmentManager(User $user): ?User
    {
        if ($user->department_id === null) {
            return null;
        }

        $managerId = Department::query()->whereKey($user->department_id)->value('manager_id');
        if (! $managerId || (int) $managerId === (int) $user->id) {
            return null;
        }

        $manager = User::query()->find($managerId);

        return $manager !== null && $manager->is_active ? $manager : null;
    }
}
