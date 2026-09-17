<?php

namespace App\Http\Controllers\Api\Quotes;

use App\Http\Controllers\Controller;
use App\Models\CommercialQuotation;
use App\Models\CommercialQuotationItem;
use App\Models\QuotationSupplierQuote;
use App\Models\Supplier;
use App\Services\Quotes\QuotationSupplierSourcingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotationSupplierSourcingController extends Controller
{
    public function __construct(private readonly QuotationSupplierSourcingService $sourcing) {}

    public function index(Request $request, CommercialQuotation $commercialQuotation): JsonResponse
    {
        $quotes = $this->sourcing->listForQuotation($request->user(), $commercialQuotation);

        return ApiResponse::success([
            'sourcing' => $this->sourcing->sourcingPayload($commercialQuotation),
            'quotes' => $quotes->map(fn (QuotationSupplierQuote $quote): array => $this->sourcing->ownerQuotePayload($quote))->values()->all(),
        ]);
    }

    public function compare(Request $request, CommercialQuotationItem $item): JsonResponse
    {
        return ApiResponse::success([
            'item_id' => $item->id,
            'options' => $this->sourcing->compareForItem($request->user(), $item),
        ]);
    }

    public function requestQuote(Request $request, CommercialQuotation $commercialQuotation, CommercialQuotationItem $item): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $quote = $this->sourcing->requestQuote($request->user(), $commercialQuotation, $item, $data);

        return ApiResponse::success($this->sourcing->ownerQuotePayload($quote), 201);
    }

    public function markUnderReview(Request $request, QuotationSupplierQuote $quote): JsonResponse
    {
        $updated = $this->sourcing->markUnderReview($request->user(), $quote);

        return ApiResponse::success($this->sourcing->ownerQuotePayload($updated));
    }

    public function select(Request $request, QuotationSupplierQuote $quote): JsonResponse
    {
        $updated = $this->sourcing->select($request->user(), $quote);

        return ApiResponse::success($this->sourcing->ownerQuotePayload($updated));
    }

    public function reject(Request $request, QuotationSupplierQuote $quote): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->sourcing->reject($request->user(), $quote, $data['reason'] ?? null);

        return ApiResponse::success($this->sourcing->ownerQuotePayload($updated));
    }

    public function replace(Request $request, QuotationSupplierQuote $quote): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'currency' => ['nullable', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $replacement = $this->sourcing->replace($request->user(), $quote, $data);

        return ApiResponse::success($this->sourcing->ownerQuotePayload($replacement), 201);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $this->sourcing->assertOwnerCanManage($request->user());

        $q = trim((string) $request->query('q', ''));
        $query = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(50);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('display_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return ApiResponse::success([
            'items' => $query->get(['id', 'name', 'display_name', 'slug', 'email'])->map(fn (Supplier $s): array => [
                'id' => $s->id,
                'name' => $s->display_name ?: $s->name,
                'slug' => $s->slug,
                'email' => $s->email,
            ])->values()->all(),
        ]);
    }
}
