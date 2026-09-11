<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\PrintingRequest;
use App\Services\Customer\CustomerCommunicationTimelineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintingCommunicationController extends Controller
{
    public function __construct(private readonly CustomerCommunicationTimelineService $timeline) {}

    public function index(Request $request, PrintingRequest $printing_request): JsonResponse
    {
        return ApiResponse::success([
            'items' => $this->timeline->forPrintingRequest($request->user(), $printing_request),
        ]);
    }
}
