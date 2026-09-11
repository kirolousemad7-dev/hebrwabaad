<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\ApprovalRequestType;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Operations\ApprovalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
    ) {}

    public function inbox(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $status = $request->query('status');

        return ApiResponse::success([
            'items' => $this->approvals->inbox($request->user(), is_string($status) ? $status : null),
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        return ApiResponse::success([
            'items' => $this->approvals->mine($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ApprovalRequest::class);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(ApprovalRequestType::values())],
            'related_type' => ['required', 'string', 'max:60'],
            'related_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $approval = $this->approvals->request($request->user(), $data);

        return ApiResponse::success($this->approvals->serialize($approval), 201);
    }

    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $this->authorize('decide', $approval);

        $data = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $approval = $this->approvals->approve($request->user(), $approval, $data['decision_notes'] ?? null);

        return ApiResponse::success($this->approvals->serialize($approval));
    }

    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $this->authorize('decide', $approval);

        $data = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $approval = $this->approvals->reject($request->user(), $approval, $data['decision_notes'] ?? null);

        return ApiResponse::success($this->approvals->serialize($approval));
    }
}
