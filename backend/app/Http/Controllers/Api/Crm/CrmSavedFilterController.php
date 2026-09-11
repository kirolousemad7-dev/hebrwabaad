<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmSavedFilter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmSavedFilterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = CrmSavedFilter::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'entity' => ['nullable', 'string', 'max:40'],
            'filters' => ['required', 'array'],
        ]);

        $filter = CrmSavedFilter::query()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'entity' => $data['entity'] ?? 'leads',
            'filters' => $data['filters'],
        ]);

        return ApiResponse::success($filter, 201);
    }

    public function destroy(Request $request, CrmSavedFilter $savedFilter): JsonResponse
    {
        abort_unless((int) $savedFilter->user_id === (int) $request->user()->id, 403);
        $savedFilter->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
