<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\PrintingRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\PrintingRequest;
use App\Services\Operations\PrintingAssignmentService;
use App\Services\Operations\PrintingOperationsService;
use App\Services\Operations\PrintingStatusTransitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrintingOperationsController extends Controller
{
    public function __construct(
        private readonly PrintingOperationsService $printing,
        private readonly PrintingStatusTransitionService $transitions,
        private readonly PrintingAssignmentService $assignments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PrintingRequest::class);

        $data = $this->printing->list($request->user(), [
            'category' => $request->query('category'),
            'status' => $request->query('status'),
            'approaching_days' => $request->query('approaching_days', 2),
            'q' => $request->query('q'),
        ]);

        return ApiResponse::success($data);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PrintingRequest::class);

        $days = (int) $request->query('approaching_days', 2);

        return ApiResponse::success($this->printing->summary($request->user(), $days));
    }

    public function board(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PrintingRequest::class);

        $days = (int) $request->query('approaching_days', 2);

        return ApiResponse::success($this->printing->board($request->user(), $days));
    }

    public function updateStatus(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('manageLifecycle', $printingRequest);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(PrintingRequestStatus::values())],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $updated = $this->transitions->transition(
            $request->user(),
            $printingRequest,
            $data['status'],
            $data['note'] ?? null,
        );

        return ApiResponse::success($this->printing->serializeItem($request->user(), $updated));
    }

    public function assign(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('manageLifecycle', $printingRequest);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $updated = $this->assignments->assign(
            $request->user(),
            $printingRequest,
            array_key_exists('assigned_to', $data) ? $data['assigned_to'] : null,
            array_key_exists('assigned_department_id', $data) ? $data['assigned_department_id'] : null,
            array_key_exists('assigned_to', $data),
            array_key_exists('assigned_department_id', $data),
        );

        return ApiResponse::success($this->printing->serializeItem($request->user(), $updated));
    }

    public function history(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('view', $printingRequest);

        return ApiResponse::success([
            'items' => $this->printing->history($request->user(), $printingRequest),
        ]);
    }

    public function updateDelivery(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('manageLifecycle', $printingRequest);

        $status = $printingRequest->status instanceof PrintingRequestStatus
            ? $printingRequest->status
            : PrintingRequestStatus::from((string) $printingRequest->status);

        if ($status !== PrintingRequestStatus::ReadyForDelivery) {
            throw ValidationException::withMessages([
                'delivery' => ['Delivery details can only be updated when the request is ready for delivery.'],
            ]);
        }

        $data = $request->validate([
            'delivery_method' => ['nullable', 'string', 'max:40'],
            'delivery_notes' => ['nullable', 'string', 'max:5000'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'delivered_at' => ['nullable', 'date'],
        ]);

        $printingRequest->update([
            'delivery_method' => array_key_exists('delivery_method', $data)
                ? $data['delivery_method']
                : $printingRequest->delivery_method,
            'delivery_notes' => array_key_exists('delivery_notes', $data)
                ? $data['delivery_notes']
                : $printingRequest->delivery_notes,
            'received_by' => array_key_exists('received_by', $data)
                ? $data['received_by']
                : $printingRequest->received_by,
            'delivered_at' => array_key_exists('delivered_at', $data)
                ? $data['delivered_at']
                : $printingRequest->delivered_at,
        ]);

        return ApiResponse::success($this->printing->serializeItem(
            $request->user(),
            $printingRequest->fresh() ?? $printingRequest,
        ));
    }
}
