<?php

namespace App\Http\Controllers\Api\Crm;

use App\Enums\CrmLeadStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmLostReason;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmReportController extends Controller
{
    public function sources(Request $request): JsonResponse
    {
        $rows = CrmLead::query()
            ->select('source_id', DB::raw('count(*) as total'))
            ->with('source:id,name,slug')
            ->groupBy('source_id')
            ->orderByDesc('total')
            ->get();

        return ApiResponse::success(['items' => $rows]);
    }

    public function reps(Request $request): JsonResponse
    {
        $rows = CrmLead::query()
            ->select(
                'assigned_to',
                DB::raw('count(*) as total_leads'),
                DB::raw("sum(case when status = '".CrmLeadStatus::Won->value."' then 1 else 0 end) as won_leads"),
                DB::raw("sum(case when status = '".CrmLeadStatus::Lost->value."' then 1 else 0 end) as lost_leads"),
                DB::raw('sum(coalesce(deal_value, 0)) as deal_value_sum'),
            )
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->get()
            ->map(function ($row) {
                $user = User::query()->find($row->assigned_to);

                return [
                    'user' => $user === null ? null : [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                    ],
                    'total_leads' => (int) $row->total_leads,
                    'won_leads' => (int) $row->won_leads,
                    'lost_leads' => (int) $row->lost_leads,
                    'deal_value_sum' => (float) $row->deal_value_sum,
                ];
            });

        return ApiResponse::success(['items' => $rows]);
    }

    public function lostReasons(): JsonResponse
    {
        $rows = CrmLead::query()
            ->select('lost_reason_id', DB::raw('count(*) as total'))
            ->where('status', CrmLeadStatus::Lost->value)
            ->whereNotNull('lost_reason_id')
            ->groupBy('lost_reason_id')
            ->get()
            ->map(function ($row) {
                $reason = CrmLostReason::query()->find($row->lost_reason_id);

                return [
                    'reason' => $reason === null ? null : [
                        'id' => $reason->id,
                        'name' => $reason->name,
                        'slug' => $reason->slug,
                    ],
                    'total' => (int) $row->total,
                ];
            });

        return ApiResponse::success(['items' => $rows]);
    }

    public function services(): JsonResponse
    {
        $rows = CrmLead::query()
            ->select('service_id', DB::raw('count(*) as total'), DB::raw('sum(coalesce(deal_value, 0)) as deal_value_sum'))
            ->whereNotNull('service_id')
            ->with('service:id,name,slug')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->get();

        return ApiResponse::success(['items' => $rows]);
    }
}
