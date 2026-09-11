<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\UpsertPortfolioItemRequest;
use App\Models\PortfolioItem;
use App\Services\Portfolio\PortfolioShowcaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioItemController extends Controller
{
    public function __construct(private readonly PortfolioShowcaseService $portfolio) {}

    public function index(Request $request): JsonResponse
    {
        $items = PortfolioItem::query()
            ->with(['sectors:id,name_ar,name_en,slug', 'services:id,name,slug', 'package:id,name,slug', 'media'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items->map(fn (PortfolioItem $item) => $this->portfolio->staffPayload($item))->values()->all()
        );
    }

    public function store(UpsertPortfolioItemRequest $request): JsonResponse
    {
        $item = $this->portfolio->upsert($request->validated());

        return ApiResponse::success($this->portfolio->staffPayload($item), 201);
    }

    public function update(UpsertPortfolioItemRequest $request, PortfolioItem $portfolioItem): JsonResponse
    {
        $item = $this->portfolio->upsert($request->validated(), $portfolioItem);

        return ApiResponse::success($this->portfolio->staffPayload($item));
    }

    public function destroy(PortfolioItem $portfolioItem): JsonResponse
    {
        $portfolioItem->delete();

        return ApiResponse::success(null);
    }
}
