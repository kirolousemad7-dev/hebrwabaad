<?php

namespace App\Http\Controllers\Api\Media;

use App\Enums\MediaVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Media\MediaEntityRegistry;
use App\Services\Media\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly MediaEntityRegistry $registry,
    ) {}

    public function entityTypes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        return ApiResponse::success([
            'entity_types' => $this->registry->keys(),
            'visibilities' => MediaVisibility::values(),
            'max_kilobytes' => MediaService::MAX_KILOBYTES,
            'allowed_extensions' => MediaService::ALLOWED_EXTENSIONS,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $page = $this->mediaService->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => MediaResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $item = $this->mediaService->store(
            $request->user(),
            $request->file('file'),
            $request->safe()->only(['entity_type', 'entity_id', 'visibility', 'metadata']),
        );

        return ApiResponse::success(MediaResource::make($item)->resolve($request), 201);
    }

    public function show(Request $request, Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        return ApiResponse::success(
            MediaResource::make($media->load($this->mediaService->eagerLoad()))->resolve($request)
        );
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        $this->authorize('update', $media);

        if ($request->hasFile('file')) {
            $item = $this->mediaService->replace(
                $request->user(),
                $media,
                $request->file('file'),
                $request->safe()->only(['visibility', 'metadata']),
            );
        } else {
            $item = $this->mediaService->updateMeta(
                $media,
                $request->safe()->only(['visibility', 'metadata']),
            );
        }

        return ApiResponse::success(MediaResource::make($item)->resolve($request));
    }

    public function duplicate(Request $request, Media $media): JsonResponse
    {
        $this->authorize('duplicate', $media);

        $copy = $this->mediaService->duplicate($request->user(), $media);

        return ApiResponse::success(MediaResource::make($copy)->resolve($request), 201);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        $this->mediaService->delete($media);

        return ApiResponse::success(null);
    }

    public function download(Request $request, Media $media): StreamedResponse
    {
        $this->authorize('download', $media);

        return $this->mediaService->download($media);
    }

    public function preview(Request $request, Media $media): StreamedResponse
    {
        $this->authorize('download', $media);

        return $this->mediaService->preview($media);
    }
}
