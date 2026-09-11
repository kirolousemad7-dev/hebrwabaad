<?php

namespace App\Services\Crm;

use App\Enums\CrmActivityType;
use App\Enums\CrmFollowUpStatus;
use App\Enums\UserRole;
use App\Models\CrmActivity;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\User;
use Carbon\Carbon;

class CrmCalendarService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function events(User $actor, string $from, string $to): array
    {
        $fromAt = Carbon::parse($from)->startOfDay();
        $toAt = Carbon::parse($to)->endOfDay();
        $events = [];

        $followUps = CrmFollowUp::query()
            ->with(['lead:id,reference,full_name,assigned_to', 'assignee:id,name'])
            ->whereBetween('scheduled_at', [$fromAt, $toAt])
            ->whereNotIn('status', [CrmFollowUpStatus::Cancelled->value, CrmFollowUpStatus::Completed->value]);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $followUps->where('assigned_to', $actor->id);
        }

        foreach ($followUps->get() as $followUp) {
            $events[] = [
                'id' => 'followup-'.$followUp->id,
                'type' => 'follow_up',
                'title' => ($followUp->type instanceof \BackedEnum ? $followUp->type->value : $followUp->type).' — '.($followUp->lead?->full_name ?? 'Lead'),
                'starts_at' => $followUp->scheduled_at?->toIso8601String(),
                'related_type' => 'crm_follow_up',
                'related_id' => $followUp->id,
                'lead_id' => $followUp->lead_id,
                'href' => '/crm/leads/'.$followUp->lead_id,
            ];
        }

        $activities = CrmActivity::query()
            ->with(['lead:id,reference,full_name,assigned_to', 'user:id,name'])
            ->whereIn('type', [
                CrmActivityType::Meeting->value,
                CrmActivityType::VideoMeeting->value,
                CrmActivityType::Call->value,
            ])
            ->whereBetween('occurred_at', [$fromAt, $toAt]);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $activities->where('user_id', $actor->id);
        }

        foreach ($activities->get() as $activity) {
            $events[] = [
                'id' => 'activity-'.$activity->id,
                'type' => 'activity',
                'title' => ($activity->type instanceof \BackedEnum ? $activity->type->value : $activity->type).' — '.($activity->lead?->full_name ?? 'Activity'),
                'starts_at' => $activity->occurred_at?->toIso8601String(),
                'related_type' => 'crm_activity',
                'related_id' => $activity->id,
                'lead_id' => $activity->lead_id,
                'href' => $activity->lead_id ? '/crm/leads/'.$activity->lead_id : null,
            ];
        }

        $leads = CrmLead::query()
            ->whereNotNull('expected_close_at')
            ->whereBetween('expected_close_at', [$fromAt->toDateString(), $toAt->toDateString()])
            ->whereNull('archived_at');

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $leads->where('assigned_to', $actor->id);
        }

        foreach ($leads->get() as $lead) {
            $events[] = [
                'id' => 'lead-close-'.$lead->id,
                'type' => 'expected_close',
                'title' => 'Expected close — '.$lead->full_name,
                'starts_at' => $lead->expected_close_at?->startOfDay()->toIso8601String(),
                'related_type' => 'crm_lead',
                'related_id' => $lead->id,
                'lead_id' => $lead->id,
                'href' => '/crm/leads/'.$lead->id,
            ];
        }

        usort($events, fn (array $a, array $b): int => strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? '')));

        return $events;
    }
}
