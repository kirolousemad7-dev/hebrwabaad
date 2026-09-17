<?php

namespace App\Services\Calendar;

use App\Enums\CalendarItemPriority;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\CalendarSource;
use App\Enums\CalendarVisibility;
use App\Enums\GoogleCalendarSyncStatus;
use App\Enums\UserRole;
use App\Models\CrmQuotation;
use App\Models\Order;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Calendar\CalendarRelatedEntityUrlResolver;
use Carbon\Carbon;

/**
 * Read-only derived calendar events for linked domain dates (UTC storage / display).
 */
class CalendarDerivedEventsService
{
    public function __construct(
        private readonly CalendarRelatedEntityUrlResolver $relatedUrls,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function eventsFor(User $actor, Carbon $from, Carbon $to): array
    {
        $events = [];

        $events = array_merge($events, $this->projectEvents($actor, $from, $to));
        $events = array_merge($events, $this->printingEvents($actor, $from, $to));
        $events = array_merge($events, $this->orderDeliveryEvents($actor, $from, $to));
        $events = array_merge($events, $this->googleSyncedTaskEvents($actor, $from, $to));

        if ($actor->role instanceof UserRole && $actor->role->canAccessCrm()) {
            $events = array_merge($events, $this->quotationEvents($actor, $from, $to));
        }

        // Payments skipped: no due_date field (do not invent dates from created_at).
        // Suppliers skipped: no real schedule date field beyond soft timestamps.

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectEvents(User $actor, Carbon $from, Carbon $to): array
    {
        $query = Project::query()
            ->where(function ($builder) use ($from, $to): void {
                $builder->whereBetween('started_at', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('deadline', [$from->toDateString(), $to->toDateString()]);
            });

        if (! ($actor->role instanceof UserRole && ($actor->role === UserRole::Owner || $actor->role->canOverseeProjects()))) {
            if ($actor->role === UserRole::AccountManager) {
                $query->where('account_manager_id', $actor->id);
            } else {
                $query->whereHas('tasks', fn ($q) => $q->where('assigned_to', $actor->id));
            }
        }

        $events = [];
        foreach ($query->get(['id', 'title', 'started_at', 'deadline', 'account_manager_id']) as $project) {
            $href = $this->relatedUrls->resolve($actor, 'project', (int) $project->id);

            if ($project->started_at !== null) {
                $start = Carbon::parse($project->started_at)->startOfDay();
                if ($start->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())) {
                    $events[] = $this->derived(
                        id: 'derived-project-started-'.$project->id,
                        title: 'بدء المشروع: '.($project->title ?? ('#'.$project->id)),
                        type: CalendarItemType::Other->value,
                        source: CalendarSource::Project->value,
                        startsAt: $start,
                        allDay: true,
                        relatedType: 'project',
                        relatedId: (int) $project->id,
                        href: $href,
                    );
                }
            }

            if ($project->deadline !== null) {
                $deadline = Carbon::parse($project->deadline)->endOfDay();
                if ($deadline->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())) {
                    $events[] = $this->derived(
                        id: 'derived-project-deadline-'.$project->id,
                        title: 'موعد تسليم المشروع: '.($project->title ?? ('#'.$project->id)),
                        type: CalendarItemType::Deadline->value,
                        source: CalendarSource::Project->value,
                        startsAt: $deadline,
                        allDay: true,
                        relatedType: 'project',
                        relatedId: (int) $project->id,
                        href: $href,
                    );
                }
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function printingEvents(User $actor, Carbon $from, Carbon $to): array
    {
        $query = PrintingRequest::query()
            ->whereNotNull('required_date')
            ->whereBetween('required_date', [$from->toDateString(), $to->toDateString()]);

        if (! ($actor->role instanceof UserRole && in_array($actor->role, [UserRole::Owner, UserRole::AdminManager, UserRole::PrintingSpecialist], true))) {
            $query->where('user_id', $actor->id);
        }

        $events = [];
        foreach ($query->get(['id', 'product_name', 'required_date']) as $request) {
            $start = Carbon::parse($request->required_date)->startOfDay();
            $events[] = $this->derived(
                id: 'derived-printing-required-'.$request->id,
                title: 'موعد طباعة: '.($request->product_name ?? ('#'.$request->id)),
                type: CalendarItemType::Deadline->value,
                source: CalendarSource::Printing->value,
                startsAt: $start,
                allDay: true,
                relatedType: 'printing_request',
                relatedId: (int) $request->id,
                href: $this->relatedUrls->resolve($actor, 'printing_request', (int) $request->id),
            );
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderDeliveryEvents(User $actor, Carbon $from, Carbon $to): array
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canManageOrders())) {
            return [];
        }

        $query = Order::query()
            ->where(function ($builder) use ($from, $to): void {
                $builder->where(function ($inner) use ($from, $to): void {
                    $inner->whereNotNull('delivered_at')
                        ->whereBetween('delivered_at', [$from, $to]);
                })->orWhere(function ($inner) use ($from, $to): void {
                    $inner->whereNotNull('completed_at')
                        ->whereBetween('completed_at', [$from, $to]);
                });
            });

        if ($actor->role === UserRole::AccountManager) {
            $query->where('account_manager_id', $actor->id);
        }

        $events = [];
        foreach ($query->get(['id', 'reference', 'delivered_at', 'completed_at']) as $order) {
            $at = $order->delivered_at ?? $order->completed_at;
            if ($at === null) {
                continue;
            }

            $ref = $order->reference ?: (string) $order->id;

            $events[] = $this->derived(
                id: 'derived-order-delivery-'.$order->id,
                title: 'موعد تسليم الطلب #'.$ref,
                type: CalendarItemType::Deadline->value,
                source: CalendarSource::Order->value,
                startsAt: Carbon::parse($at),
                allDay: false,
                relatedType: 'order',
                relatedId: (int) $order->id,
                href: $this->relatedUrls->resolve($actor, 'order', (int) $order->id),
            );
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quotationEvents(User $actor, Carbon $from, Carbon $to): array
    {
        $query = CrmQuotation::query()
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()]);

        $events = [];
        foreach ($query->get(['id', 'number', 'valid_until']) as $quotation) {
            $start = Carbon::parse($quotation->valid_until)->endOfDay();
            $label = $quotation->number ? (string) $quotation->number : ('#'.$quotation->id);
            $events[] = $this->derived(
                id: 'derived-crm-quotation-'.$quotation->id,
                title: 'صلاحية عرض السعر '.$label,
                type: CalendarItemType::Deadline->value,
                source: CalendarSource::Crm->value,
                startsAt: $start,
                allDay: true,
                relatedType: 'crm_quotation',
                relatedId: (int) $quotation->id,
                href: $this->relatedUrls->resolve($actor, 'crm_quotation', (int) $quotation->id),
            );
        }

        return $events;
    }

    /**
     * Local tasks synced to Google Calendar — shown alongside internal calendar items.
     *
     * @return list<array<string, mixed>>
     */
    private function googleSyncedTaskEvents(User $actor, Carbon $from, Carbon $to): array
    {
        $query = Task::query()
            ->where('google_sync_enabled', true)
            ->where('google_sync_status', GoogleCalendarSyncStatus::Synced->value)
            ->where(function ($builder) use ($from, $to): void {
                $builder->whereBetween('start_at', [$from, $to])
                    ->orWhereBetween('due_at', [$from, $to])
                    ->orWhereBetween('deadline', [$from->toDateString(), $to->toDateString()]);
            });

        if (! ($actor->role instanceof UserRole && ($actor->role === UserRole::Owner || $actor->role->canOverseeProjects()))) {
            $query->where(function ($inner) use ($actor): void {
                $inner->where('assigned_to', $actor->id)
                    ->orWhere('created_by', $actor->id)
                    ->orWhereHas('project', fn ($p) => $p->where('account_manager_id', $actor->id));
            });
        }

        $events = [];
        foreach ($query->get(['id', 'title', 'start_at', 'due_at', 'deadline', 'google_html_link', 'location']) as $task) {
            $start = $task->start_at
                ? Carbon::parse($task->start_at)
                : ($task->due_at
                    ? Carbon::parse($task->due_at)->subHour()
                    : ($task->deadline ? Carbon::parse($task->deadline)->startOfDay() : null));

            if ($start === null || ! $start->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())) {
                continue;
            }

            $href = $this->relatedUrls->resolve($actor, 'task', (int) $task->id) ?? '/workspace/tasks/'.$task->id;
            $event = $this->derived(
                id: 'google-task-'.$task->id,
                title: 'Google: '.$task->title,
                type: CalendarItemType::Task->value,
                source: CalendarSource::Google->value,
                startsAt: $start,
                allDay: $task->start_at === null && $task->due_at === null,
                relatedType: 'task',
                relatedId: (int) $task->id,
                href: $href,
            );
            $event['meeting_url'] = $task->google_html_link;
            $event['location'] = $task->location;
            $events[] = $event;
        }

        return $events;
    }

    /**
     * @return array<string, mixed>
     */
    private function derived(
        string $id,
        string $title,
        string $type,
        string $source,
        Carbon $startsAt,
        bool $allDay,
        string $relatedType,
        int $relatedId,
        ?string $href = null,
    ): array {
        return [
            'id' => $id,
            'is_linked' => true,
            'is_occurrence' => false,
            'occurrence_at' => null,
            'title' => $title,
            'description' => null,
            'type' => $type,
            'status' => CalendarItemStatus::Scheduled->value,
            'priority' => CalendarItemPriority::Medium->value,
            'visibility' => CalendarVisibility::Team->value,
            'source' => $source,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => null,
            'all_day' => $allDay,
            'created_by' => null,
            'creator' => null,
            'assignees' => [],
            'reminders' => [],
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'related_label' => $title,
            'related_href' => $href,
            'completed_at' => null,
            'can_edit' => false,
            'location' => null,
            'meeting_url' => null,
            'checklist' => [],
            'recurrence_rule' => null,
            'recurrence_until' => null,
            'recurrence_count' => null,
            'comments_count' => 0,
            'attachments_count' => 0,
        ];
    }
}
