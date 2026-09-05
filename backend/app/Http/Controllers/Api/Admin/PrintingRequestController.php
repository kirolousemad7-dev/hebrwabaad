<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Printing\ProvidePrintingQuoteRequest;
use App\Http\Requests\Printing\RequestPrintingQuoteRequest;
use App\Http\Requests\Printing\UpdatePrintingRequestPricingRequest;
use App\Http\Resources\PrintingRequestResource;
use App\Models\PrintingRequest;
use App\Support\ApiResponse;
use App\Support\PrintingRequestFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PrintingRequest::class);

        $status = $request->query('status');
        $pricingType = $request->query('pricing_type');
        $search = $request->query('q');
        $from = $request->query('from');
        $to = $request->query('to');

        $printingRequests = PrintingRequest::query()
            ->with(['user', 'quotedBy'])
            ->when(
                is_string($status) && in_array($status, PrintingRequestStatus::values(), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                is_string($pricingType) && in_array($pricingType, PrintingPricingType::values(), true),
                fn ($query) => $query->where('pricing_type', $pricingType),
            )
            ->when(
                is_string($search) && trim($search) !== '',
                function ($query) use ($search): void {
                    $term = '%'.trim($search).'%';
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('product_name', 'like', $term)
                            ->orWhere('product_slug', 'like', $term)
                            ->orWhereHas('user', function ($userQuery) use ($term): void {
                                $userQuery->where('name', 'like', $term)
                                    ->orWhere('email', 'like', $term);
                            });
                    });
                },
            )
            ->when(
                is_string($from) && $from !== '',
                fn ($query) => $query->whereDate('created_at', '>=', $from),
            )
            ->when(
                is_string($to) && $to !== '',
                fn ($query) => $query->whereDate('created_at', '<=', $to),
            )
            ->latest()
            ->get();

        return ApiResponse::success(
            PrintingRequestResource::collection($printingRequests)->resolve($request)
        );
    }

    public function show(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('view', $printingRequest);
        $printingRequest->load(['user', 'quotedBy']);

        return ApiResponse::success(
            PrintingRequestResource::make($printingRequest)->resolve($request)
        );
    }

    public function file(Request $request, PrintingRequest $printingRequest): StreamedResponse
    {
        $this->authorize('download', $printingRequest);

        return PrintingRequestFile::download($printingRequest);
    }

    public function setEstimatedPrice(UpdatePrintingRequestPricingRequest $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('price', $printingRequest);

        DB::transaction(function () use ($request, $printingRequest): void {
            $printingRequest->update([
                'pricing_type' => PrintingPricingType::Estimated,
                'estimated_price' => $request->validated('estimated_price'),
                'pricing_notes' => $request->validated('pricing_notes'),
            ]);
        });

        return ApiResponse::success(
            PrintingRequestResource::make($printingRequest->load(['user', 'quotedBy']))->resolve($request)
        );
    }

    public function requestQuote(RequestPrintingQuoteRequest $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('price', $printingRequest);

        DB::transaction(function () use ($request, $printingRequest): void {
            $notes = $request->validated('pricing_notes');

            $printingRequest->update([
                'pricing_type' => PrintingPricingType::QuoteRequired,
                'pricing_notes' => $notes ?: 'هذا الطلب يحتاج إلى عرض سعر مخصص.',
            ]);
        });

        return ApiResponse::success(
            PrintingRequestResource::make($printingRequest->load(['user', 'quotedBy']))->resolve($request)
        );
    }

    public function provideQuote(ProvidePrintingQuoteRequest $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->authorize('price', $printingRequest);

        DB::transaction(function () use ($request, $printingRequest): void {
            $printingRequest->update([
                'pricing_type' => PrintingPricingType::QuoteReady,
                'quoted_price' => $request->validated('quoted_price'),
                'pricing_notes' => $request->validated('pricing_notes'),
                'quoted_at' => now(),
                'quoted_by' => $request->user()->id,
            ]);
        });

        return ApiResponse::success(
            PrintingRequestResource::make($printingRequest->load(['user', 'quotedBy']))->resolve($request)
        );
    }
}
