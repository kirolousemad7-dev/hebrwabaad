<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmPipelineStage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmPipelineController extends Controller
{
    public function stages(): JsonResponse
    {
        $stages = CrmPipelineStage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(['items' => $stages]);
    }

    public function reorder(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->role?->canManageCrmSettings() === true,
            403,
            __('messages.forbidden'),
        );

        $data = $request->validate([
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['integer', 'exists:crm_pipeline_stages,id'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['stage_ids'] as $index => $stageId) {
                CrmPipelineStage::query()->whereKey($stageId)->update(['sort_order' => $index + 1]);
            }
        });

        return $this->stages();
    }
}
