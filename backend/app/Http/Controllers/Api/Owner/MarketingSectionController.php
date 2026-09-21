<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\UpdateMarketingSectionRequest;
use App\Http\Resources\MarketingSectionResource;
use App\Models\MarketingSection;
use App\Services\Marketing\MarketingContentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingSectionController extends Controller
{
    public function __construct(private readonly MarketingContentService $contentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MarketingSection::class);

        $sections = $this->contentService->listSectionsForOwner();

        return ApiResponse::success(
            MarketingSectionResource::collection(collect($sections))->resolve($request)
        );
    }

    public function show(Request $request, MarketingSection $section): JsonResponse
    {
        $this->authorize('view', $section);

        $section->load(['contents.media']);

        return ApiResponse::success(MarketingSectionResource::make($section)->resolve($request));
    }

    public function update(UpdateMarketingSectionRequest $request, MarketingSection $section): JsonResponse
    {
        $this->authorize('update', $section);

        $data = $request->validated();
        $section = $this->contentService->updateSection($section, $data);

        if (isset($data['contents']) && is_array($data['contents'])) {
            $section = $this->contentService->syncContents($section, $data['contents']);
        }

        return ApiResponse::success(MarketingSectionResource::make($section)->resolve($request));
    }
}
