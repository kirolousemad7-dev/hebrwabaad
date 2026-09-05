<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\PrintingPricingType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Consultation;
use App\Models\ConsultationEvent;
use App\Models\Conversation;
use App\Models\ManagedFile;
use App\Models\Order;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerDashboardService
{
    private const ACTIVITY_LIMIT = 12;

    public function __construct(
        private readonly OrderService $orders,
        private readonly ConversationService $conversations,
        private readonly FileService $files,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $customer): array
    {
        $projects = $this->projectsFor($customer);
        $orders = $this->orders->forCustomer($customer);
        $conversations = $this->conversations->forCustomer($customer);
        $files = $this->files->recentFor($customer);
        $fileCount = $this->files->countFor($customer);
        $notificationUnread = $this->notifications->unreadCount($customer);
        $recentNotifications = $this->notifications->recentFor($customer);
        $requests = $customer->printingRequests()->latest()->limit(8)->get();
        $allRequestCount = $customer->printingRequests()->count();
        $needsAttention = $customer->printingRequests()
            ->where('pricing_type', PrintingPricingType::QuoteReady)
            ->count();

        $activeStatuses = [
            ProjectStatus::Planning->value,
            ProjectStatus::InProgress->value,
            ProjectStatus::Review->value,
        ];

        $activeConversations = $conversations->filter(function (Conversation $conversation) {
            $status = $conversation->statusEnum();

            return $status === ConversationStatus::Open || $status === ConversationStatus::InProgress;
        })->count();

        $activeProjects = $projects->filter(
            fn (Project $project) => in_array($project->status?->value ?? $project->status, $activeStatuses, true)
        )->count();

        $inProgress = $projects->filter(function (Project $project) {
            $status = $project->status?->value ?? $project->status;

            return $status === ProjectStatus::InProgress->value
                || ($project->progress()['in_progress'] ?? 0) > 0;
        })->count();

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'summary' => [
                'projects' => $this->availableMetric($projects->count(), [
                    'active' => $activeProjects,
                    'in_progress' => $inProgress,
                ]),
                'requests' => $this->availableMetric($allRequestCount, [
                    'needs_attention' => $needsAttention,
                ]),
                'in_progress' => $this->availableMetric($inProgress),
                'needs_attention' => $this->availableMetric($needsAttention),
                'orders' => $this->availableMetric($orders->count()),
                'messages' => $this->availableMetric($activeConversations),
                'files' => $this->availableMetric($fileCount),
                'notifications' => $this->availableMetric($notificationUnread),
            ],
            'projects' => $projects->take(6)->values(),
            'requests' => $requests,
            'activity' => $this->activity($customer, $projects, $requests, $orders, $conversations, $files),
            'orders' => $orders->take(6)->values(),
            'messages' => $conversations->take(6)->values(),
            'files' => $files,
            'notification_unread' => $notificationUnread,
            'notifications' => $recentNotifications,
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    public function projectsFor(User $customer): Collection
    {
        return Project::query()
            ->where('customer_id', $customer->id)
            ->with('accountManager')
            ->withCount($this->progressCounts())
            ->latest()
            ->get();
    }

    public function load(Project $project): Project
    {
        return $project->load('accountManager')->loadCount($this->progressCounts());
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function progressCounts(): array
    {
        return [
            'tasks',
            'tasks as todo_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Todo->value),
            'tasks as in_progress_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::InProgress->value),
            'tasks as review_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Review->value),
            'tasks as revision_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Revision->value),
            'tasks as completed_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Completed->value),
            'tasks as overdue_tasks_count' => fn (Builder $query) => $query
                ->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString()),
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, PrintingRequest>  $requests
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, Conversation>  $conversations
     * @param  Collection<int, ManagedFile>  $files
     * @return list<array<string, mixed>>
     */
    private function activity(User $customer, Collection $projects, Collection $requests, Collection $orders, Collection $conversations, Collection $files): array
    {
        $items = collect();

        foreach ($projects as $project) {
            $items->push($this->activityItem(
                'project_created',
                $project->title,
                $project->created_at?->toIso8601String(),
                (string) ($project->status?->value ?? $project->status),
                '/dashboard/projects/'.$project->id,
                $project->id,
            ));

            if ($project->updated_at && $project->created_at && $project->updated_at->gt($project->created_at)) {
                $items->push($this->activityItem(
                    'project_updated',
                    $project->title,
                    $project->updated_at->toIso8601String(),
                    (string) ($project->status?->value ?? $project->status),
                    '/dashboard/projects/'.$project->id,
                    $project->id,
                ));
            }
        }

        foreach ($orders as $order) {
            $items->push($this->activityItem(
                'order_created',
                $order->title,
                $order->created_at?->toIso8601String(),
                (string) ($order->status?->value ?? $order->status),
                '/dashboard/orders/'.$order->id,
                $order->id,
            ));

            foreach ($order->statusHistory as $history) {
                if ($history->from_status === null) {
                    continue;
                }

                $items->push($this->activityItem(
                    'order_status_changed',
                    $order->reference.' · '.$order->title,
                    $history->created_at?->toIso8601String(),
                    (string) ($history->to_status?->value ?? $history->to_status),
                    '/dashboard/orders/'.$order->id,
                    (int) $history->id,
                ));
            }
        }

        foreach ($conversations as $conversation) {
            $items->push($this->activityItem(
                'conversation_created',
                $conversation->subject,
                $conversation->created_at?->toIso8601String(),
                $conversation->statusEnum()->value,
                '/dashboard/messages/'.$conversation->id,
                $conversation->id,
            ));
        }

        foreach ($files as $file) {
            $items->push($this->activityItem(
                'file_uploaded',
                $file->original_name,
                $file->created_at?->toIso8601String(),
                $file->extension,
                '/dashboard/files',
                $file->id,
            ));
        }

        foreach ($requests as $request) {
            $items->push($this->activityItem(
                'printing_request_submitted',
                $request->product_name,
                $request->created_at?->toIso8601String(),
                (string) ($request->status?->value ?? $request->status),
                '/customer/printing-requests/'.$request->id,
                $request->id,
            ));

            if ($request->quoted_at && $request->pricing_type === PrintingPricingType::QuoteReady) {
                $items->push($this->activityItem(
                    'printing_quote_ready',
                    $request->product_name,
                    $request->quoted_at->toIso8601String(),
                    PrintingPricingType::QuoteReady->value,
                    '/customer/printing-requests/'.$request->id,
                    $request->id,
                ));
            }
        }

        $consultationIds = Consultation::query()
            ->where('user_id', $customer->id)
            ->pluck('id');

        if ($consultationIds->isNotEmpty()) {
            $events = ConsultationEvent::query()
                ->whereIn('consultation_id', $consultationIds)
                ->whereIn('name', ['ai_consultation_started', 'consultation_completed', 'quote_requested'])
                ->latest()
                ->limit(self::ACTIVITY_LIMIT)
                ->get();

            foreach ($events as $event) {
                $items->push($this->activityItem(
                    $event->name,
                    match ($event->name) {
                        'consultation_completed' => 'اكتملت استشارة حبر الذكية',
                        'quote_requested' => 'طلب تواصل من الاستشارة الذكية',
                        default => 'بدأت استشارة حبر الذكية',
                    },
                    $event->created_at?->toIso8601String(),
                    $event->name,
                    '/consultant',
                    $event->consultation_id,
                ));
            }
        }

        return $items
            ->sortByDesc(fn (array $item) => $item['occurred_at'] ?? '')
            ->take(self::ACTIVITY_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function activityItem(
        string $type,
        string $title,
        ?string $occurredAt,
        string $status,
        string $href,
        int $entityId,
    ): array {
        return [
            'id' => $type.'-'.$entityId.'-'.($occurredAt ?? 'none'),
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'occurred_at' => $occurredAt,
            'href' => $href,
        ];
    }

    /**
     * @param  array<string, int>  $secondary
     * @return array<string, mixed>
     */
    private function availableMetric(int $value, array $secondary = []): array
    {
        return [
            'available' => true,
            'value' => $value,
            'secondary' => $secondary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableDomain(string $reason): array
    {
        return [
            'available' => false,
            'status' => 'unavailable',
            'reason' => $reason,
            'message' => match ($reason) {
                'orders_not_implemented' => 'الطلبات سيتم تفعيلها قريبًا',
                'messages_not_implemented' => 'التواصل المباشر مع فريق HEBR سيتم تفعيله قريبًا.',
                'files_not_implemented' => 'سيتم تفعيل مشاركة الملفات وإدارتها قريبًا.',
                'notifications_not_implemented' => 'لا توجد إشعارات متاحة حاليًا.',
                default => 'سيتم تفعيله قريبًا',
            },
        ];
    }
}
