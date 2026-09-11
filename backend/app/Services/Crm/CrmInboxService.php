<?php

namespace App\Services\Crm;

use App\Enums\CrmFollowUpStatus;
use App\Enums\CrmLeadStatus;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\PlatformNotifier;

class CrmInboxService
{
    public function __construct(
        private readonly CrmSettingsService $settings,
        private readonly CrmFollowUpService $followUps,
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inbox(User $actor): array
    {
        $this->followUps->markOverdue();
        $this->markStaleLeads();

        $notifications = $actor->notifications()
            ->where(function ($query): void {
                $query->where('data->type', 'like', 'crm_%')
                    ->orWhere('type', 'like', '%CrmNotification%');
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? 'crm_notification',
                'title' => $notification->data['title'] ?? 'CRM',
                'body' => $notification->data['message'] ?? ($notification->data['body'] ?? null),
                'href' => $notification->data['href'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                'source' => 'notification',
            ])
            ->all();

        $overdueFollowUps = CrmFollowUp::query()
            ->with(['lead:id,reference,full_name'])
            ->where('assigned_to', $actor->id)
            ->whereIn('status', [CrmFollowUpStatus::Scheduled->value, CrmFollowUpStatus::Overdue->value])
            ->where('scheduled_at', '<', now())
            ->orderBy('scheduled_at')
            ->limit(25)
            ->get()
            ->map(fn (CrmFollowUp $followUp): array => [
                'id' => 'followup-'.$followUp->id,
                'type' => 'crm_follow_up_overdue',
                'title' => 'Overdue follow-up',
                'body' => ($followUp->lead?->full_name ?? 'Lead').' — '.$followUp->scheduled_at?->toDateTimeString(),
                'href' => '/crm/leads/'.$followUp->lead_id,
                'read_at' => null,
                'created_at' => $followUp->scheduled_at?->toIso8601String(),
                'source' => 'follow_up',
                'related_id' => $followUp->id,
            ])
            ->all();

        $items = array_values(array_merge($overdueFollowUps, $notifications));

        return [
            'items' => $items,
            'unread_count' => collect($items)->whereNull('read_at')->count(),
        ];
    }

    /**
     * @param  list<string>  $ids
     */
    public function markRead(User $actor, array $ids = [], bool $all = false): int
    {
        $query = $actor->unreadNotifications();

        if (! $all && $ids !== []) {
            $query->whereIn('id', $ids);
        } elseif (! $all) {
            return 0;
        }

        $count = $query->count();
        $query->update(['read_at' => now()]);

        return $count;
    }

    public function markStaleLeads(): int
    {
        $days = $this->settings->staleLeadDays();
        $threshold = now()->subDays($days);

        $stale = CrmLead::query()
            ->whereNull('archived_at')
            ->whereNotIn('status', [CrmLeadStatus::Won->value, CrmLeadStatus::Lost->value])
            ->where('needs_attention', false)
            ->where(function ($query) use ($threshold): void {
                $query->where(function ($inner) use ($threshold): void {
                    $inner->whereNull('last_contacted_at')
                        ->where('created_at', '<', $threshold);
                })->orWhere('last_contacted_at', '<', $threshold);
            })
            ->get();

        foreach ($stale as $lead) {
            $lead->update(['needs_attention' => true]);
            if ($lead->assignee) {
                $this->notifier->crmStaleLead($lead);
            } elseif ($lead->assigned_to) {
                $assignee = User::query()->find($lead->assigned_to);
                if ($assignee) {
                    $this->notifier->crmStaleLead($lead);
                }
            }
        }

        return $stale->count();
    }
}
