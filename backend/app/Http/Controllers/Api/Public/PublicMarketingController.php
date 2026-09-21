<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarketingSectionResource;
use App\Services\Marketing\MarketingContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PublicMarketingController extends Controller
{
    public function __construct(private readonly MarketingContentService $contentService) {}

    /**
     * Public marketing payload. Resilient: empty sections on failure rather than 500.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $sections = $this->contentService->listEnabledForPublic();

            return ApiResponse::success([
                'sections' => MarketingSectionResource::collection(collect($sections))->resolve($request),
            ]);
        } catch (Throwable) {
            return ApiResponse::success([
                'sections' => [],
            ]);
        }
    }

    public function show(Request $request, string $key): JsonResponse
    {
        try {
            $section = $this->contentService->findByKey($key);

            if (! $section->is_enabled) {
                return ApiResponse::error(__('messages.not_found'), 404);
            }

            $section->load(['contents' => fn ($q) => $q->enabled()->with('media')]);

            return ApiResponse::success(MarketingSectionResource::make($section)->resolve($request));
        } catch (Throwable) {
            return ApiResponse::error(__('messages.not_found'), 404);
        }
    }
}
