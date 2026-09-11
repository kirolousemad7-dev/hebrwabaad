<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Models\ContentMedia;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Services\ContentMediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentMediaController extends Controller
{
    public function store(Request $request, ContentMediaService $media): JsonResponse
    {
        $this->authorize('create', ContentMedia::class);

        $request->validate([
            'file' => ['required', 'file'],
            'collection' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $stored = $media->store(
                $request->user(),
                $request->file('file'),
                (string) $request->input('collection', 'gallery'),
            );
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success([
            'id' => $stored->id,
            'url' => $stored->url(),
            'thumb_url' => $stored->url('thumb'),
            'mime_type' => $stored->mime_type,
            'size' => $stored->size,
            'width' => $stored->width,
            'height' => $stored->height,
        ], 201);
    }

    public function show(Request $request, string $uuid): StreamedResponse|JsonResponse
    {
        $media = ContentMedia::query()->findOrFail($uuid);
        $media->load('attachable');
        if ($media->attachable instanceof SupplierPortfolioItem || $media->attachable instanceof SupplierProduct) {
            $media->attachable->loadMissing('supplier');
        }
        $this->authorize('view', $media);

        $variant = $request->query('variant');
        $path = $media->storagePath(is_string($variant) ? $variant : null);
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($path)) {
            return ApiResponse::error('Not found.', 404);
        }

        return $disk->response($path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => $media->isPublishedParent() ? 'public, max-age=86400' : 'private, no-store',
        ]);
    }
}
