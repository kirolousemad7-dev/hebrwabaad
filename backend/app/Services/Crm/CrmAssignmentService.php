<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmAssignmentRule;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\PlatformNotifier;
use Illuminate\Support\Facades\DB;

class CrmAssignmentService
{
    public function __construct(
        private readonly CrmSettingsService $settings,
        private readonly PlatformNotifier $notifier,
    ) {}

    public function assignAfterCreate(CrmLead $lead): CrmLead
    {
        if ($lead->assigned_to !== null) {
            return $lead;
        }

        $mode = $this->settings->assignmentMode();
        if ($mode === 'manual') {
            return $lead;
        }

        $assigneeId = match ($mode) {
            'round_robin' => $this->nextRoundRobinUserId(),
            'by_source' => $this->resolveByRule('source', (string) ($lead->source_id ?? '')),
            'by_service' => $this->resolveByRule('service', (string) ($lead->service_id ?? '')),
            default => null,
        };

        if ($assigneeId === null) {
            return $lead;
        }

        $lead->update(['assigned_to' => $assigneeId]);
        $assignee = User::query()->find($assigneeId);

        if ($assignee !== null) {
            $this->notifier->crmLeadAssigned($lead->fresh() ?? $lead, $assignee);
        }

        return $lead->fresh() ?? $lead;
    }

    private function nextRoundRobinUserId(): ?int
    {
        $reps = User::query()
            ->active()
            ->where('role', UserRole::SalesRepresentative)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($reps === []) {
            return null;
        }

        return DB::transaction(function () use ($reps): int {
            $cursor = (int) $this->settings->get('round_robin_cursor', 0);
            $index = $cursor % count($reps);
            $userId = (int) $reps[$index];
            $this->settings->setMany(['round_robin_cursor' => $cursor + 1]);

            return $userId;
        });
    }

    private function resolveByRule(string $matchType, string $matchValue): ?int
    {
        if ($matchValue === '') {
            return null;
        }

        $rule = CrmAssignmentRule::query()
            ->where('is_active', true)
            ->where('match_type', $matchType)
            ->where('match_value', $matchValue)
            ->orderBy('sort_order')
            ->first();

        return $rule?->assign_to_user_id;
    }
}
