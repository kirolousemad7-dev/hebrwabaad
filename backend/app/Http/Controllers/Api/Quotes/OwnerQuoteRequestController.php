<?php

namespace App\Http\Controllers\Api\Quotes;

use App\Enums\QuoteRequestSource;
use App\Enums\QuoteRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use App\Services\Quotes\CommercialQuotationService;
use App\Services\Quotes\QuoteRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerQuoteRequestController extends Controller
{
    public function __construct(
        private readonly QuoteRequestService $requests,
        private readonly CommercialQuotationService $quotations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->requests->paginateForStaff($request->user(), $request->query());

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'summary' => $this->requests->summaryCounts($request->user()),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->requests->summaryCounts($request->user()));
    }

    public function show(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        return ApiResponse::success($this->requests->loadForStaff($request->user(), $quoteRequest));
    }

    public function startReview(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        return ApiResponse::success($this->requests->startReview($request->user(), $quoteRequest));
    }

    public function assign(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        return ApiResponse::success(
            $this->requests->assign($request->user(), $quoteRequest, (int) $data['assigned_to'])
        );
    }

    public function requestInformation(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        return ApiResponse::success(
            $this->requests->requestInformation($request->user(), $quoteRequest, $data['message'])
        );
    }

    public function cancel(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(
            $this->requests->cancel($request->user(), $quoteRequest, $data['reason'] ?? null)
        );
    }

    public function updateNotes(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        return ApiResponse::success(
            $this->requests->updateInternalNotes($request->user(), $quoteRequest, $data['internal_notes'] ?? null)
        );
    }

    public function createQuotation(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $source = $quoteRequest->source_type instanceof QuoteRequestSource
            ? $quoteRequest->source_type
            : QuoteRequestSource::from((string) $quoteRequest->source_type);

        if ($source->usesPrintingQuotation()) {
            return ApiResponse::error(
                'طلبات الطباعة تُسعَّر عبر عروض الطباعة الحالية.',
                422,
                ['quotation_type' => 'PRINTING', 'printing_request_id' => $quoteRequest->source_id],
            );
        }

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.category' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],
        ]);

        $quotation = $this->quotations->createFromQuoteRequest($request->user(), $quoteRequest, $data);

        return ApiResponse::success($quotation, 201);
    }

    public function update(Request $request, QuoteRequest $quoteRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(QuoteRequestStatus::values())],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if (array_key_exists('internal_notes', $data)) {
            $quoteRequest = $this->requests->updateInternalNotes(
                $request->user(),
                $quoteRequest,
                $data['internal_notes'],
            );
        }

        return ApiResponse::success($quoteRequest);
    }
}
