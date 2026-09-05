<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreCustomerPaymentRequest;
use App\Http\Requests\Payments\SubmitManualTransferRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('createOwned', Payment::class);

        $items = Payment::query()
            ->where('customer_id', $request->user()->id)
            ->with($this->payments->eagerLoad())
            ->latest('id')
            ->get();

        return ApiResponse::success(
            PaymentResource::collection($items)->resolve($request)
        );
    }

    public function settings(): JsonResponse
    {
        $this->authorize('createOwned', Payment::class);

        return ApiResponse::success($this->payments->customerSettings());
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('viewOwned', $payment);

        return ApiResponse::success(
            PaymentResource::make($this->payments->load($payment))->resolve($request)
        );
    }

    public function store(StoreCustomerPaymentRequest $request): JsonResponse
    {
        $this->authorize('createOwned', Payment::class);

        $order = Order::query()->findOrFail((int) $request->validated('order_id'));
        $this->authorize('viewOwned', $order);

        $method = PaymentMethod::from($request->validated('method'));

        try {
            $result = $this->payments->createForCustomer($request->user(), $order, $method);
        } catch (HttpException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return ApiResponse::success($this->paymentResponse($request, $result), 201);
    }

    public function card(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('viewOwned', $payment);

        try {
            $result = $this->payments->startCardForCustomer($request->user(), $payment);
        } catch (HttpException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return ApiResponse::success($this->paymentResponse($request, $result));
    }

    public function manualTransfer(SubmitManualTransferRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('viewOwned', $payment);

        $payment = $this->payments->submitManualTransfer(
            $request->user(),
            $payment,
            $request->validated('reference_number'),
            $request->validated('payer_name'),
            $request->validated('notes'),
        );

        return ApiResponse::success(
            PaymentResource::make($payment)->resolve($request)
        );
    }

    /**
     * @param  array{payment: Payment, checkout_url: string|null}  $result
     * @return array<string, mixed>
     */
    private function paymentResponse(Request $request, array $result): array
    {
        $payload = PaymentResource::make($result['payment'])->resolve($request);
        $payload['checkout_url'] = $result['checkout_url'];

        return $payload;
    }
}
