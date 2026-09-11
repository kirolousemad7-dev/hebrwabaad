<?php

namespace App\Services\Crm;

use App\Enums\CrmFollowUpStatus;
use App\Enums\CrmLeadStatus;
use App\Enums\CrmQuotationStatus;
use App\Enums\UserRole;
use App\Models\CrmActivity;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmPipelineStage;
use App\Models\CrmQuotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CrmDashboardService
{
    public function __construct(
        private readonly CrmSettingsService $settings,
        private readonly CrmInboxService $inbox,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $actor, ?string $from = null, ?string $to = null, ?string $view = null): array
    {
        $this->inbox->markStaleLeads();

        $base = $this->baseSummary($actor, $from, $to);
        $resolvedView = $view
            ?? (($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) ? 'manager' : 'rep');

        if ($resolvedView === 'manager' && $actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            $base['view'] = 'manager';
            $base['manager'] = $this->managerHome($actor, $from, $to);
        } else {
            $base['view'] = 'rep';
            $base['rep'] = $this->repHome($actor, $from, $to);
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSummary(User $actor, ?string $from = null, ?string $to = null): array
    {
        $fromAt = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toAt = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $leads = CrmLead::query();
        $this->scopeLeads($leads, $actor);

        $newLeads = (clone $leads)
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->count();

        $wonLeads = (clone $leads)
            ->where('status', CrmLeadStatus::Won->value)
            ->whereBetween('converted_at', [$fromAt, $toAt])
            ->count();

        $lostLeads = (clone $leads)
            ->where('status', CrmLeadStatus::Lost->value)
            ->whereBetween('updated_at', [$fromAt, $toAt])
            ->count();

        $openLeads = (clone $leads)
            ->whereNotIn('status', [CrmLeadStatus::Won->value, CrmLeadStatus::Lost->value])
            ->whereNull('archived_at')
            ->count();

        $pipelineValue = (float) (clone $leads)
            ->whereNotIn('status', [CrmLeadStatus::Won->value, CrmLeadStatus::Lost->value])
            ->whereNull('archived_at')
            ->sum(DB::raw('COALESCE(deal_value, estimated_budget, 0)'));

        $wonRevenue = (float) (clone $leads)
            ->where('status', CrmLeadStatus::Won->value)
            ->whereBetween('converted_at', [$fromAt, $toAt])
            ->sum(DB::raw('COALESCE(deal_value, 0)'));

        $followUps = CrmFollowUp::query()->whereBetween('scheduled_at', [$fromAt, $toAt]);
        $this->scopeFollowUps($followUps, $actor);

        $overdueFollowUps = CrmFollowUp::query()
            ->whereIn('status', [CrmFollowUpStatus::Scheduled->value, CrmFollowUpStatus::Overdue->value])
            ->where('scheduled_at', '<', now());
        $this->scopeFollowUps($overdueFollowUps, $actor);

        $activities = CrmActivity::query()->whereBetween('occurred_at', [$fromAt, $toAt]);
        $this->scopeActivities($activities, $actor);

        $quotations = CrmQuotation::query()->whereBetween('created_at', [$fromAt, $toAt]);
        $this->scopeQuotations($quotations, $actor);

        $opportunities = CrmOpportunity::query();
        $this->scopeOpportunities($opportunities, $actor);
        $weightedPipeline = (float) $opportunities
            ->whereNull('won_at')
            ->whereNull('lost_at')
            ->whereNull('archived_at')
            ->get()
            ->sum(fn (CrmOpportunity $opportunity): float => $opportunity->weightedRevenue());

        $funnelStages = CrmPipelineStage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $funnel = $funnelStages->map(function ($stage) use ($actor, $fromAt, $toAt): array {
            $stageLeads = CrmLead::query()->where('stage_id', $stage->id);
            $this->scopeLeads($stageLeads, $actor);
            $stageLeads->whereBetween('created_at', [$fromAt, $toAt]);

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'slug' => $stage->slug,
                'count' => (clone $stageLeads)->count(),
                'value' => (float) (clone $stageLeads)->sum(DB::raw('COALESCE(deal_value, estimated_budget, 0)')),
            ];
        })->values()->all();

        return [
            'range' => [
                'from' => $fromAt->toIso8601String(),
                'to' => $toAt->toIso8601String(),
            ],
            'kpis' => [
                'new_leads' => $newLeads,
                'open_leads' => $openLeads,
                'won_leads' => $wonLeads,
                'lost_leads' => $lostLeads,
                'win_rate' => ($wonLeads + $lostLeads) > 0
                    ? round(($wonLeads / ($wonLeads + $lostLeads)) * 100, 1)
                    : 0,
                'pipeline_value' => $pipelineValue,
                'won_revenue' => $wonRevenue,
                'weighted_pipeline' => $weightedPipeline,
                'follow_ups_scheduled' => (clone $followUps)->count(),
                'follow_ups_overdue' => $overdueFollowUps->count(),
                'activities' => $activities->count(),
                'quotations' => (clone $quotations)->count(),
                'quotations_accepted' => (clone $quotations)
                    ->where('status', CrmQuotationStatus::Accepted->value)
                    ->count(),
                'stale_leads' => (clone $leads)->where('needs_attention', true)->whereNull('archived_at')->count(),
                'unassigned_leads' => (clone $leads)->whereNull('assigned_to')->whereNull('archived_at')->count(),
            ],
            'funnel' => $funnel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function managerHome(User $actor, ?string $from, ?string $to): array
    {
        $fromAt = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toAt = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $slaMinutes = $this->settings->newLeadSlaMinutes();

        $reps = User::query()
            ->active()
            ->where('role', UserRole::SalesRepresentative)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $performance = $reps->map(function (User $rep) use ($fromAt, $toAt): array {
            $won = CrmLead::query()
                ->where('assigned_to', $rep->id)
                ->where('status', CrmLeadStatus::Won->value)
                ->whereBetween('converted_at', [$fromAt, $toAt]);

            $open = CrmLead::query()
                ->where('assigned_to', $rep->id)
                ->whereNull('archived_at')
                ->whereNotIn('status', [CrmLeadStatus::Won->value, CrmLeadStatus::Lost->value])
                ->count();

            return [
                'user' => ['id' => $rep->id, 'name' => $rep->name, 'email' => $rep->email],
                'won_deals' => (clone $won)->count(),
                'won_revenue' => (float) (clone $won)->sum(DB::raw('COALESCE(deal_value, 0)')),
                'open_leads' => $open,
                'activities' => CrmActivity::query()
                    ->where('user_id', $rep->id)
                    ->whereBetween('occurred_at', [$fromAt, $toAt])
                    ->count(),
            ];
        })->values()->all();

        $slaLeads = CrmLead::query()
            ->whereNotNull('first_contacted_at')
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->get(['created_at', 'first_contacted_at']);

        $avgSla = $slaLeads->isEmpty()
            ? null
            : round($slaLeads->avg(fn (CrmLead $lead) => $lead->created_at?->diffInMinutes($lead->first_contacted_at) ?? 0), 1);

        return [
            'rep_performance' => $performance,
            'stale_leads' => CrmLead::query()->where('needs_attention', true)->whereNull('archived_at')->count(),
            'unassigned_count' => CrmLead::query()->whereNull('assigned_to')->whereNull('archived_at')
                ->whereNotIn('status', [CrmLeadStatus::Won->value, CrmLeadStatus::Lost->value])->count(),
            'sla_target_minutes' => $slaMinutes,
            'avg_first_contact_minutes' => $avgSla,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repHome(User $actor, ?string $from, ?string $to): array
    {
        $fromAt = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toAt = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        return [
            'my_open_leads' => CrmLead::query()
                ->where('assigned_to', $actor->id)
                ->whereNull('archived_at')
                ->whereNotIn('status', [CrmLeadStatus::Won->value, CrmLeadStatus::Lost->value])
                ->count(),
            'my_overdue_follow_ups' => CrmFollowUp::query()
                ->where('assigned_to', $actor->id)
                ->whereIn('status', [CrmFollowUpStatus::Scheduled->value, CrmFollowUpStatus::Overdue->value])
                ->where('scheduled_at', '<', now())
                ->count(),
            'my_needs_attention' => CrmLead::query()
                ->where('assigned_to', $actor->id)
                ->where('needs_attention', true)
                ->whereNull('archived_at')
                ->count(),
            'my_won_revenue' => (float) CrmLead::query()
                ->where('assigned_to', $actor->id)
                ->where('status', CrmLeadStatus::Won->value)
                ->whereBetween('converted_at', [$fromAt, $toAt])
                ->sum(DB::raw('COALESCE(deal_value, 0)')),
        ];
    }

    /**
     * @param  Builder<CrmLead>  $query
     */
    private function scopeLeads($query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('assigned_to', $actor->id);
    }

    /**
     * @param  Builder<CrmFollowUp>  $query
     */
    private function scopeFollowUps($query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('assigned_to', $actor->id);
    }

    /**
     * @param  Builder<CrmActivity>  $query
     */
    private function scopeActivities($query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('user_id', $actor->id);
    }

    /**
     * @param  Builder<CrmQuotation>  $query
     */
    private function scopeQuotations($query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('created_by', $actor->id);
    }

    /**
     * @param  Builder<CrmOpportunity>  $query
     */
    private function scopeOpportunities($query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('assigned_to', $actor->id);
    }
}
