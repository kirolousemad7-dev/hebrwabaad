<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreRecommendationGoalRequest;
use App\Http\Requests\Catalog\UpdateRecommendationGoalRequest;
use App\Http\Resources\RecommendationGoalResource;
use App\Models\RecommendationGoal;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationGoalAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goals = RecommendationGoal::query()
            ->with('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(RecommendationGoalResource::collection($goals)->resolve($request));
    }

    public function store(StoreRecommendationGoalRequest $request): JsonResponse
    {
        $goal = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['items']);

            if (empty($data['slug'])) {
                $data['slug'] = RecommendationGoal::uniqueSlug($data['name_ar'] ?? 'goal');
            }

            $goal = RecommendationGoal::query()->create($data);
            $this->syncItems($goal, $request->validated('items') ?? []);

            return $goal->load('items');
        });

        return ApiResponse::success(RecommendationGoalResource::make($goal)->resolve($request), 201);
    }

    public function show(Request $request, RecommendationGoal $recommendationGoal): JsonResponse
    {
        $recommendationGoal->load('items');

        return ApiResponse::success(RecommendationGoalResource::make($recommendationGoal)->resolve($request));
    }

    public function update(UpdateRecommendationGoalRequest $request, RecommendationGoal $recommendationGoal): JsonResponse
    {
        $goal = DB::transaction(function () use ($request, $recommendationGoal) {
            $data = $request->safe()->except(['items']);
            $recommendationGoal->update($data);

            if (array_key_exists('items', $request->validated())) {
                $this->syncItems($recommendationGoal, $request->validated('items') ?? []);
            }

            return $recommendationGoal->load('items');
        });

        return ApiResponse::success(RecommendationGoalResource::make($goal)->resolve($request));
    }

    public function destroy(RecommendationGoal $recommendationGoal): JsonResponse
    {
        $recommendationGoal->delete();

        return ApiResponse::success(null);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(RecommendationGoal $goal, array $items): void
    {
        $goal->items()->delete();

        foreach ($items as $index => $item) {
            $goal->items()->create([
                'item_type' => $item['item_type'],
                'item_slug' => $item['item_slug'],
                'priority' => $item['priority'] ?? $index,
                'reason_ar' => $item['reason_ar'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'is_active' => $item['is_active'] ?? true,
            ]);
        }
    }
}
