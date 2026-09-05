<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayTabsWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $tranRef = $payload['tran_ref'] ?? null;

        if (! is_string($tranRef) || trim($tranRef) === '') {
            Log::info('paytabs.callback_ignored', ['reason' => 'missing_tran_ref']);

            return ApiResponse::success(['received' => true]);
        }

        try {
            $this->payments->applyPayTabsCallback($payload);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 503) {
                return ApiResponse::error('Payment provider unavailable.', 503);
            }

            throw $exception;
        }

        return ApiResponse::success(['received' => true]);
    }
}
