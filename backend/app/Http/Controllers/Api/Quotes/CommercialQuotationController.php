<?php

namespace App\Http\Controllers\Api\Quotes;

use App\Enums\PrintingPaymentPolicy;
use App\Enums\QuotationLineCategory;
use App\Http\Controllers\Controller;
use App\Models\CommercialQuotation;
use App\Services\Quotes\CommercialQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CommercialQuotationController extends Controller
{
    public function __construct(private readonly CommercialQuotationService $quotations) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->quotations->paginateForStaff($request->user(), $request->query());

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

    public function show(Request $request, CommercialQuotation $commercialQuotation): JsonResponse
    {
        $this->quotations->assertCanManage($request->user());

        return ApiResponse::success($this->quotations->staffPayload($commercialQuotation));
    }

    public function update(Request $request, CommercialQuotation $commercialQuotation): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'execution_duration' => ['nullable', 'string', 'max:120'],
            'revision_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_policy' => ['nullable', 'string', Rule::in(PrintingPaymentPolicy::values())],
            'deposit_required' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'rental_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'terms' => ['nullable', 'string', 'max:20000'],
            'delivery_terms' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:500'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.category' => ['nullable', 'string', Rule::in(QuotationLineCategory::values())],
            'items.*.meta' => ['nullable', 'array'],
        ]);

        return ApiResponse::success(
            $this->quotations->updateDraft($request->user(), $commercialQuotation, $data)
        );
    }

    public function send(Request $request, CommercialQuotation $commercialQuotation): JsonResponse
    {
        $result = $this->quotations->send($request->user(), $commercialQuotation);

        return ApiResponse::success([
            'quotation' => $result['quotation'],
            'public_token' => $result['public_token'],
            'public_url' => '/cq/'.$result['public_token'],
        ]);
    }

    public function revise(Request $request, CommercialQuotation $commercialQuotation): JsonResponse
    {
        return ApiResponse::success(
            $this->quotations->revise($request->user(), $commercialQuotation),
            201,
        );
    }

    public function preview(Request $request, CommercialQuotation $commercialQuotation): JsonResponse
    {
        $this->quotations->assertCanManage($request->user());
        $loaded = $this->quotations->load($commercialQuotation, includeInternal: false);

        return ApiResponse::success($this->quotations->publicPayload($loaded));
    }

    public function pdf(Request $request, CommercialQuotation $commercialQuotation): Response
    {
        return $this->quotations->pdfResponseForStaff($request->user(), $commercialQuotation);
    }
}
