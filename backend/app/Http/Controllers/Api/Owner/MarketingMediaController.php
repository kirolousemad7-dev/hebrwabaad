<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreMarketingMediaRequest;
use App\Http\Requests\Marketing\UpdateMarketingMediaRequest;
use App\Http\Resources\MarketingMediaResource;
use App\Models\MarketingSection;
use App\Models\Media;
use App\Services\Marketing\MarketingMediaLibraryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class MarketingMediaController extends Controller
{
    public function __construct(private readonly MarketingMediaLibraryService $library) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);

        $paginator = $this->library->paginate($request->query());

        return ApiResponse::success([
            'items' => MarketingMediaResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreMarketingMediaRequest $request): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $media = $this->library->upload($request->user(), $file, $request->validated());

        return ApiResponse::success(MarketingMediaResource::make($media)->resolve($request), 201);
    }

    public function show(Request $request, Media $media): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);
        $media = $this->library->find((int) $media->id);

        return ApiResponse::success(MarketingMediaResource::make($media)->resolve($request));
    }

    public function update(UpdateMarketingMediaRequest $request, Media $media): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);
        $media = $this->library->find((int) $media->id);
        $media = $this->library->updateMeta($media, $request->validated());

        return ApiResponse::success(MarketingMediaResource::make($media)->resolve($request));
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);
        $media = $this->library->find((int) $media->id);
        $this->library->deleteIfUnused($media);

        return ApiResponse::success(null);
    }

    public function replace(StoreMarketingMediaRequest $request, Media $media): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);
        $previous = $this->library->find((int) $media->id);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $result = $this->library->uploadReplacement($request->user(), $previous, $file, $request->validated());

        return ApiResponse::success([
            'new' => MarketingMediaResource::make($result['new'])->resolve($request),
            'previous' => MarketingMediaResource::make($result['previous'])->resolve($request),
            'note' => 'Previous media was kept. Update content media_id to the new asset, then delete the previous asset when unused.',
        ], 201);
    }

    public function orphans(Request $request): JsonResponse
    {
        $this->authorize('manageMedia', MarketingSection::class);

        return ApiResponse::success(
            MarketingMediaResource::collection(collect($this->library->orphans()))->resolve($request)
        );
    }
}
