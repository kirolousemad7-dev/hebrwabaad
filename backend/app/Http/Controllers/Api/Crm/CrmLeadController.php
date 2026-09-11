<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AssignCrmLeadRequest;
use App\Http\Requests\Crm\ConvertCrmLeadRequest;
use App\Http\Requests\Crm\LoseCrmLeadRequest;
use App\Http\Requests\Crm\MoveCrmLeadStageRequest;
use App\Http\Requests\Crm\StoreCrmLeadRequest;
use App\Http\Requests\Crm\UpdateCrmLeadRequest;
use App\Http\Resources\CrmLeadResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProjectResource;
use App\Models\CrmLead;
use App\Services\Crm\CrmLeadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmLeadController extends Controller
{
    public function __construct(private readonly CrmLeadService $leads) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->leads->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => CrmLeadResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreCrmLeadRequest $request): JsonResponse
    {
        $lead = $this->leads->createLead($request->user(), $request->validated());

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request), 201);
    }

    public function show(Request $request, CrmLead $lead): JsonResponse
    {
        $lead = $this->leads->assertVisible($request->user(), $lead);

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request));
    }

    public function update(UpdateCrmLeadRequest $request, CrmLead $lead): JsonResponse
    {
        $lead = $this->leads->updateLead($request->user(), $lead, $request->validated());

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request));
    }

    public function assign(AssignCrmLeadRequest $request, CrmLead $lead): JsonResponse
    {
        $lead = $this->leads->assign(
            $request->user(),
            $lead,
            (int) $request->validated('assigned_to'),
        );

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request));
    }

    public function stage(MoveCrmLeadStageRequest $request, CrmLead $lead): JsonResponse
    {
        $lead = $this->leads->moveStage(
            $request->user(),
            $lead,
            (int) $request->validated('stage_id'),
        );

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request));
    }

    public function convert(ConvertCrmLeadRequest $request, CrmLead $lead): JsonResponse
    {
        $result = $this->leads->convertWon($request->user(), $lead, $request->validated());

        return ApiResponse::success([
            'lead' => CrmLeadResource::make($result['lead'])->resolve($request),
            'customer' => [
                'id' => $result['customer']->id,
                'name' => $result['customer']->name,
                'email' => $result['customer']->email,
            ],
            'order' => OrderResource::make($result['order'])->resolve($request),
            'project' => $result['project'] === null
                ? null
                : ProjectResource::make($result['project'])->resolve($request),
        ]);
    }

    public function lose(LoseCrmLeadRequest $request, CrmLead $lead): JsonResponse
    {
        $lead = $this->leads->markLost(
            $request->user(),
            $lead,
            (int) $request->validated('lost_reason_id'),
            $request->validated('notes'),
            $request->validated('competitor'),
        );

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request));
    }

    public function duplicates(Request $request): JsonResponse
    {
        $duplicates = $this->leads->findDuplicates(
            $request->query('phone'),
            $request->query('email'),
            $request->query('whatsapp'),
            $request->query('except_id') !== null ? (int) $request->query('except_id') : null,
        );

        return ApiResponse::success([
            'items' => CrmLeadResource::collection($duplicates)->resolve($request),
        ]);
    }
}
