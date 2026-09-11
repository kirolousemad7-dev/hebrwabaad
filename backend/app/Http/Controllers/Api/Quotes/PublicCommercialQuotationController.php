<?php

namespace App\Http\Controllers\Api\Quotes;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Quotes\CommercialQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicCommercialQuotationController extends Controller
{
    public function __construct(
        private readonly CommercialQuotationService $quotations,
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
        ]);

        $quotation = $this->quotations->rejectByToken($token, $data['reason'] ?? null);

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }

    public function requestRevision(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'reason_code' => ['nullable', 'string', 'max:64'],
        ]);

        $quotation = $this->quotations->requestRevisionByToken(
            $token,
            $data['reason'] ?? null,
            $data['reason_code'] ?? null,
        );

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }

    public function checkout(string $token): JsonResponse
    {
        $quotation = $this->quotations->findUsableByRawToken($token);

        try {
            $result = $this->payments->createPublicCommercialCheckout($quotation);
        } catch (HttpException $e) {
            return ApiResponse::error($e->getMessage(), $e->getStatusCode());
        }

        return ApiResponse::success($result);
    }

    public function paymentStatus(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
        ]);

        return ApiResponse::success(
            $this->payments->publicCommercialPaymentStatus($payment, $data['token'])
        );
    }

    public function pdf(string $token): Response
    {
        return $this->quotations->pdfResponseByToken($token);
    }
}
