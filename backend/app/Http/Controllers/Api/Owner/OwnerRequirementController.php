<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\NeedsDiscovery\UpdateRequirementRequest;
use App\Http\Resources\RequirementResource;
use App\Models\Requirement;
use App\Services\NeedsDiscovery\NeedsDiscoveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerRequirementController extends Controller
{
    public function __construct(private readonly NeedsDiscoveryService $discovery) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->discovery->paginate($request->query());

        return ApiResponse::success([
            'items' => RequirementResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Requirement $requirement): JsonResponse
    {
        $requirement->load(['assignee', 'crmLead', 'catalogService', 'task']);

        return ApiResponse::success(RequirementResource::make($requirement)->resolve($request));
    }

    public function update(UpdateRequirementRequest $request, Requirement $requirement): JsonResponse
    {
        $updated = $this->discovery->update(
            $requirement,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::success(RequirementResource::make($updated)->resolve($request));
    }

    public function downloadAttachment(Request $request, Requirement $requirement, int $index): StreamedResponse
    {
        $attachments = $requirement->attachments ?? [];
        $file = $attachments[$index] ?? null;
        if (! is_array($file) || empty($file['path'])) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($file['path'])) {
            abort(404);
        }

        return $disk->download($file['path'], $file['original_name'] ?? 'attachment');
    }
}
