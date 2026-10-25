<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StorePaymentAccountRequest;
use App\Http\Requests\Payments\UpdatePaymentAccountRequest;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Services\Payments\PaymentAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentAccountController extends Controller
{
    public function __construct(private readonly PaymentAccountService $accounts) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $items = PaymentAccount::query()
            ->latest('id')
            ->get()
            ->map(fn (PaymentAccount $account) => $this->accounts->ownerPayload($account))
            ->values()
            ->all();

        return ApiResponse::success($items);
    }

    public function store(StorePaymentAccountRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $account = $this->accounts->create($request->validated());

        return ApiResponse::success($this->accounts->ownerPayload($account), 201);
    }

    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $account = $this->accounts->update($paymentAccount, $request->validated());

        return ApiResponse::success($this->accounts->ownerPayload($account));
    }

    public function makeDefault(PaymentAccount $paymentAccount): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $account = $this->accounts->makeDefault($paymentAccount);

        return ApiResponse::success($this->accounts->ownerPayload($account));
    }

    public function archive(PaymentAccount $paymentAccount): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $account = $this->accounts->archive($paymentAccount);

        return ApiResponse::success($this->accounts->ownerPayload($account));
    }
}
