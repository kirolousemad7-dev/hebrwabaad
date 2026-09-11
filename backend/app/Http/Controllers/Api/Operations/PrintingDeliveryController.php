<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\PrintingDeliveryMethod;
use App\Http\Controllers\Controller;
use App\Models\PrintingDelivery;
use App\Models\PrintingRequest;
use App\Services\Printing\PrintingDeliveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrintingDeliveryController extends Controller
{
    public function __construct(private readonly PrintingDeliveryService $deliveries) {}

    public function store(Request $request, PrintingRequest $printing_request): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(PrintingDeliveryMethod::values())],
            'provider' => ['nullable', 'string', 'max:40'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_window' => ['nullable', 'string', 'max:120'],
        ]);

        $delivery = $this->deliveries->createForRequest($request->user(), $printing_request, $data);

        return ApiResponse::success($this->deliveries->staffPayload($delivery), 201);
    }

    public function markDelivered(Request $request, PrintingDelivery $printing_delivery): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'proof_file_id' => ['nullable', 'integer', 'exists:files,id'],
            'received_by' => ['nullable', 'string', 'max:255'],
        ]);

        $delivery = $this->deliveries->markDelivered(
            $request->user(),
            $printing_delivery,
            $data,
        );

        return ApiResponse::success($this->deliveries->staffPayload($delivery));
    }
}
