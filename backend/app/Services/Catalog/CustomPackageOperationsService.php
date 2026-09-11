<?php

namespace App\Services\Catalog;

use App\Enums\OrderStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Project;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a commercially confirmed custom-package order into one Project
 * and idempotent workspace Tasks (one per order item).
 */
class CustomPackageOperationsService
{
    public const SOURCE = 'custom_package_order_item';

    /**
     * @return array{project: Project|null, tasks_created: int, tasks_existing: int}
     */
    public function ensureOperationalWork(Order $order): array
    {
        $order->loadMissing(['items.service.department', 'customer', 'accountManager', 'project']);

        if (! $order->is_custom_package) {
            return ['project' => $order->project, 'tasks_created' => 0, 'tasks_existing' => 0];
        }

        if ($order->items->isEmpty()) {
            return ['project' => $order->project, 'tasks_created' => 0, 'tasks_existing' => 0];
        }

        return DB::transaction(function () use ($order): array {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->load(['items.service.department', 'customer', 'accountManager', 'project']);

            $project = $this->ensureProject($locked);
            $created = 0;
            $existing = 0;

            foreach ($locked->items as $item) {
                $result = $this->ensureTaskForItem($locked, $project, $item);
                if ($result === 'created') {
                    $created++;
                } else {
                    $existing++;
                }
            }

            return [
                'project' => $project->fresh(['customer', 'accountManager']),
                'tasks_created' => $created,
                'tasks_existing' => $existing,
            ];
        });
    }

    public function shouldRunForTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return $to === OrderStatus::Confirmed;
    }

    private function ensureProject(Order $order): Project
    {
        if ($order->project_id !== null && $order->project !== null) {
            return $order->project;
        }

        $manager = $order->accountManager
            ?? User::query()->active()->whereKey($order->account_manager_id)->first()
            ?? User::query()->active()->where('role', 'OWNER')->orderBy('id')->firstOrFail();

        $project = Project::query()->create([
            'title' => $order->title,
            'description' => 'مشروع تشغيلي لطلب الباقة المخصّصة '.$order->reference,
            'customer_id' => $order->customer_id,
            'account_manager_id' => $manager->id,
            'status' => ProjectStatus::InProgress->value,
            'started_at' => now()->toDateString(),
        ]);

        $order->update(['project_id' => $project->id]);

        return $project;
    }

    /**
     * @return 'created'|'existing'
     */
    private function ensureTaskForItem(Order $order, Project $project, OrderItem $item): string
    {
        $existing = Task::query()->where('order_item_id', $item->id)->first();
        if ($existing !== null) {
            return 'existing';
        }

        $service = $item->service;
        $title = $this->resolveTitle($service, $item);
        $priority = $this->resolvePriority($service);
        $description = $this->buildDescription($order, $item, $service);

        Task::query()->create([
            'title' => $title,
            'description' => $description,
            'project_id' => $project->id,
            'department_id' => $service?->department_id,
            'order_item_id' => $item->id,
            'source' => self::SOURCE,
            'assigned_to' => null,
            'created_by' => $order->account_manager_id,
            'priority' => $priority->value,
            'status' => TaskStatus::Todo->value,
            'deadline' => null,
        ]);

        return 'created';
    }

    private function resolveTitle(?Service $service, OrderItem $item): string
    {
        $qty = max(1, (int) $item->quantity);
        $name = $service?->name ?? 'خدمة';
        $template = $service?->task_title_template;

        if (is_string($template) && trim($template) !== '') {
            return str_replace(
                [':service', ':quantity', ':name'],
                [$name, (string) $qty, $name],
                $template,
            );
        }

        if ($qty > 1) {
            return "تنفيذ {$qty} × {$name}";
        }

        return "تنفيذ {$name}";
    }

    private function resolvePriority(?Service $service): TaskPriority
    {
        $raw = $service?->default_task_priority;

        if (is_string($raw) && $raw !== '') {
            return TaskPriority::tryFrom($raw) ?? TaskPriority::Medium;
        }

        return TaskPriority::Medium;
    }

    private function buildDescription(Order $order, OrderItem $item, ?Service $service): string
    {
        $lines = [
            'مصدر: باقة مخصّصة',
            'الطلب: '.$order->reference,
            'الخدمة: '.($service?->name ?? ('#'.$item->service_id)),
            'الكمية: '.(string) $item->quantity,
        ];

        if ($service?->department) {
            $lines[] = 'القسم التشغيلي: '.$service->department->name;
        }

        if ($service?->requires_review) {
            $lines[] = 'يتطلب مراجعة داخلية.';
        }

        if ($service?->requires_customer_approval) {
            $lines[] = 'يتطلب اعتماد العميل عبر البوابة.';
        }

        if ($service?->revision_rounds !== null) {
            $lines[] = 'جولات التعديل المشمولة: '.(string) $service->revision_rounds;
            $lines[] = 'أي جولة إضافية تتطلب إضافة «جولة تعديل إضافية» إن كانت مُسعّرة.';
        }

        $checklist = $service?->checklist_template;
        if (is_array($checklist) && $checklist !== []) {
            $lines[] = 'قائمة تحقق:';
            foreach ($checklist as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $lines[] = '- '.$entry;
                }
            }
        }

        if (filled($item->notes)) {
            $lines[] = 'ملاحظات العميل: '.$item->notes;
        }

        return implode("\n", $lines);
    }
}
