<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Printing\StorePrintingCustomerApprovalRequest;
use App\Http\Requests\Printing\StorePrintingQuotationPaymentRequest;
use App\Http\Requests\Printing\StorePrintingQuotationRequest;
use App\Http\Requests\Printing\UpdatePrintingQuotationRequest;
use App\Models\Payment;
use App\Models\PrintingQuotation;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Printing\PrintingCustomerApprovalService;
use App\Services\Printing\PrintingExecutionEligibilityService;
use App\Services\Printing\PrintingQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PrintingQuotationController extends Controller
{
    public function __construct(
        private readonly PrintingQuotationService $quotations,
        private readonly PrintingExecutionEligibilityService $eligibility,
        private readonly PrintingCustomerApprovalService $approvals,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->quotations->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => collect($page->items())->map(
                fn (PrintingQuotation $quotation): array => $this->staffPayload($quotation, $request->user())
            )->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StorePrintingQuotationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $printingRequest = PrintingRequest::query()->findOrFail($data['printing_request_id']);
        $result = $this->quotations->create($request->user(), $printingRequest, $data);

        return ApiResponse::success($this->staffPayload($result['quotation'], $request->user()), 201);
    }

    public function show(Request $request, PrintingQuotation $printingQuotation): JsonResponse
    {
        $this->quotations->assertCanManage($request->user());

        return ApiResponse::success($this->staffPayload($this->quotations->load($printingQuotation), $request->user()));
    }

    public function update(UpdatePrintingQuotationRequest $request, PrintingQuotation $printingQuotation): JsonResponse
    {
        $quotation = $this->quotations->updateDraft($request->user(), $printingQuotation, $request->validated());

        return ApiResponse::success($this->staffPayload($quotation, $request->user()));
    }

    public function send(Request $request, PrintingQuotation $printingQuotation): JsonResponse
    {
        $result = $this->quotations->send($request->user(), $printingQuotation);

        return ApiResponse::success([
            ...$this->staffPayload($result['quotation'], $request->user()),
            'public_token' => $result['public_token'],
            'public_path' => '/q/'.$result['public_token'],
            'public_url' => url('/q/'.$result['public_token']),
        ]);
    }

    public function revise(Request $request, PrintingQuotation $printingQuotation): JsonResponse
    {
        $result = $this->quotations->revise($request->user(), $printingQuotation);

        return ApiResponse::success($this->staffPayload($result['quotation'], $request->user()), 201);
    }

    public function pdf(Request $request, PrintingQuotation $printingQuotation): Response
    {
        return $this->quotations->pdfResponse($request->user(), $printingQuotation);
    }

    public function eligibility(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $this->quotations->assertCanManage($request->user());
        $check = $this->eligibility->eligible($printingRequest);

        return ApiResponse::success([
            'eligible' => $check['eligible'],
            'reasons' => $check['reasons'],
            'payment_summary' => $check['payment_summary'],
            'quotation_id' => $check['quotation']?->id,
            'quotation_reference' => $check['quotation']?->reference,
        ]);
    }

    public function storePayment(
        StorePrintingQuotationPaymentRequest $request,
        PrintingQuotation $printingQuotation,
    ): JsonResponse {
        try {
            $result = $this->payments->createForPrintingQuotation(
                $request->user(),
                $printingQuotation,
                $request->validated(),
            );
        } catch (HttpException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getStatusCode());
        }

        return ApiResponse::success([
            'payment' => $result['payment'],
            'checkout_url' => $result['checkout_url'],
            'quotation' => $this->staffPayload(
                $this->quotations->load($printingQuotation->fresh() ?? $printingQuotation),
                $request->user(),
            ),
        ], 201);
    }

    public function timeline(Request $request, PrintingQuotation $printingQuotation): JsonResponse
    {
        return ApiResponse::success([
            'items' => $this->quotations->staffTimeline($request->user(), $printingQuotation),
        ]);
    }

    public function paymentReceipt(
        Request $request,
        PrintingQuotation $printingQuotation,
        Payment $payment,
    ): Response|JsonResponse {
        return $this->quotations->paymentReceiptResponse($request->user(), $printingQuotation, $payment);
    }

    public function storeApproval(
        StorePrintingCustomerApprovalRequest $request,
        PrintingRequest $printingRequest,
    ): JsonResponse {
        $result = $this->approvals->create($request->user(), $printingRequest, $request->validated());

        return ApiResponse::success([
            'id' => $result['approval']->id,
            'printing_request_id' => $result['approval']->printing_request_id,
            'type' => $result['approval']->type instanceof \BackedEnum
                ? $result['approval']->type->value
                : $result['approval']->type,
            'status' => $result['approval']->status instanceof \BackedEnum
                ? $result['approval']->status->value
                : $result['approval']->status,
            'title' => $result['approval']->title,
            'notes' => $result['approval']->notes,
            'public_token' => $result['public_token'],
        ], 201);
    }

    public function listApprovals(Request $request, PrintingRequest $printingRequest): JsonResponse
    {
        $items = $this->approvals->listForRequest($request->user(), $printingRequest)->map(function ($approval): array {
            return [
                'id' => $approval->id,
                'type' => $approval->type instanceof \BackedEnum ? $approval->type->value : $approval->type,
                'status' => $approval->status instanceof \BackedEnum ? $approval->status->value : $approval->status,
                'title' => $approval->title,
                'decided_at' => $approval->decided_at?->toIso8601String(),
                'notes' => $approval->notes,
            ];
        })->values()->all();

        return ApiResponse::success(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(PrintingQuotation $quotation, User $actor): array
    {
        $paymentSummary = $this->quotations->paymentSummary($quotation);
        $payments = $quotation->relationLoaded('payments') ? $quotation->payments : null;

        // Quote operators need payment amounts to collect; revenue-only roles use canViewPrintingRevenue.
        $canSeePaymentAmounts = $actor->role instanceof UserRole
            && ($actor->role->canViewPrintingRevenue() || $actor->role->canReviewPrintingRequests());

        if (! $canSeePaymentAmounts) {
            $paymentSummary = [
                'paid' => null,
                'remaining' => null,
                'total' => null,
                'deposit_required' => null,
                'payment_policy' => $paymentSummary['payment_policy'],
                'requirement_met' => $paymentSummary['requirement_met'],
            ];

            if ($payments !== null) {
                $payments = $payments->map(function (Payment $payment): array {
                    return [
                        'id' => $payment->id,
                        'status' => $payment->status instanceof \BackedEnum
                            ? $payment->status->value
                            : $payment->status,
                        'payment_method' => $payment->payment_method instanceof \BackedEnum
                            ? $payment->payment_method->value
                            : $payment->payment_method,
                        'currency' => $payment->currency,
                        'amount' => null,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                        'created_at' => $payment->created_at?->toIso8601String(),
                    ];
                })->values();
            }
        }

        return [
            'id' => $quotation->id,
            'reference' => $quotation->reference,
            'revision' => $quotation->revision,
            'printing_request_id' => $quotation->printing_request_id,
            'customer_id' => $quotation->customer_id,
            'created_by' => $quotation->created_by,
            'status' => $quotation->status instanceof \BackedEnum ? $quotation->status->value : $quotation->status,
            'currency' => $quotation->currency,
            'subtotal' => $quotation->subtotal,
            'tax_amount' => $quotation->tax_amount,
            'discount_amount' => $quotation->discount_amount,
            'total' => $quotation->total,
            'deposit_required' => $quotation->deposit_required,
            'payment_policy' => $quotation->payment_policy instanceof \BackedEnum
                ? $quotation->payment_policy->value
                : $quotation->payment_policy,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'public_token_hint' => $quotation->public_token_hint,
            'token_revoked_at' => $quotation->token_revoked_at?->toIso8601String(),
            'sent_at' => $quotation->sent_at?->toIso8601String(),
            'viewed_at' => $quotation->viewed_at?->toIso8601String(),
            'accepted_at' => $quotation->accepted_at?->toIso8601String(),
            'rejected_at' => $quotation->rejected_at?->toIso8601String(),
            'expired_at' => $quotation->expired_at?->toIso8601String(),
            'supersedes_id' => $quotation->supersedes_id,
            'snapshot' => $quotation->snapshot,
            'tracking_token_hint' => $quotation->tracking_token_hint,
            'payment_summary' => $paymentSummary,
            'customer' => $quotation->relationLoaded('customer') ? $quotation->customer : null,
            'creator' => $quotation->relationLoaded('creator') ? $quotation->creator : null,
            'printing_request' => $quotation->relationLoaded('printingRequest') ? $quotation->printingRequest : null,
            'payments' => $payments,
        ];
    }
}
