<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\BookOtoShipmentRequest;
use App\Http\Requests\Shipping\QuoteOtoShipmentRequest;
use App\Models\Order;
use App\Services\Shipping\OtoApiException;
use App\Services\Shipping\OtoShipmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OtoShipmentController extends Controller
{
    public function __construct(private readonly OtoShipmentService $shipments) {}

    public function index(Request $request): JsonResponse
    {
        $view = (string) $request->query('view', 'awaiting');
        if (! in_array($view, ['awaiting', 'errors', 'shipped', 'all'], true)) {
            $view = 'awaiting';
        }

        $page = $this->shipments->queue($view);

        return ApiResponse::success([
            'integration' => $this->shipments->integrationStatus(),
            'items' => collect($page->items())->map(fn (Order $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'title' => $order->title,
                'customer_name' => $order->customer?->name,
                'shipment' => $order->otoShipment?->ownerPayload(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'view' => $view,
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success($this->shipments->detail($order));
    }

    public function quote(QuoteOtoShipmentRequest $request, Order $order): JsonResponse
    {
        return $this->respond(fn () => $this->shipments->quote($order, $request->validated())->ownerPayload());
    }

    public function storeOrder(QuoteOtoShipmentRequest $request, Order $order): JsonResponse
    {
        return $this->respond(fn () => $this->shipments->pushOrder($order, $request->validated())->ownerPayload());
    }

    public function storeShipment(BookOtoShipmentRequest $request, Order $order): JsonResponse
    {
        return $this->respond(fn () => $this->shipments->book($order, $request->validated('delivery_option_id'))->ownerPayload());
    }

    public function sync(Order $order): JsonResponse
    {
        return $this->respond(fn () => $this->shipments->sync($order)->ownerPayload());
    }

    public function retry(Order $order): JsonResponse
    {
        return $this->respond(fn () => $this->shipments->retry($order)->ownerPayload());
    }

    public function label(Order $order): JsonResponse
    {
        return $this->respond(fn () => $this->shipments->label($order)->ownerPayload());
    }

    /**
     * @param  callable(): array<string, mixed>  $action
     */
    private function respond(callable $action): JsonResponse
    {
        try {
            return ApiResponse::success($action());
        } catch (ValidationException $exception) {
            return ApiResponse::error(
                $exception->validator->errors()->first() ?: 'بيانات الشحن غير مكتملة.',
                422,
                $exception->errors(),
            );
        } catch (OtoApiException $exception) {
            $status = $exception->status >= 400 && $exception->status <= 599 ? $exception->status : 502;
            if ($status === 401) {
                $status = 502;
            }

            return ApiResponse::error($exception->getMessage(), $status);
        }
    }
}
