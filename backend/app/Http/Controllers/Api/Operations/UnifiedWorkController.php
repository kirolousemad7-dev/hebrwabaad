<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Operations\Work\UnifiedWorkActionService;
use App\Services\Operations\Work\UnifiedWorkService;
use App\Support\ApiResponse;
use App\Support\Operations\UnifiedWorkReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UnifiedWorkController extends Controller
{
    public function __construct(
        private readonly UnifiedWorkService $work,
        private readonly UnifiedWorkActionService $actions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanView($request);

        return ApiResponse::success($this->work->list($request->user(), $request->query()));
    }

    public function show(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);

        return ApiResponse::success($this->actions->show($request->user(), $ref));
    }

    public function complete(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);

        return ApiResponse::success($this->actions->complete($request->user(), $ref));
    }

    public function assign(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);
        $validated = $request->validate([
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        return ApiResponse::success($this->actions->assign(
            $request->user(),
            $ref,
            array_map('intval', $validated['assignee_ids']),
        ));
    }

    public function priority(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);
        $validated = $request->validate([
            'priority' => ['required', 'string', 'in:LOW,MEDIUM,HIGH,URGENT,low,medium,high,urgent'],
        ]);

        return ApiResponse::success($this->actions->setPriority(
            $request->user(),
            $ref,
            $validated['priority'],
        ));
    }

    public function status(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,review,completed,cancelled,overdue'],
        ]);

        return ApiResponse::success($this->actions->setStatus(
            $request->user(),
            $ref,
            $validated['status'],
        ));
    }

    public function reschedule(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);
        $validated = $request->validate([
            'due_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success($this->actions->reschedule(
            $request->user(),
            $ref,
            $validated['due_at'] ?? null,
            $validated['starts_at'] ?? null,
        ));
    }

    public function start(Request $request, string $work): JsonResponse
    {
        $this->assertCanView($request);
        $ref = $this->parseRef($work);

        return ApiResponse::success($this->actions->start($request->user(), $ref));
    }

    public function focus(Request $request): JsonResponse
    {
        $this->assertCanView($request);
        $limit = max(1, min((int) $request->query('limit', 5), 20));

        return ApiResponse::success([
            'items' => $this->work->focus($request->user(), $limit),
        ]);
    }

    public function kanban(Request $request): JsonResponse
    {
        $this->assertCanView($request);

        return ApiResponse::success([
            'columns' => $this->work->kanban($request->user(), $request->query()),
        ]);
    }

    private function assertCanView(Request $request): void
    {
        $user = $request->user();
        if (! ($user?->role instanceof UserRole) || ! $user->role->canViewUnifiedWork()) {
            abort(403);
        }
    }

    private function parseRef(string $work): string
    {
        try {
            return UnifiedWorkReference::parse($work)->toString();
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException('Invalid work reference.');
        }
    }
}
