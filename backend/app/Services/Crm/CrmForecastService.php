<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmOpportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CrmForecastService
{
    /**
     * @return array<string, mixed>
     */
    public function forecast(User $actor): array
    {
        $query = CrmOpportunity::query()
            ->whereNull('won_at')
            ->whereNull('lost_at')
            ->whereNull('archived_at');

        $this->scope($query, $actor);

        $open = $query->get();
        $pipelineTotal = (float) $open->sum(fn (CrmOpportunity $o) => (float) $o->deal_value);
        $weighted = (float) $open->sum(fn (CrmOpportunity $o) => $o->weightedRevenue());
        $commit = (float) $open->filter(fn (CrmOpportunity $o) => (int) $o->probability >= 80)
            ->sum(fn (CrmOpportunity $o) => (float) $o->deal_value);
        $bestCase = (float) $open->filter(fn (CrmOpportunity $o) => (int) $o->probability >= 50)
            ->sum(fn (CrmOpportunity $o) => (float) $o->deal_value);

        $thisMonthStart = now()->startOfMonth();
        $thisMonthEnd = now()->endOfMonth();
        $nextMonthStart = now()->addMonthNoOverflow()->startOfMonth();
        $nextMonthEnd = now()->addMonthNoOverflow()->endOfMonth();

        $expectedThisMonth = (float) $open
            ->filter(fn (CrmOpportunity $o) => $this->inRange($o->expected_close_at, $thisMonthStart, $thisMonthEnd))
            ->sum(fn (CrmOpportunity $o) => $o->weightedRevenue());

        $expectedNextMonth = (float) $open
            ->filter(fn (CrmOpportunity $o) => $this->inRange($o->expected_close_at, $nextMonthStart, $nextMonthEnd))
            ->sum(fn (CrmOpportunity $o) => $o->weightedRevenue());

        return [
            'pipeline_total' => round($pipelineTotal, 2),
            'weighted' => round($weighted, 2),
            'commit' => round($commit, 2),
            'best_case' => round($bestCase, 2),
            'expected_this_month' => round($expectedThisMonth, 2),
            'expected_next_month' => round($expectedNextMonth, 2),
            'open_count' => $open->count(),
        ];
    }

    private function inRange(mixed $date, Carbon $from, Carbon $to): bool
    {
        if ($date === null) {
            return false;
        }

        $value = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $value->betweenIncluded($from, $to);
    }

    /**
     * @param  Builder<CrmOpportunity>  $query
     */
    private function scope(Builder $query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('assigned_to', $actor->id);
    }
}
