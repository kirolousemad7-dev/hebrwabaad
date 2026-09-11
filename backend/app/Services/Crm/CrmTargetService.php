<?php

namespace App\Services\Crm;

use App\Enums\CrmActivityType;
use App\Enums\CrmLeadStatus;
use App\Enums\CrmQuotationStatus;
use App\Enums\UserRole;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\CrmSalesTarget;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmTargetService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmSalesTarget>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmSalesTarget::query()->with('user:id,name,email,role');

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('user_id', $actor->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        return $query->latest('period_start')->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): CrmSalesTarget
    {
        $this->assertCanManageTargets($actor);

        $userId = (int) ($data['user_id'] ?? $actor->id);
        $this->assertTargetUser($userId);

        return CrmSalesTarget::query()->create([
            'user_id' => $userId,
            'period_type' => $data['period_type'] ?? 'month',
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'target_type' => $data['target_type'],
            'target_value' => $data['target_value'],
        ])->load('user:id,name,email,role');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, CrmSalesTarget $target, array $data): CrmSalesTarget
    {
        $this->assertCanManageTargets($actor);
        $target->fill(collect($data)->only([
            'period_type', 'period_start', 'period_end', 'target_type', 'target_value', 'user_id',
        ])->all());
        $target->save();

        return $target->fresh('user:id,name,email,role') ?? $target;
    }

    public function delete(User $actor, CrmSalesTarget $target): void
    {
        $this->assertCanManageTargets($actor);
        $target->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function progress(User $actor, array $filters = []): array
    {
        $query = CrmSalesTarget::query()->with('user:id,name,email');

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('user_id', $actor->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        return $query->get()->map(function (CrmSalesTarget $target): array {
            $actual = $this->computeActual($target);

            return [
                'id' => $target->id,
                'user' => $target->user,
                'period_type' => $target->period_type,
                'period_start' => $target->period_start?->toDateString(),
                'period_end' => $target->period_end?->toDateString(),
                'target_type' => $target->target_type,
                'target_value' => (float) $target->target_value,
                'actual_value' => $actual,
                'progress_percent' => (float) $target->target_value > 0
                    ? round(($actual / (float) $target->target_value) * 100, 1)
                    : 0,
            ];
        })->values()->all();
    }

    public function computeActual(CrmSalesTarget $target): float
    {
        $userId = (int) $target->user_id;
        $from = $target->period_start?->startOfDay();
        $to = $target->period_end?->endOfDay();

        return match ($target->target_type) {
            'revenue' => (float) CrmLead::query()
                ->where('assigned_to', $userId)
                ->where('status', CrmLeadStatus::Won->value)
                ->whereBetween('converted_at', [$from, $to])
                ->sum(DB::raw('COALESCE(deal_value, 0)')),
            'deals_won' => (float) CrmLead::query()
                ->where('assigned_to', $userId)
                ->where('status', CrmLeadStatus::Won->value)
                ->whereBetween('converted_at', [$from, $to])
                ->count(),
            'qualified_leads' => (float) CrmLead::query()
                ->where('assigned_to', $userId)
                ->whereBetween('created_at', [$from, $to])
                ->whereHas('stage', fn ($q) => $q->where('probability', '>=', 30))
                ->count(),
            'new_customers' => (float) CrmLead::query()
                ->where('assigned_to', $userId)
                ->whereNotNull('customer_id')
                ->whereBetween('converted_at', [$from, $to])
                ->count(),
            'calls' => (float) CrmActivity::query()
                ->where('user_id', $userId)
                ->where('type', CrmActivityType::Call->value)
                ->whereBetween('occurred_at', [$from, $to])
                ->count(),
            'meetings' => (float) CrmActivity::query()
                ->where('user_id', $userId)
                ->whereIn('type', [CrmActivityType::Meeting->value, CrmActivityType::VideoMeeting->value])
                ->whereBetween('occurred_at', [$from, $to])
                ->count(),
            'quotations' => (float) CrmQuotation::query()
                ->where('created_by', $userId)
                ->whereBetween('created_at', [$from, $to])
                ->whereNotIn('status', [CrmQuotationStatus::Draft->value])
                ->count(),
            default => 0.0,
        };
    }

    private function assertCanManageTargets(User $actor): void
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            throw new AuthorizationException('Only CRM managers can manage targets.');
        }
    }

    private function assertTargetUser(int $userId): void
    {
        $user = User::query()->find($userId);
        if ($user === null || ! $user->role instanceof UserRole || ! $user->role->isSalesRole()) {
            throw ValidationException::withMessages([
                'user_id' => ['Target user must be a sales role.'],
            ]);
        }
    }
}
