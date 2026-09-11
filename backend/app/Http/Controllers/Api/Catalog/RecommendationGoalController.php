<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\RecommendationGoal;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationGoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goals = RecommendationGoal::query()
            ->active()
            ->with(['items' => fn ($query) => $query->active()->orderBy('priority')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RecommendationGoal $goal) => [
                'id' => $goal->id,
                'slug' => $goal->slug,
                'name_ar' => $goal->name_ar,
                'explanation' => $goal->explanation,
                'items' => $goal->items->map(fn ($item) => [
                    'item_type' => $item->item_type,
                    'item_slug' => $item->item_slug,
                    'priority' => $item->priority,
                    'reason_ar' => $item->reason_ar,
                    'quantity' => $item->quantity,
                ])->values()->all(),
            ]);

        return ApiResponse::success($goals->values()->all());
    }
}
