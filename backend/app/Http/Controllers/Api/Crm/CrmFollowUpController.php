<?php

namespace App\Http\Controllers\Api\Crm;

use App\Enums\CrmFollowUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmFollowUpRequest;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Services\Crm\CrmFollowUpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmFollowUpController extends Controller
{
    public function __construct(private readonly CrmFollowUpService $followUps) {}

    public function index(Request $request): JsonResponse
    {
        $this->followUps->markOverdue();
        $page = $this->followUps->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreCrmFollowUpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $lead = CrmLead::query()->findOrFail($data['lead_id']);
        $followUp = $this->followUps->schedule($request->user(), $lead, $data);

        return ApiResponse::success($followUp, 201);
    }

    public function complete(Request $request, CrmFollowUp $followUp): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $followUp = $this->followUps->complete($request->user(), $followUp, $data['notes'] ?? null);

        return ApiResponse::success($followUp);
    }

    public function reschedule(Request $request, CrmFollowUp $followUp): JsonResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->followUps->assertVisible($request->user(), $followUp);

        $followUp->update([
            'scheduled_at' => $data['scheduled_at'],
            'status' => CrmFollowUpStatus::Scheduled->value,
            'notes' => $data['notes'] ?? $followUp->notes,
            'completed_at' => null,
        ]);

        $followUp->lead?->update(['next_follow_up_at' => $followUp->scheduled_at]);

        return ApiResponse::success($followUp->fresh(['lead', 'assignee']));
    }

    public function cancel(Request $request, CrmFollowUp $followUp): JsonResponse
    {
        $this->followUps->assertVisible($request->user(), $followUp);

        $followUp->update([
            'status' => CrmFollowUpStatus::Cancelled->value,
        ]);

        return ApiResponse::success($followUp->fresh(['lead', 'assignee']));
    }
}
