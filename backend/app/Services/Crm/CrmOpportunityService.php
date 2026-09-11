<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmPipelineStage;
use App\Models\User;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmOpportunityService
{
    public function __construct(private readonly CrmLeadService $leads) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmOpportunity>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmOpportunity::query()->with([
            'lead:id,reference,full_name,assigned_to',
            'stage',
            'assignee:id,name,email',
        ]);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('assigned_to', $actor->id);
        }

        if (isset($filters['stage_id']) && $filters['stage_id'] !== '') {
            $query->where('stage_id', (int) $filters['stage_id']);
        }

        return $query->latest()->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    public function load(CrmOpportunity $opportunity): CrmOpportunity
    {
        return $opportunity->load(['lead', 'stage', 'assignee:id,name,email', 'company', 'customer:id,name,email']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromLead(User $actor, CrmLead $lead, array $data = []): CrmOpportunity
    {
        $this->leads->assertVisible($actor, $lead);

        return DB::transaction(function () use ($actor, $lead, $data): CrmOpportunity {
            $stage = isset($data['stage_id'])
                ? CrmPipelineStage::query()->findOrFail((int) $data['stage_id'])
                : ($lead->stage ?? CrmPipelineStage::query()->orderBy('sort_order')->firstOrFail());

            $opportunity = CrmOpportunity::query()->create([
                'reference' => $this->generateReference(),
                'name' => $data['name'] ?? ($lead->company_name ?: $lead->full_name),
                'lead_id' => $lead->id,
                'company_id' => $data['company_id'] ?? $lead->company_id,
                'customer_id' => $data['customer_id'] ?? $lead->customer_id,
                'service_id' => $data['service_id'] ?? $lead->service_id,
                'package_id' => $data['package_id'] ?? $lead->package_id,
                'deal_value' => $data['deal_value'] ?? $lead->deal_value ?? 0,
                'probability' => $data['probability'] ?? $stage->probability,
                'stage_id' => $stage->id,
                'assigned_to' => $data['assigned_to'] ?? $lead->assigned_to ?? $actor->id,
                'expected_close_at' => $data['expected_close_at'] ?? $lead->expected_close_at,
                'competitor' => $data['competitor'] ?? null,
                'decision_maker' => $data['decision_maker'] ?? null,
                'notes' => $data['notes'] ?? $lead->notes,
            ]);

            return $this->load($opportunity);
        });
    }

    public function moveStage(User $actor, CrmOpportunity $opportunity, int $stageId): CrmOpportunity
    {
        $this->assertVisible($actor, $opportunity);

        $stage = CrmPipelineStage::query()->where('is_active', true)->find($stageId);

        if ($stage === null) {
            throw ValidationException::withMessages([
                'stage_id' => ['Selected pipeline stage is not valid.'],
            ]);
        }

        $attributes = [
            'stage_id' => $stage->id,
            'probability' => $stage->probability,
        ];

        if ($stage->is_won) {
            $attributes['won_at'] = now();
            $attributes['lost_at'] = null;
        } elseif ($stage->is_lost) {
            $attributes['lost_at'] = now();
            $attributes['won_at'] = null;
        }

        $opportunity->update($attributes);

        if ($stage->is_won) {
            try {
                app(WorkflowAutomationEngine::class)->dispatch('crm.opportunity.won', [
                    'source_type' => 'crm_opportunity',
                    'source_id' => $opportunity->id,
                    'actor_id' => $actor->id,
                    'title' => 'فرصة رابحة: '.($opportunity->name ?? ('#'.$opportunity->id)),
                    'related_type' => 'crm_opportunity',
                    'related_id' => $opportunity->id,
                    'payload' => [
                        'opportunity_id' => $opportunity->id,
                        'stage_id' => $stage->id,
                    ],
                ]);
            } catch (\Throwable) {
                // Workflow hooks must never break CRM stage moves.
            }
        }

        return $this->load($opportunity->fresh() ?? $opportunity);
    }

    public function weightedRevenue(User $actor): float
    {
        $query = CrmOpportunity::query()->whereNull('won_at')->whereNull('lost_at');

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('assigned_to', $actor->id);
        }

        return (float) $query->get()->sum(fn (CrmOpportunity $opportunity): float => $opportunity->weightedRevenue());
    }

    public function assertVisible(User $actor, CrmOpportunity $opportunity): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        if ((int) $opportunity->assigned_to === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'opportunity' => ['You do not have access to this opportunity.'],
        ]);
    }

    public function generateReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'OP-'.$year.'-';

        $latest = CrmOpportunity::query()
            ->where('reference', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $next = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }
}
