<?php

namespace App\Http\Controllers\Api\Crm;

use App\Enums\CrmQuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmQuotationRequest;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Services\Crm\CrmQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CrmQuotationController extends Controller
{
    public function __construct(private readonly CrmQuotationService $quotations) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->quotations->paginateFor($request->user(), $request->query());

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

    public function store(StoreCrmQuotationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $lead = CrmLead::query()->findOrFail($data['lead_id']);
        $quotation = $this->quotations->create($request->user(), $lead, $data);

        return ApiResponse::success($quotation, 201);
    }

    public function show(Request $request, CrmQuotation $quotation): JsonResponse
    {
        $this->quotations->assertVisible($request->user(), $quotation);

        return ApiResponse::success($this->quotations->load($quotation));
    }

    public function status(Request $request, CrmQuotation $quotation): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(CrmQuotationStatus::values())],
        ]);

        $quotation = $this->quotations->transition(
            $request->user(),
            $quotation,
            CrmQuotationStatus::from($data['status']),
        );

        return ApiResponse::success($quotation);
    }

    public function pdf(Request $request, CrmQuotation $quotation): Response
    {
        return $this->quotations->pdfResponse($request->user(), $quotation);
    }

    public function approve(Request $request, CrmQuotation $quotation): JsonResponse
    {
        return ApiResponse::success($this->quotations->approve($request->user(), $quotation));
    }

    public function reject(Request $request, CrmQuotation $quotation): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::success(
            $this->quotations->reject($request->user(), $quotation, $data['notes'] ?? null)
        );
    }

    public function send(Request $request, CrmQuotation $quotation): JsonResponse
    {
        return ApiResponse::success($this->quotations->send($request->user(), $quotation));
    }
}
