<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmSalesTarget;
use App\Services\Crm\CrmTargetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmTargetController extends Controller
{
    public function __construct(private readonly CrmTargetService $targets) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->targets->paginateFor($request->user(), $request->query());

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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'period_type' => ['nullable', 'string', 'max:40'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'target_type' => ['required', 'in:revenue,deals_won,qualified_leads,new_customers,calls,meetings,quotations'],
            'target_value' => ['required', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success($this->targets->create($request->user(), $data), 201);
    }

    public function update(Request $request, CrmSalesTarget $target): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'period_type' => ['nullable', 'string', 'max:40'],
            'period_start' => ['sometimes', 'date'],
            'period_end' => ['sometimes', 'date'],
            'target_type' => ['sometimes', 'in:revenue,deals_won,qualified_leads,new_customers,calls,meetings,quotations'],
            'target_value' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success($this->targets->update($request->user(), $target, $data));
    }

    public function destroy(Request $request, CrmSalesTarget $target): JsonResponse
    {
        $this->targets->delete($request->user(), $target);

        return ApiResponse::success(['deleted' => true]);
    }

    public function progress(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => $this->targets->progress($request->user(), $request->query()),
        ]);
    }
}
