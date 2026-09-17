<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Models\QuotationSupplierQuote;
use App\Services\Quotes\QuotationSupplierSourcingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierSourcingController extends Controller
{
    public function __construct(private readonly QuotationSupplierSourcingService $sourcing) {}

    public function index(Request $request): JsonResponse
    {
        $quotes = $this->sourcing->listForSupplier($request->user());

        return ApiResponse::success([
            'items' => $quotes->map(
                fn (QuotationSupplierQuote $quote): array => $this->sourcing->supplierQuotePayload($quote)
            )->values()->all(),
        ]);
    }

    public function show(Request $request, QuotationSupplierQuote $quote): JsonResponse
    {
        $loaded = $this->sourcing->showForSupplier($request->user(), $quote);

        return ApiResponse::success($this->sourcing->supplierQuotePayload($loaded));
    }

    public function respond(Request $request, QuotationSupplierQuote $quote): JsonResponse
    {
        $data = $request->validate([
            'cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        $updated = $this->sourcing->submitSupplierResponse(
            $request->user(),
            $quote,
            $data,
            array_values($files),
        );

        return ApiResponse::success($this->sourcing->supplierQuotePayload($updated));
    }
}
