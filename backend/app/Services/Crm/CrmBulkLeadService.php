<?php

namespace App\Services\Crm;

use App\Enums\CrmFollowUpType;
use App\Enums\CrmLeadPriority;
use App\Enums\UserRole;
use App\Models\CrmLead;
use App\Models\CrmTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmBulkLeadService
{
    public function __construct(
        private readonly CrmLeadService $leads,
        private readonly CrmFollowUpService $followUps,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{updated: int, leads: list<CrmLead>}
     */
    public function handle(User $actor, array $payload): array
    {
        $ids = array_values(array_unique(array_map('intval', $payload['lead_ids'] ?? [])));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'lead_ids' => ['At least one lead is required.'],
            ]);
        }

        $action = (string) ($payload['action'] ?? '');
        $leads = CrmLead::query()->whereIn('id', $ids)->get();

        foreach ($leads as $lead) {
            $this->leads->assertVisible($actor, $lead);
        }

        $updated = DB::transaction(function () use ($actor, $leads, $action, $payload): array {
            $result = [];

            foreach ($leads as $lead) {
                $result[] = match ($action) {
                    'assign' => $this->bulkAssign($actor, $lead, (int) ($payload['assigned_to'] ?? 0)),
                    'stage' => $this->leads->moveStage($actor, $lead, (int) ($payload['stage_id'] ?? 0)),
                    'priority' => $this->updatePriority($actor, $lead, (string) ($payload['priority'] ?? '')),
                    'tags' => $this->updateTags($actor, $lead, $payload['tags'] ?? []),
                    'schedule_follow_up' => $this->scheduleFollowUp($actor, $lead, $payload),
                    'archive' => $this->archive($actor, $lead),
                    default => throw ValidationException::withMessages([
                        'action' => ['Unsupported bulk action.'],
                    ]),
                };
            }

            return $result;
        });

        return [
            'updated' => count($updated),
            'leads' => $updated,
        ];
    }

    private function bulkAssign(User $actor, CrmLead $lead, int $assignedTo): CrmLead
    {
        if ($assignedTo <= 0) {
            throw ValidationException::withMessages(['assigned_to' => ['Assignee is required.']]);
        }

        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return $this->leads->assign($actor, $lead, $assignedTo);
        }

        throw ValidationException::withMessages(['assigned_to' => ['You cannot bulk assign leads.']]);
    }

    private function updatePriority(User $actor, CrmLead $lead, string $priority): CrmLead
    {
        if (! in_array($priority, CrmLeadPriority::values(), true)) {
            throw ValidationException::withMessages(['priority' => ['Invalid priority.']]);
        }

        return $this->leads->updateLead($actor, $lead, ['priority' => $priority]);
    }

    /**
     * @param  list<mixed>  $tags
     */
    private function updateTags(User $actor, CrmLead $lead, array $tags): CrmLead
    {
        $names = array_values(array_filter(array_map(fn ($t) => is_string($t) ? trim($t) : null, $tags)));
        $lead = $this->leads->updateLead($actor, $lead, ['tags' => $names]);

        $tagIds = [];
        foreach ($names as $name) {
            $tag = CrmTag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
            $tagIds[] = $tag->id;
        }
        $lead->tagModels()->sync($tagIds);

        return $this->leads->load($lead->fresh() ?? $lead);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function scheduleFollowUp(User $actor, CrmLead $lead, array $payload): CrmLead
    {
        if (empty($payload['scheduled_at'])) {
            throw ValidationException::withMessages(['scheduled_at' => ['Follow-up time is required.']]);
        }

        $this->followUps->schedule($actor, $lead, [
            'type' => $payload['follow_up_type'] ?? CrmFollowUpType::Call->value,
            'scheduled_at' => $payload['scheduled_at'],
            'notes' => $payload['notes'] ?? null,
            'assigned_to' => $payload['assigned_to'] ?? $lead->assigned_to ?? $actor->id,
        ]);

        return $this->leads->load($lead->fresh() ?? $lead);
    }

    private function archive(User $actor, CrmLead $lead): CrmLead
    {
        $lead->update(['archived_at' => now()]);

        return $this->leads->load($lead->fresh() ?? $lead);
    }
}
