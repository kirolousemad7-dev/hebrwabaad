<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Services\Crm\CrmLeadService;
use App\Services\Crm\CrmOpportunityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmOpportunityController extends Controller
{
    public function __construct(
        private readonly CrmOpportunityService $opportunities,
        private readonly CrmLeadService $leads,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->opportunities->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'weighted_revenue' => $this->opportunities->weightedRevenue($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'name' => ['nullable', 'string', 'max:200'],
            'deal_value' => ['nullable', 'numeric', 'min:0'],
            'stage_id' => ['nullable', 'integer', 'exists:crm_pipeline_stages,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'expected_close_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $lead = $this->leads->assertVisible($request->user(), CrmLead::query()->findOrFail($data['lead_id']));
        $opportunity = $this->opportunities->createFromLead($request->user(), $lead, $data);

        return ApiResponse::success($opportunity, 201);
    }

    public function show(Request $request, CrmOpportunity $opportunity): JsonResponse
    {
        $this->opportunities->assertVisible($request->user(), $opportunity);

        return ApiResponse::success($this->opportunities->load($opportunity));
    }

    public function stage(Request $request, CrmOpportunity $opportunity): JsonResponse
    {
        $data = $request->validate([
            'stage_id' => ['required', 'integer', 'exists:crm_pipeline_stages,id'],
        ]);

        $opportunity = $this->opportunities->moveStage(
            $request->user(),
            $opportunity,
            (int) $data['stage_id'],
        );

        return ApiResponse::success($opportunity);
    }
}
