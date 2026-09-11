<?php

namespace App\Http\Controllers\Api\PublicPrinting;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Printing\PrintingQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicPrintingQuotationController extends Controller
{
    public function __construct(
        private readonly PrintingQuotationService $quotations,
        private readonly PaymentService $payments,
    ) {}

    public function show(string $token): JsonResponse
    {
        $quotation = $this->quotations->findUsableByRawToken($token);
        $quotation = $this->quotations->recordView($quotation);

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }

    public function accept(string $token): JsonResponse
    {
        $result = $this->quotations->acceptByToken($token);

        return ApiResponse::success([
            ...$this->quotations->publicPayload($result['quotation']),
            'tracking_token' => $result['tracking_token'],
        ]);
    }

    public function reject(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $reason = $data['reason'] ?? $data['notes'] ?? null;
        $quotation = $this->quotations->rejectByToken($token, $reason);

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }

    public function checkout(string $token): JsonResponse
    {
        $quotation = $this->quotations->findUsableByRawToken($token);

        try {
            $result = $this->payments->createPublicPrintingCheckout($quotation);
        } catch (HttpException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return ApiResponse::success($result, 201);
    }

    public function paymentStatus(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
        ]);

        return ApiResponse::success(
            $this->payments->publicPrintingPaymentStatus($payment, $data['token'])
        );
    }
}
