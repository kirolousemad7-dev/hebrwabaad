<?php

namespace App\Services\Operations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\TaskStatus;
use App\Models\ApprovalRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Services\Catalog\CustomPackageProjectOverviewService;

/**
 * Derived execution views for Project Workspace — no new domain tables.
 */
class ProjectExecutionService
{
    public function __construct(
        private readonly CustomPackageProjectOverviewService $customPackageOverview,
        private readonly ProjectBriefService $brief,
        private readonly ProjectHealthService $health,
    ) {}

    /**
     * @param  array{items: list<array<string, mixed>>, counts: array<string, int>}|null  $attention
     * @return array<string, mixed>
     */
    public function executionSummary(Project $project, ?array $attention = null, ?string $nextDeadline = null): array
    {
        $progress = $project->progress();
        $health = $this->health->evaluate($project);
        $attention ??= $this->attention($project);
        $closure = $this->closureReadiness($project, $progress, $attention);

        $currentPhase = ProjectPhase::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectPhase::STATUS_IN_PROGRESS)
            ->orderBy('sort_order')
            ->first()
            ?? ProjectPhase::query()
                ->where('project_id', $project->id)
                ->where('status', ProjectPhase::STATUS_PENDING)
                ->orderBy('sort_order')
                ->first();

        $activeMilestone = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectMilestone::STATUS_PENDING)
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return [
            'current_phase' => $currentPhase ? [
                'id' => $currentPhase->id,
                'title' => $currentPhase->title,
                'status' => $currentPhase->status,
            ] : null,
            'active_milestone' => $activeMilestone ? [
                'id' => $activeMilestone->id,
                'title' => $activeMilestone->title,
                'due_date' => $activeMilestone->due_date?->toDateString(),
                'status' => $activeMilestone->status,
            ] : null,
            'open_tasks' => max(0, $progress['total'] - $progress['completed']),
            'overdue_tasks' => $progress['overdue'],
            'in_review_tasks' => ($progress['review'] ?? 0) + ($progress['revision'] ?? 0),
            'next_deadline' => $nextDeadline,
            'completion_percent' => $progress['percent'],
            'health' => $health,
            'risks' => [
                'overdue_tasks' => $progress['overdue'],
                'attention_count' => count($attention['items']),
            ],
            'attention_count' => count($attention['items']),
            'recent_activity_count' => null,
            'closure' => $closure,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function attention(Project $project): array
    {
        $today = now()->startOfDay();
        $soon = now()->addDays(7)->endOfDay();
        $items = [];

        $tasks = Task::query()
            ->with(['assignee:id,name'])
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->orderBy('deadline')
            ->orderBy('id')
            ->get();

        foreach ($tasks as $task) {
            $status = $task->status instanceof TaskStatus
                ? $task->status
                : TaskStatus::tryFrom((string) $task->status);
            $deadline = $task->deadline;

            if ($deadline !== null && $deadline->lt($today)) {
                $items[] = $this->attentionItem(
                    'overdue',
                    'task',
                    (int) $task->id,
                    'مهمة متأخرة: '.$task->title,
                    $deadline->toDateString(),
                    $task->assignee?->name,
                );
            } elseif ($deadline !== null && $deadline->lte($soon)) {
                $items[] = $this->attentionItem(
                    'due_soon',
                    'task',
                    (int) $task->id,
                    'مهمة قريبة: '.$task->title,
                    $deadline->toDateString(),
                    $task->assignee?->name,
                );
            }

            if ($task->assigned_to === null) {
                $items[] = $this->attentionItem(
                    'unassigned',
                    'task',
                    (int) $task->id,
                    'مهمة بلا مسؤول: '.$task->title,
                    $deadline?->toDateString(),
                    null,
                );
            }

            if (in_array($status, [TaskStatus::Review, TaskStatus::Revision], true)) {
                $items[] = $this->attentionItem(
                    'waiting',
                    'task',
                    (int) $task->id,
                    ($status === TaskStatus::Review ? 'بانتظار مراجعة: ' : 'بانتظار تعديل: ').$task->title,
                    $deadline?->toDateString(),
                    $task->assignee?->name,
                );
            }
        }

        $milestones = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', ProjectMilestone::STATUS_DONE)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get();

        foreach ($milestones as $milestone) {
            if ($milestone->due_date === null) {
                continue;
            }
            if ($milestone->due_date->lt($today)) {
                $items[] = $this->attentionItem(
                    'overdue',
                    'milestone',
                    (int) $milestone->id,
                    'معلم متأخر: '.$milestone->title,
                    $milestone->due_date->toDateString(),
                    null,
                );
            } elseif ($milestone->due_date->lte($soon)) {
                $items[] = $this->attentionItem(
                    'due_soon',
                    'milestone',
                    (int) $milestone->id,
                    'معلم قريب: '.$milestone->title,
                    $milestone->due_date->toDateString(),
                    null,
                );
            }
        }

        $counts = [
            'overdue' => 0,
            'due_soon' => 0,
            'unassigned' => 0,
            'waiting' => 0,
        ];
        foreach ($items as $item) {
            $kind = (string) $item['kind'];
            if (isset($counts[$kind])) {
                $counts[$kind]++;
            }
        }

        return [
            'items' => array_slice($items, 0, 40),
            'counts' => $counts,
        ];
    }

    /**
     * Derived deliverables: catalog service lines + project references.
     *
     * @return list<array<string, mixed>>
     */
    public function deliverables(Project $project, bool $forCustomer = false): array
    {
        $rows = [];

        foreach ($this->customPackageOverview->serviceLines($project, forCustomer: $forCustomer) as $line) {
            $row = [
                'source' => 'service_line',
                'id' => 'service-'.$line['order_item_id'],
                'name' => $line['service_name'],
                'quantity' => $line['quantity'],
                'status_key' => $line['status_key'],
                'status_label' => $line['status_label'],
                'milestone_id' => null,
                'due_date' => null,
                'is_client_visible' => true,
                'requires_customer_approval' => (bool) ($line['requires_customer_approval'] ?? false),
                'url' => null,
            ];

            if (! $forCustomer) {
                $row['task_id'] = $line['task_id'] ?? null;
                $row['file_id'] = null;
            }

            $rows[] = $row;
        }

        foreach ($this->brief->listReferences($project, clientVisibleOnly: $forCustomer) as $reference) {
            if ($forCustomer && ! ($reference['is_client_visible'] ?? false)) {
                continue;
            }
            $row = [
                'source' => 'reference',
                'id' => 'reference-'.$reference['id'],
                'name' => $reference['title'],
                'quantity' => 1,
                'status_key' => ($reference['is_client_visible'] ?? false) ? 'shared' : 'internal',
                'status_label' => ($reference['is_client_visible'] ?? false) ? 'مرجع ظاهر للعميل' : 'مرجع داخلي',
                'milestone_id' => null,
                'due_date' => null,
                'is_client_visible' => (bool) ($reference['is_client_visible'] ?? false),
                'requires_customer_approval' => false,
                'url' => $reference['url'] ?? null,
            ];

            if (! $forCustomer) {
                $row['task_id'] = null;
                $row['file_id'] = $reference['file_id'] ?? null;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingApprovals(Project $project): array
    {
        return ApprovalRequest::query()
            ->with(['requester:id,name', 'assignee:id,name'])
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('status', ApprovalRequestStatus::Pending->value)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ApprovalRequest $row) => [
                'id' => $row->id,
                'type' => $row->type,
                'title' => $row->title,
                'status' => $row->status instanceof ApprovalRequestStatus
                    ? $row->status->value
                    : (string) $row->status,
                'requested_by' => $row->requester ? [
                    'id' => $row->requester->id,
                    'name' => $row->requester->name,
                ] : null,
                'assigned_to' => $row->assignee ? [
                    'id' => $row->assignee->id,
                    'name' => $row->assignee->name,
                ] : null,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  array{total: int, completed: int, overdue: int, review: int, revision: int, percent: float}  $progress
     * @param  array{items: list<array<string, mixed>>, counts: array<string, int>}  $attention
     * @return array<string, mixed>
     */
    public function closureReadiness(Project $project, array $progress, array $attention): array
    {
        $openTasks = max(0, $progress['total'] - $progress['completed']);
        $incompleteMilestones = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', ProjectMilestone::STATUS_DONE)
            ->count();

        $pendingDeliverables = collect($this->deliverables($project, forCustomer: false))
            ->filter(function (array $row): bool {
                if ($row['source'] !== 'service_line') {
                    return false;
                }

                return ! in_array($row['status_key'], ['completed', 'done'], true);
            })
            ->count();

        $issues = [];
        if ($openTasks > 0) {
            $issues[] = ['key' => 'open_tasks', 'label' => 'مهام مفتوحة', 'count' => $openTasks];
        }
        if ($progress['overdue'] > 0) {
            $issues[] = ['key' => 'overdue_tasks', 'label' => 'مهام متأخرة', 'count' => $progress['overdue']];
        }
        if ($incompleteMilestones > 0) {
            $issues[] = ['key' => 'incomplete_milestones', 'label' => 'معالم غير مكتملة', 'count' => $incompleteMilestones];
        }
        if ($pendingDeliverables > 0) {
            $issues[] = ['key' => 'pending_deliverables', 'label' => 'تسليمات خدمة قيد التنفيذ', 'count' => $pendingDeliverables];
        }
        if (($attention['counts']['waiting'] ?? 0) > 0) {
            $issues[] = ['key' => 'waiting_review', 'label' => 'بانتظار مراجعة/تعديل', 'count' => $attention['counts']['waiting']];
        }

        $state = $issues === [] ? 'ready' : 'attention';

        return [
            'state' => $state,
            'label' => $state === 'ready' ? 'جاهز للإغلاق التشغيلي' : 'يحتاج متابعة قبل الإغلاق',
            'issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attentionItem(
        string $kind,
        string $relatedType,
        int $relatedId,
        string $title,
        ?string $dueDate,
        ?string $assigneeName,
    ): array {
        return [
            'kind' => $kind,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'title' => $title,
            'due_date' => $dueDate,
            'assignee_name' => $assigneeName,
        ];
    }
}
