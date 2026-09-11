<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RejectPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRefundRequest;
use App\Http\Requests\Payments\UpdatePaymentSettingsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Services\Payments\PaymentRefundService;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentRefundService $refunds,
    ) {}

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

    public function reconcile(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('verify', $payment);

        try {
            $payment = $this->payments->reconcileWithProvider($request->user(), $payment);
        } catch (HttpException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return ApiResponse::success(
            (new PaymentResource($payment, true))->resolve($request)
        );
    }

    public function refunds(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('refund', $payment);

        $items = array_map(
            fn (PaymentRefund $refund): array => $this->refunds->serialize($refund),
            $this->refunds->listForPayment($request->user(), $payment),
        );

        return ApiResponse::success([
            'items' => $items,
            'refundable_amount' => $this->refunds->refundableAmount($payment),
            'net_paid' => $this->refunds->netPaid($payment),
            'payment_amount' => $payment->amount,
        ]);
    }

    public function storeRefund(StorePaymentRefundRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('refund', $payment);

        $data = $request->validated();
        $result = $this->refunds->requestRefund(
            $request->user(),
            $payment,
            (string) $data['amount'],
            $data['reason'] ?? null,
            (bool) ($data['manual'] ?? false),
        );

        return ApiResponse::success([
            'refund' => $this->refunds->serialize($result['refund']),
            'payment' => (new PaymentResource($this->payments->load($result['payment']), true))->resolve($request),
            'payment_amount' => $result['payment']->amount,
            'net_paid' => $this->refunds->netPaid($result['payment']),
        ], status: 201);
    }

    public function confirmManualRefund(Request $request, PaymentRefund $refund): JsonResponse
    {
        $this->authorize('refund', $refund->payment ?? Payment::query()->findOrFail($refund->payment_id));

        $result = $this->refunds->confirmManual($request->user(), $refund);

        return ApiResponse::success([
            'refund' => $this->refunds->serialize($result['refund']),
            'payment' => (new PaymentResource($this->payments->load($result['payment']), true))->resolve($request),
            'payment_amount' => $result['payment']->amount,
            'net_paid' => $this->refunds->netPaid($result['payment']),
        ]);
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
