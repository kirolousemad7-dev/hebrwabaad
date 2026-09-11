<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Portfolio\PortfolioShowcaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPortfolioController extends Controller
{
    public function __construct(private readonly PortfolioShowcaseService $portfolio) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->portfolio->listPublished([
            'category' => $request->query('category'),
            'sector' => $request->query('sector'),
            'service' => $request->query('service'),
        ]);

        return ApiResponse::success(
            $items->map(fn ($item) => $this->portfolio->listingPayload($item))->values()->all()
        );
    }

    public function show(string $slug): JsonResponse
    {
        $item = $this->portfolio->findPublishedBySlug($slug);

        return ApiResponse::success($this->portfolio->detailPayload($item));
    }
}
