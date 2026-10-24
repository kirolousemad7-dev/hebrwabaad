<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PayTabsClient;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayTabsWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PayTabsClient $paytabs,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = (string) ($request->header('Signature') ?? '');
        if (! $this->paytabs->isGenuineCallback($request->getContent(), $signature)) {
            Log::warning('paytabs.callback_signature_rejected');

            return ApiResponse::error('Invalid callback signature.', 401);
        }

        $payload = $request->all();
        unset($payload['payment_info'], $payload['token'], $payload['signature']);
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
