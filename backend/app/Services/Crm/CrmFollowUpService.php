<?php

namespace App\Services\Crm;

use App\Enums\CrmFollowUpStatus;
use App\Enums\CrmFollowUpType;
use App\Enums\CrmLeadPriority;
use App\Enums\UserRole;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmFollowUpService
{
    public function __construct(private readonly CrmLeadService $leads) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmFollowUp>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmFollowUp::query()->with([
            'lead:id,reference,full_name,assigned_to',
            'assignee:id,name,email',
            'opportunity:id,reference,name',
        ]);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('assigned_to', $actor->id);
        }

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], CrmFollowUpStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (($filters['overdue'] ?? null) === '1' || ($filters['overdue'] ?? null) === true) {
            $query->whereIn('status', [CrmFollowUpStatus::Scheduled->value, CrmFollowUpStatus::Overdue->value])
                ->where('scheduled_at', '<', now());
        }

        return $query->orderBy('scheduled_at')->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(User $actor, CrmLead $lead, array $data): CrmFollowUp
    {
        $this->leads->assertVisible($actor, $lead);

        $assignedTo = (int) ($data['assigned_to'] ?? $lead->assigned_to ?? $actor->id);
        $assignee = User::query()->find($assignedTo);

        if ($assignee === null || ! $assignee->is_active || ! $assignee->role instanceof UserRole || ! $assignee->role->canAccessCrm()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Selected assignee is not valid.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $data, $assignee): CrmFollowUp {
            $followUp = CrmFollowUp::query()->create([
                'lead_id' => $lead->id,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'assigned_to' => $assignee->id,
                'type' => $data['type'] ?? CrmFollowUpType::Call->value,
                'scheduled_at' => $data['scheduled_at'],
                'priority' => $data['priority'] ?? CrmLeadPriority::Medium->value,
                'notes' => $data['notes'] ?? null,
                'status' => CrmFollowUpStatus::Scheduled->value,
            ]);

            $lead->update(['next_follow_up_at' => $followUp->scheduled_at]);

            return $followUp->load(['lead', 'assignee']);
        });
    }

    public function complete(User $actor, CrmFollowUp $followUp, ?string $notes = null): CrmFollowUp
    {
        $this->assertVisible($actor, $followUp);

        $followUp->update([
            'status' => CrmFollowUpStatus::Completed->value,
            'completed_at' => now(),
            'notes' => $notes ?? $followUp->notes,
        ]);

        $followUp->lead?->update(['last_contacted_at' => now()]);

        return $followUp->fresh(['lead', 'assignee']) ?? $followUp;
    }

    public function markOverdue(?CrmFollowUp $followUp = null): int
    {
        $query = CrmFollowUp::query()
            ->where('status', CrmFollowUpStatus::Scheduled->value)
            ->where('scheduled_at', '<', now());

        if ($followUp !== null) {
            $query->whereKey($followUp->id);
        }

        return $query->update(['status' => CrmFollowUpStatus::Overdue->value]);
    }

    public function assertVisible(User $actor, CrmFollowUp $followUp): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        if ((int) $followUp->assigned_to === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'follow_up' => ['You do not have access to this follow-up.'],
        ]);
    }
}
