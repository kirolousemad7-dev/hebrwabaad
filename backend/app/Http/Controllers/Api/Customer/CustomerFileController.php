<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\StoreManagedFileRequest;
use App\Http\Resources\ManagedFileResource;
use App\Models\ManagedFile;
use App\Services\FileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerFileController extends Controller
{
    public function __construct(private readonly FileService $files) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->files->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => ManagedFileResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreManagedFileRequest $request): JsonResponse
    {
        $this->authorize('create', ManagedFile::class);

        $file = $this->files->store(
            $request->user(),
            $request->file('file'),
            $request->safe()->only(['project_id', 'order_id', 'task_id']),
        );

        return ApiResponse::success(ManagedFileResource::make($file)->resolve($request), 201);
    }

    public function show(Request $request, ManagedFile $file): JsonResponse
    {
        $this->authorize('view', $file);

        return ApiResponse::success(
            ManagedFileResource::make($file->load($this->files->eagerLoad()))->resolve($request)
        );
    }

    public function download(ManagedFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        return $this->files->download($file);
    }

    public function preview(ManagedFile $file): StreamedResponse
    {
        $this->authorize('download', $file);

        return $this->files->preview($file);
    }
}
