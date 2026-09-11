<?php

namespace App\Services\Operations;

use App\Enums\CalendarItemStatus;
use App\Enums\TaskStatus;
use App\Models\ApprovalRequest;
use App\Models\CalendarItem;
use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\WorkflowAutomationRun;
use Carbon\Carbon;

class ProjectTimelineService
{
    private const CAP = 100;

    /**
     * @return list<array<string, mixed>>
     */
    public function forProject(Project $project, string $filter = 'all', int $limit = self::CAP): array
    {
        $filter = in_array($filter, ['all', 'tasks', 'files', 'team', 'approvals', 'automation'], true)
            ? $filter
            : 'all';

        $events = [];

        if (in_array($filter, ['all'], true)) {
            $events[] = $this->event(
                'project_created',
                'project',
                (int) $project->id,
                'تم إنشاء المشروع',
                $project->created_at,
                ['title' => $project->title, 'status' => $this->enumValue($project->status)],
            );

            if ($project->updated_at && $project->created_at
                && $project->updated_at->gt($project->created_at->copy()->addMinute())
            ) {
                $events[] = $this->event(
                    'project_updated',
                    'project',
                    (int) $project->id,
                    'تحديث المشروع (الحالة / الموعد)',
                    $project->updated_at,
                    [
                        'status' => $this->enumValue($project->status),
                        'deadline' => $project->deadline?->toDateString(),
                    ],
                );
            }
        }

        if (in_array($filter, ['all', 'tasks'], true)) {
            $events = array_merge($events, $this->workspaceTaskEvents($project));
            $events = array_merge($events, $this->calendarProjectEvents($project));
        }

        if (in_array($filter, ['all', 'files'], true)) {
            $events = array_merge($events, $this->fileEvents($project));
        }

        if (in_array($filter, ['all', 'team'], true)) {
            $events = array_merge($events, $this->memberEvents($project));
        }

        if (in_array($filter, ['all', 'approvals'], true)) {
            $events = array_merge($events, $this->approvalEvents($project));
        }

        if (in_array($filter, ['all', 'automation'], true)) {
            $events = array_merge($events, $this->automationEvents($project));
        }

        usort($events, function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_slice(array_values($events), 0, min($limit, self::CAP));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workspaceTaskEvents(Project $project): array
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        $events = [];
        $completedGroups = [];

        foreach ($tasks as $task) {
            $events[] = $this->event(
                'workspace_task_created',
                'workspace_task',
                (int) $task->id,
                'مهمة مساحة عمل: '.$task->title,
                $task->created_at,
                ['title' => $task->title, 'actor_id' => $task->created_by],
            );

            $status = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
            if ($status === TaskStatus::Completed->value) {
                $day = ($task->updated_at ?? $task->created_at)?->toDateString() ?? 'unknown';
                $actor = (int) ($task->assigned_to ?? $task->created_by ?? 0);
                $key = $day.'|'.$actor;
                $completedGroups[$key]['day'] = $day;
                $completedGroups[$key]['actor_id'] = $actor;
                $completedGroups[$key]['at'] = $task->updated_at ?? $task->created_at;
                $completedGroups[$key]['titles'][] = $task->title;
                $completedGroups[$key]['ids'][] = (int) $task->id;
            }
        }

        foreach ($completedGroups as $group) {
            $count = count($group['titles']);
            $title = $count === 1
                ? 'أكمل مهمة: '.$group['titles'][0]
                : 'أكمل '.$count.' مهام';

            $events[] = $this->event(
                'workspace_tasks_completed',
                'workspace_task',
                $group['ids'][0] ?? null,
                $title,
                $group['at'],
                [
                    'actor_id' => $group['actor_id'],
                    'count' => $count,
                    'details' => $group['titles'],
                    'task_ids' => $group['ids'],
                ],
            );
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calendarProjectEvents(Project $project): array
    {
        $items = CalendarItem::query()
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $events = [];
        foreach ($items as $item) {
            $events[] = $this->event(
                'calendar_item_created',
                'calendar_item',
                (int) $item->id,
                'عنصر تقويم: '.$item->title,
                $item->created_at,
                [
                    'title' => $item->title,
                    'type' => $this->enumValue($item->type),
                    'actor_id' => $item->created_by,
                ],
            );

            $status = $item->status instanceof CalendarItemStatus
                ? $item->status->value
                : (string) $item->status;

            if ($status === CalendarItemStatus::Completed->value) {
                $events[] = $this->event(
                    'calendar_item_completed',
                    'calendar_item',
                    (int) $item->id,
                    'اكتمل عنصر التقويم: '.$item->title,
                    $item->completed_at ?? $item->updated_at,
                    ['title' => $item->title],
                );
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fileEvents(Project $project): array
    {
        return ManagedFile::query()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (ManagedFile $file) => $this->event(
                'file_uploaded',
                'file',
                (int) $file->id,
                'ملف: '.$file->original_name,
                $file->created_at,
                ['name' => $file->original_name, 'actor_id' => $file->uploaded_by],
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memberEvents(Project $project): array
    {
        return ProjectMember::query()
            ->with('user:id,name')
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (ProjectMember $member) => $this->event(
                'project_member_added',
                'project_member',
                (int) $member->id,
                'إضافة عضو: '.($member->user?->name ?? '#'.$member->user_id),
                $member->created_at,
                [
                    'user_id' => $member->user_id,
                    'role' => $member->role,
                ],
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function approvalEvents(Project $project): array
    {
        return ApprovalRequest::query()
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (ApprovalRequest $row) => $this->event(
                'approval_request',
                'approval_request',
                (int) $row->id,
                $row->title,
                $row->created_at,
                [
                    'status' => $this->enumValue($row->status),
                    'type' => $this->enumValue($row->type),
                ],
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function automationEvents(Project $project): array
    {
        $calendarIds = CalendarItem::query()
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->limit(100)
            ->pluck('id')
            ->all();

        $taskIds = Task::query()
            ->where('project_id', $project->id)
            ->limit(100)
            ->pluck('id')
            ->all();

        $query = WorkflowAutomationRun::query()
            ->where(function ($builder) use ($project, $calendarIds, $taskIds): void {
                $builder->where(function ($q) use ($project): void {
                    $q->where('source_type', 'project')->where('source_id', $project->id);
                });

                if ($calendarIds !== []) {
                    $builder->orWhere(function ($q) use ($calendarIds): void {
                        $q->where('source_type', 'calendar_item')->whereIn('source_id', $calendarIds);
                    });
                }

                if ($taskIds !== []) {
                    $builder->orWhere(function ($q) use ($taskIds): void {
                        $q->whereIn('source_type', ['workspace_task', 'task'])
                            ->whereIn('source_id', $taskIds);
                    });
                }
            })
            ->orderByDesc('executed_at')
            ->limit(40)
            ->get();

        return $query->map(fn (WorkflowAutomationRun $run) => $this->event(
            'automation_run',
            'workflow_automation_run',
            (int) $run->id,
            'تشغيل أتمتة: '.$run->trigger,
            $run->executed_at ?? $run->created_at,
            [
                'status' => $this->enumValue($run->status),
                'source_type' => $run->source_type,
                'source_id' => $run->source_id,
            ],
        ))->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function event(
        string $type,
        string $relatedType,
        ?int $relatedId,
        string $title,
        mixed $at,
        array $meta = [],
    ): array {
        $occurred = $at instanceof Carbon
            ? $at
            : ($at ? Carbon::parse($at) : now());

        return [
            'type' => $type,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'title' => $title,
            'occurred_at' => $occurred->toIso8601String(),
            'meta' => $meta,
        ];
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_object($value) && property_exists($value, 'value')
            ? (string) $value->value
            : (string) $value;
    }
}
