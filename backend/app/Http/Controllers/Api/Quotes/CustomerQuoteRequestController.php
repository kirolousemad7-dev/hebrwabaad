<?php

namespace App\Http\Controllers\Api\Quotes;

use App\Enums\QuoteRequestSource;
use App\Http\Controllers\Controller;
use App\Models\CommercialQuotation;
use App\Models\QuoteRequest;
use App\Services\Quotes\CommercialQuotationService;
use App\Services\Quotes\QuoteRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CustomerQuoteRequestController extends Controller
{
    public function __construct(
        private readonly QuoteRequestService $requests,
        private readonly CommercialQuotationService $quotations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->requests->paginateForCustomer($request->user(), $request->query());

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_type' => ['required', 'string', Rule::in(QuoteRequestSource::values())],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'title' => ['required', 'string', 'max:255'],
            'required_date' => ['nullable', 'date'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'city' => ['nullable', 'string', 'max:120'],
            'customer_notes' => ['nullable', 'string', 'max:10000'],
            'payload' => ['nullable', 'array'],
            'file_ids' => ['nullable', 'array'],
            'file_ids.*' => ['integer', 'exists:files,id'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        if (isset($data['idempotency_key'])) {
            $payload = $data['payload'] ?? [];
            $payload['idempotency_key'] = $data['idempotency_key'];
            $data['payload'] = $payload;
        }

        $created = $this->requests->create($request->user(), $data);

        return ApiResponse::success($created, 201);
    }

    public function show(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        return ApiResponse::success($this->requests->loadForCustomer($request->user(), $quoteRequest));
    }

    public function respond(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'file_ids' => ['nullable', 'array'],
            'file_ids.*' => ['integer', 'exists:files,id'],
        ]);

        return ApiResponse::success(
            $this->requests->customerRespond($request->user(), $quoteRequest, $data)
        );
    }

    public function quotationPdf(Request $request, CommercialQuotation $commercialQuotation): Response
    {
        return $this->quotations->pdfResponseForCustomer($request->user(), $commercialQuotation);
    }
}
