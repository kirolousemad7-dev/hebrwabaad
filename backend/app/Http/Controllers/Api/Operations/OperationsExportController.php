<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsExportController extends Controller
{
    public function __construct(
        private readonly OperationsExportService $exports,
    ) {}

    public function work(Request $request): StreamedResponse
    {
        return $this->exports->workCsv($request->user(), $request->query());
    }

    public function printing(Request $request): StreamedResponse
    {
        return $this->exports->printingCsv($request->user(), $request->query());
    }

    public function printingQuotations(Request $request): StreamedResponse
    {
        return $this->exports->printingQuotationsCsv($request->user());
    }

    public function sla(Request $request): StreamedResponse
    {
        return $this->exports->slaCsv($request->user());
    }
}
