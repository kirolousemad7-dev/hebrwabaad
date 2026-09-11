<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\InboundWebhookIntegration;
use App\Models\InboundWebhookReceipt;
use App\Services\Operations\InboundWebhooks\InboundWebhookIntegrationService;
use App\Services\Operations\InboundWebhooks\InboundWebhookManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InboundWebhookIntegrationController extends Controller
{
    public function __construct(
        private readonly InboundWebhookIntegrationService $integrations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $items = InboundWebhookIntegration::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (InboundWebhookIntegration $row) => $this->integrations->serialize($row))
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'integration_type' => ['sometimes', 'string', Rule::in(InboundWebhookManager::allowedTypes())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $result = $this->integrations->create($request->user(), $data);

        return ApiResponse::success(
            $this->integrations->serialize($result['integration'], $result['secret']),
            201,
        );
    }

    public function show(Request $request, InboundWebhookIntegration $inboundWebhook): JsonResponse
    {
        $this->assertCanManage($request);

        return ApiResponse::success($this->integrations->serialize($inboundWebhook));
    }

    public function update(Request $request, InboundWebhookIntegration $inboundWebhook): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->integrations->update($request->user(), $inboundWebhook, $data);

        return ApiResponse::success($this->integrations->serialize($updated));
    }

    public function destroy(Request $request, InboundWebhookIntegration $inboundWebhook): JsonResponse
    {
        $this->assertCanManage($request);
        $this->integrations->delete($request->user(), $inboundWebhook);

        return ApiResponse::success(['deleted' => true]);
    }

    public function receipts(Request $request, InboundWebhookIntegration $inboundWebhook): JsonResponse
    {
        $this->assertCanManage($request);

        $items = InboundWebhookReceipt::query()
            ->where('inbound_webhook_integration_id', $inboundWebhook->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (InboundWebhookReceipt $row) => $this->integrations->serializeReceipt($row))
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    private function assertCanManage(Request $request): void
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole) || ! $user->role->canManageIntegrations()) {
            abort(403);
        }
    }
}
