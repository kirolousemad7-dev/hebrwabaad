<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function lookups(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        return ApiResponse::success($this->orders->lookups($request->user()));
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $page = $this->orders->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => OrderResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->create($request->user(), $request->validated());

        return ApiResponse::success(OrderResource::make($order)->resolve($request), 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(
            OrderResource::make($this->orders->load($order))->resolve($request)
        );
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', $order);

        $order = $this->orders->transition(
            $request->user(),
            $order,
            OrderStatus::from($request->validated('status')),
        );

        return ApiResponse::success(OrderResource::make($order)->resolve($request));
    }
}
