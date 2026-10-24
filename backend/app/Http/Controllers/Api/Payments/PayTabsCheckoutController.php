<?php

namespace App\Http\Controllers\Api\Payments;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\OrderPayableResolver;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayTabsCheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderPayableResolver $payables,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $order = Order::query()->findOrFail((int) $validated['order_id']);
        $this->authorize('viewOwned', $order);

        $quote = $this->payables->resolve($order);
        if ($quote === null) {
            throw ValidationException::withMessages([
                'order_id' => ['This order has no payable catalog amount.'],
            ]);
        }

        if (array_key_exists('amount', $validated) && $validated['amount'] !== null) {
            $sent = number_format((float) $validated['amount'], 2, '.', '');
            if ($sent !== $quote->amount) {
                throw ValidationException::withMessages([
                    'amount' => ['المبلغ لا يطابق قيمة الطلب.'],
                ]);
            }
        }

        try {
            $result = $this->payments->createForCustomer($request->user(), $order, PaymentMethod::Card);
        } catch (HttpException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return ApiResponse::success([
            'payment_id' => $result['payment']->id,
            'payment_url' => $result['checkout_url'],
            'status' => $result['payment']->status->value,
        ], 201);
    }

    public function status(Request $request, Payment $payment): JsonResponse
    {
        $cartId = trim((string) $request->query('cart_id', ''));
        $stored = (string) ($payment->checkout_session_id ?? '');

        if ($cartId === '' || $stored === '' || ! hash_equals($stored, $cartId)) {
            return ApiResponse::error('Payment not found.', 404);
        }

        $status = $payment->status;

        return ApiResponse::success([
            'id' => $payment->id,
            'status' => $status->value,
            'amount' => (string) $payment->amount,
            'currency' => (string) $payment->currency,
        ]);
    }
}
