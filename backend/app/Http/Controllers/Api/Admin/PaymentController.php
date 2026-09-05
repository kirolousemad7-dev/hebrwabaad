<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RejectPaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentSettingsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $page = $this->payments->paginateForOwner($request->query());

        $items = collect($page->items())
            ->map(fn (Payment $payment) => (new PaymentResource($payment, true))->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success([
            'items' => $items,
            'summary' => $this->payments->revenueSummary(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function revenue(): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        return ApiResponse::success($this->payments->revenueSummary());
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return ApiResponse::success(
            (new PaymentResource($this->payments->load($payment), true))->resolve($request)
        );
    }

    public function verify(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        $payment = $this->payments->approve($request->user(), $payment);

        return ApiResponse::success(
            (new PaymentResource($payment, true))->resolve($request)
        );
    }

    public function reject(RejectPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        $payment = $this->payments->reject(
            $request->user(),
            $payment,
            $request->validated('reason'),
        );

        return ApiResponse::success(
            (new PaymentResource($payment, true))->resolve($request)
        );
    }

    public function settings(): JsonResponse
    {
        $this->authorize('manageSettings', Payment::class);

        return ApiResponse::success($this->payments->ownerSettings());
    }

    public function updateSettings(UpdatePaymentSettingsRequest $request): JsonResponse
    {
        $this->authorize('manageSettings', Payment::class);

        return ApiResponse::success($this->payments->updateSettings($request->validated()));
    }
}
