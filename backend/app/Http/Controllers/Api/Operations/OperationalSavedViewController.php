<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\OperationalSavedView;
use App\Services\Operations\OperationalSavedViewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalSavedViewController extends Controller
{
    public function __construct(
        private readonly OperationalSavedViewService $views,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OperationalSavedView::class);

        $viewType = $request->query('view_type');

        return ApiResponse::success([
            'items' => $this->views->listFor(
                $request->user(),
                is_string($viewType) ? $viewType : null,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OperationalSavedView::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'view_type' => ['nullable', 'string', 'in:work,printing,projects,command_center'],
            'filters' => ['required', 'array'],
            'sort' => ['nullable', 'array'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);

        $view = $this->views->create($request->user(), $data);

        return ApiResponse::success($this->views->serialize($view), 201);
    }

    public function show(OperationalSavedView $savedView): JsonResponse
    {
        $this->authorize('view', $savedView);

        return ApiResponse::success($this->views->serialize($savedView));
    }

    public function update(Request $request, OperationalSavedView $savedView): JsonResponse
    {
        $this->authorize('update', $savedView);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'view_type' => ['sometimes', 'string', 'in:work,printing,projects,command_center'],
            'filters' => ['sometimes', 'array'],
            'sort' => ['nullable', 'array'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);

        $view = $this->views->update($savedView, $data);

        return ApiResponse::success($this->views->serialize($view));
    }

    public function destroy(OperationalSavedView $savedView): JsonResponse
    {
        $this->authorize('delete', $savedView);

        $this->views->delete($savedView);

        return ApiResponse::success(['deleted' => true]);
    }

    public function pin(Request $request, OperationalSavedView $savedView): JsonResponse
    {
        $this->authorize('update', $savedView);

        $data = $request->validate([
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $view = $this->views->pin($savedView, (bool) ($data['is_pinned'] ?? true));

        return ApiResponse::success($this->views->serialize($view));
    }
}
