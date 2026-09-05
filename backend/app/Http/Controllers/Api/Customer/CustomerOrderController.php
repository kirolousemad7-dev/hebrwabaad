<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreCustomerPackageOrderRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orders->forCustomer($request->user());

        return ApiResponse::success(
            CustomerOrderResource::collection($orders)->resolve($request)
        );
    }

    public function storePackage(StoreCustomerPackageOrderRequest $request): JsonResponse
    {
        $this->authorize('createOwned', Order::class);

        $result = $this->orders->createOrReusePackageOrder(
            $request->user(),
            $request->validated('package_slug'),
            $request->validated('package_tier_slug'),
        );

        $payload = CustomerOrderResource::make($result['order'])->resolve($request);
        $payload['reused'] = $result['reused'];

        return ApiResponse::success($payload, $result['reused'] ? 200 : 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('viewOwned', $order);

        $order = $this->orders->load($order)->load('latestPayment');

        return ApiResponse::success(
            CustomerOrderResource::make($order)->resolve($request)
        );
    }
}
