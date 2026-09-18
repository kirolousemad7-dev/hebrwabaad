<?php

namespace App\Http\Controllers\Api\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\RecordInvoicePaymentRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $page = $this->invoices->paginateForStaff($request->user(), $request->query());

        return ApiResponse::success([
            'items' => collect($page->items())->map(
                fn (Invoice $invoice) => $this->invoices->staffPayload($invoice)
            )->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->user(), $request->validated());

        return ApiResponse::success($this->invoices->staffPayload($invoice), 201);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);
        $invoice = $this->invoices->loadForStaff($request->user(), $invoice);

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->updateDraft($request->user(), $invoice, $request->validated());

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('issue', $invoice);
        $invoice = $this->invoices->issue($request->user(), $invoice);

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);
        $invoice = $this->invoices->send($request->user(), $invoice);

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('cancel', $invoice);
        $invoice = $this->invoices->cancel($request->user(), $invoice);

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('void', $invoice);
        $invoice = $this->invoices->void($request->user(), $invoice);

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function recordPayment(RecordInvoicePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->recordPayment($request->user(), $invoice, $request->validated());

        return ApiResponse::success($this->invoices->staffPayload($invoice));
    }

    public function pdf(Request $request, Invoice $invoice): Response|string
    {
        $this->authorize('view', $invoice);
        $invoice = $this->invoices->loadForStaff($request->user(), $invoice);

        return $this->invoices->renderPdf($invoice, (string) $request->query('format', 'pdf'));
    }
}
