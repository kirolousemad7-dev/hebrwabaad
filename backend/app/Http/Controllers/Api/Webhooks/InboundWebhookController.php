<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\InboundWebhookIntegration;
use App\Services\Operations\InboundWebhooks\InboundWebhookManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundWebhookController extends Controller
{
    public function __construct(
        private readonly InboundWebhookManager $manager,
    ) {}

    public function handle(Request $request, InboundWebhookIntegration $inboundWebhook): JsonResponse
    {
        $result = $this->manager->handle($inboundWebhook, $request);

        return response()->json($result['body'], $result['status']);
    }
}
