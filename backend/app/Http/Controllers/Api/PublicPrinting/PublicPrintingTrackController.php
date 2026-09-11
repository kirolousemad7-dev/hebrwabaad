<?php

namespace App\Http\Controllers\Api\PublicPrinting;

use App\Http\Controllers\Controller;
use App\Services\Printing\PrintingQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicPrintingTrackController extends Controller
{
    public function __construct(private readonly PrintingQuotationService $quotations) {}

    public function show(string $token): JsonResponse
    {
        $quotation = $this->quotations->findByTrackingToken($token);
        $this->quotations->recordTrackView($quotation);

        return ApiResponse::success($this->quotations->trackingPayload($quotation));
    }
}
