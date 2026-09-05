<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreConversationRequest;
use App\Http\Requests\Support\StoreMessageRequest;
use App\Http\Resources\CustomerConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->conversations->forCustomer($request->user());

        return ApiResponse::success(
            CustomerConversationResource::collection($items)->resolve($request)
        );
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        $conversation = $this->conversations->createForCustomer(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            $this->detail($request, $conversation),
            $conversation->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('viewOwned', $conversation);

        return ApiResponse::success($this->detail($request, $conversation));
    }

    public function storeMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('replyOwned', $conversation);

        $conversation = $this->conversations->addMessage(
            $request->user(),
            $conversation,
            $request->validated('message'),
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

        $data = CustomerConversationResource::make($loaded)->resolve($request);
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
