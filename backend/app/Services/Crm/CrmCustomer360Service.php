<?php

namespace App\Services\Crm;

use App\Enums\CrmLeadStatus;
use App\Enums\UserRole;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmQuotation;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CrmCustomer360Service
{
    /**
     * @return array<string, mixed>
     */
    public function profile(User $actor, User $customer): array
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canAccessCrm())) {
            throw new AuthorizationException('CRM access required.');
        }

        if ($customer->role !== UserRole::Customer) {
            throw new AuthorizationException('Customer 360 is only available for customer accounts.');
        }

        $leads = CrmLead::query()
            ->with(['stage:id,name,slug', 'assignee:id,name'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        if (! ($actor->role->canManageCrmTeam())) {
            $leads = $leads->filter(fn (CrmLead $lead) => (int) $lead->assigned_to === (int) $actor->id)->values();
        }

        $leadIds = $leads->pluck('id')->all();

        $orders = Order::query()->where('customer_id', $customer->id)->latest()->limit(50)->get();
        $projects = Project::query()->where('customer_id', $customer->id)->latest()->limit(50)->get();
        $quotations = CrmQuotation::query()->where('customer_id', $customer->id)->latest()->limit(50)->get();
        $opportunities = CrmOpportunity::query()->where('customer_id', $customer->id)->latest()->limit(50)->get();
        $activities = $leadIds === []
            ? collect()
            : CrmActivity::query()->whereIn('lead_id', $leadIds)->latest('occurred_at')->limit(50)->get();

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? null,
                'created_at' => $customer->created_at?->toIso8601String(),
            ],
            'metrics' => [
                'leads' => $leads->count(),
                'won_leads' => $leads->where('status', CrmLeadStatus::Won)->count(),
                'orders' => $orders->count(),
                'projects' => $projects->count(),
                'quotations' => $quotations->count(),
                'opportunities' => $opportunities->count(),
                'lifetime_deal_value' => (float) $leads->sum(fn (CrmLead $lead) => (float) ($lead->deal_value ?? 0)),
            ],
            'leads' => $leads->values(),
            'orders' => $orders,
            'projects' => $projects,
            'quotations' => $quotations,
            'opportunities' => $opportunities,
            'recent_activities' => $activities,
        ];
    }
}
