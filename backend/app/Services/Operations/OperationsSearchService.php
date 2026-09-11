<?php

namespace App\Services\Operations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CrmLeadStatus;
use App\Enums\OrderStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CalendarItem;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Operations\Work\UnifiedWorkService;

class OperationsSearchService
{
    public function __construct(
        private readonly UnifiedWorkService $unifiedWork,
    ) {}

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function search(User $actor, string $q): array
    {
        $term = trim($q);
        $like = '%'.$term.'%';
        $result = [
            'calendar' => [],
            'projects' => [],
            'crm_leads' => [],
            'orders' => [],
            'work' => [],
            'printing' => [],
            'approvals' => [],
        ];

        if ($term === '') {
            return $result;
        }

        $result['calendar'] = CalendarItem::query()
            ->where('title', 'like', $like)
            ->orderByDesc('starts_at')
            ->limit(8)
            ->get(['id', 'title', 'type', 'starts_at', 'status'])
            ->map(fn (CalendarItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type instanceof CalendarItemType ? $item->type->value : $item->type,
                'starts_at' => $item->starts_at?->toIso8601String(),
                'status' => $item->status instanceof CalendarItemStatus ? $item->status->value : $item->status,
            ])
            ->all();

        $result['projects'] = Project::query()
            ->where('title', 'like', $like)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'title', 'status', 'deadline'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status instanceof ProjectStatus ? $project->status->value : $project->status,
                'deadline' => $project->deadline?->toDateString(),
            ])
            ->all();

        if ($actor->role instanceof UserRole && $actor->role->canAccessCrm()) {
            $result['crm_leads'] = CrmLead::query()
                ->where(function ($query) use ($like): void {
                    $query->where('full_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('company_name', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'full_name', 'email', 'status'])
                ->map(fn (CrmLead $lead) => [
                    'id' => $lead->id,
                    'title' => $lead->full_name,
                    'email' => $lead->email,
                    'status' => $lead->status instanceof CrmLeadStatus ? $lead->status->value : $lead->status,
                ])
                ->all();
        }

        if ($actor->role instanceof UserRole && $actor->role->canManageOrders()) {
            $result['orders'] = Order::query()
                ->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('reference', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'title', 'reference', 'status'])
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'title' => $order->title,
                    'reference' => $order->reference,
                    'status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
                ])
                ->all();
        }

        $work = $this->unifiedWork->list($actor, [
            'scope' => ($actor->role instanceof UserRole && $actor->role->canViewTeamCalendar()) ? 'team' : 'mine',
            'per_page' => 8,
            'page' => 1,
        ]);

        $result['work'] = array_values(array_filter(
            array_map(fn (array $item): array => [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? '',
                'source_type' => $item['source_type'] ?? null,
                'status' => $item['status'] ?? null,
                'href' => $item['href'] ?? null,
            ], $work['items']),
            fn (array $item): bool => str_contains(mb_strtolower((string) $item['title']), mb_strtolower($term)),
        ));
        $result['work'] = array_slice($result['work'], 0, 8);

        // Fallback direct title match when unified list misses due to scope/page bounds.
        if (count($result['work']) < 8) {
            $extraTasks = Task::query()
                ->where('title', 'like', $like)
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'title', 'status']);
            foreach ($extraTasks as $task) {
                $result['work'][] = [
                    'id' => 'task:'.$task->id,
                    'title' => $task->title,
                    'source_type' => 'task',
                    'status' => is_object($task->status) ? $task->status->value : $task->status,
                    'href' => null,
                ];
            }
            $result['work'] = array_slice($result['work'], 0, 8);
        }

        if ($actor->role instanceof UserRole && (
            $actor->role->canReviewPrintingRequests() || $actor->role->canManageOrders()
        )) {
            $result['printing'] = PrintingRequest::query()
                ->where(function ($query) use ($like): void {
                    $query->where('product_name', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhere('original_filename', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'product_name', 'status', 'required_date'])
                ->map(fn (PrintingRequest $row) => [
                    'id' => $row->id,
                    'title' => $row->product_name,
                    'status' => is_object($row->status) ? $row->status->value : $row->status,
                    'required_date' => $row->required_date?->toDateString(),
                ])
                ->all();
        }

        if ($actor->role instanceof UserRole && $actor->role->canManageApprovals()) {
            $result['approvals'] = ApprovalRequest::query()
                ->where(function ($query) use ($actor): void {
                    $query->where('assigned_to', $actor->id)
                        ->orWhere('requested_by', $actor->id);
                })
                ->where('title', 'like', $like)
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'title', 'status', 'type'])
                ->map(fn (ApprovalRequest $row) => [
                    'id' => $row->id,
                    'title' => $row->title,
                    'type' => is_object($row->type) ? $row->type->value : $row->type,
                    'status' => $row->status instanceof ApprovalRequestStatus
                        ? $row->status->value
                        : $row->status,
                ])
                ->all();
        }

        return $result;
    }
}
