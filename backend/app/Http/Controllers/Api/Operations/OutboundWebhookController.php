<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Http\Controllers\Controller;
use App\Models\OutboundWebhook;
use App\Models\WebhookDelivery;
use App\Services\Operations\OutboundWebhookService;
use App\Services\Operations\WebhookDispatcher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutboundWebhookController extends Controller
{
    public function __construct(
        private readonly OutboundWebhookService $webhooks,
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $items = OutboundWebhook::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (OutboundWebhook $row) => $this->webhooks->serialize($row))
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WorkflowTrigger::values())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $result = $this->webhooks->create($request->user(), $data);

        return ApiResponse::success(
            $this->webhooks->serialize($result['webhook'], $result['secret']),
            201,
        );
    }

    public function show(Request $request, OutboundWebhook $webhook): JsonResponse
    {
        $this->assertCanManage($request);

        return ApiResponse::success($this->webhooks->serialize($webhook));
    }

    public function update(Request $request, OutboundWebhook $webhook): JsonResponse
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'max:2048'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WorkflowTrigger::values())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->webhooks->update($request->user(), $webhook, $data);

        return ApiResponse::success($this->webhooks->serialize($updated));
    }

    public function destroy(Request $request, OutboundWebhook $webhook): JsonResponse
    {
        $this->assertCanManage($request);
        $this->webhooks->delete($request->user(), $webhook);

        return ApiResponse::success(['deleted' => true]);
    }

    public function test(Request $request, OutboundWebhook $webhook): JsonResponse
    {
        $this->assertCanManage($request);

        $result = $this->webhooks->queueTestDelivery($request->user(), $webhook);
        // Process immediately so test feedback is useful.
        $this->dispatcher->attempt((int) $result['delivery']->id);
        $delivery = $result['delivery']->fresh() ?? $result['delivery'];

        return ApiResponse::success([
            'delivery' => $this->webhooks->serializeDelivery($delivery),
        ]);
    }

    public function deliveries(Request $request, OutboundWebhook $webhook): JsonResponse
    {
        $this->assertCanManage($request);

        $items = WebhookDelivery::query()
            ->where('outbound_webhook_id', $webhook->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WebhookDelivery $row) => $this->webhooks->serializeDelivery($row))
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
