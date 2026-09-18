<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->invoices->paginateForCustomer($request->user(), $request->query());

        return ApiResponse::success([
            'items' => collect($page->items())->map(
                fn (Invoice $invoice) => $this->invoices->customerPayload($invoice)
            )->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->loadForCustomer($request->user(), $invoice);

        return ApiResponse::success($this->invoices->customerPayload($invoice));
    }

    public function pdf(Request $request, Invoice $invoice): Response|string
    {
        $invoice = $this->invoices->loadForCustomer($request->user(), $invoice);

        return $this->invoices->renderPdf($invoice, (string) $request->query('format', 'pdf'));
    }
}
