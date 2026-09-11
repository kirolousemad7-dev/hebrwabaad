<?php

namespace App\Services\Catalog;

use App\Enums\TaskStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Project;
use App\Models\Task;

/**
 * Builds per-service operational progress for custom-package projects.
 * Owner payloads may include department; customer payloads must not.
 */
class CustomPackageProjectOverviewService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function serviceLines(Project $project, bool $forCustomer = false): array
    {
        $order = Order::query()
            ->where('project_id', $project->id)
            ->where('is_custom_package', true)
            ->with(['items.service.department'])
            ->orderBy('id')
            ->first();

        if ($order === null) {
            return [];
        }

        $itemIds = $order->items->pluck('id')->all();
        $tasksByItem = Task::query()
            ->whereIn('order_item_id', $itemIds)
            ->get()
            ->keyBy('order_item_id');

        return $order->items
            ->sortBy('sort_order')
            ->values()
            ->map(function (OrderItem $item) use ($tasksByItem, $forCustomer): array {
                /** @var Task|null $task */
                $task = $tasksByItem->get($item->id);
                $service = $item->service;
                $status = $this->customerSafeStatus($task);

                $row = [
                    'order_item_id' => $item->id,
                    'service_id' => $item->service_id,
                    'service_name' => $service?->name ?? 'خدمة',
                    'quantity' => (int) $item->quantity,
                    'task_id' => $task?->id,
                    'task_status' => $task?->status instanceof TaskStatus
                        ? $task->status->value
                        : ($task?->status),
                    'status_label' => $status['label'],
                    'status_key' => $status['key'],
                    'requires_customer_approval' => (bool) ($service?->requires_customer_approval),
                    'revision_rounds' => $service?->revision_rounds,
                ];

                if (! $forCustomer) {
                    $row['department_id'] = $service?->department_id;
                    $row['department_name'] = $service?->department?->name;
                    $row['assigned_to'] = $task?->assigned_to;
                    $row['requires_review'] = (bool) ($service?->requires_review);
                }

                return $row;
            })
            ->all();
    }

    /**
     * @return array{key: string, label: string}
     */
    private function customerSafeStatus(?Task $task): array
    {
        if ($task === null) {
            return ['key' => 'not_started', 'label' => 'لم يبدأ'];
        }

        $status = $task->status instanceof TaskStatus
            ? $task->status
            : TaskStatus::tryFrom((string) $task->status);

        return match ($status) {
            TaskStatus::Completed => ['key' => 'completed', 'label' => 'مكتمل'],
            TaskStatus::InProgress, TaskStatus::Review, TaskStatus::Revision => ['key' => 'in_progress', 'label' => 'قيد التنفيذ'],
            default => ['key' => 'not_started', 'label' => 'لم يبدأ'],
        };
    }
}
