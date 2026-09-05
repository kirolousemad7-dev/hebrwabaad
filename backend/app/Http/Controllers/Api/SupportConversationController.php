<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreMessageRequest;
use App\Http\Requests\Support\UpdateConversationStatusRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $page = $this->conversations->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => ConversationResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return ApiResponse::success($this->detail($request, $conversation));
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('reply', $conversation);

        $conversation = $this->conversations->addMessage(
            $request->user(),
            $conversation,
            $request->validated('message'),
        );

        return ApiResponse::success($this->detail($request, $conversation));
    }

    public function updateStatus(UpdateConversationStatusRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('updateStatus', $conversation);

        $conversation = $this->conversations->transition(
            $request->user(),
            $conversation,
            ConversationStatus::from($request->validated('status')),
        );

        return ApiResponse::success($this->detail($request, $conversation));
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Request $request, Conversation $conversation): array
    {
        $loaded = $this->conversations->load($conversation);
        $page = $this->conversations->paginateMessages($loaded, $request->query());

        $data = ConversationResource::make($loaded)->resolve($request);
        $data['messages'] = MessageResource::collection($page->items())->resolve($request);
        $data['messages_meta'] = [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];

        return $data;
    }
}
