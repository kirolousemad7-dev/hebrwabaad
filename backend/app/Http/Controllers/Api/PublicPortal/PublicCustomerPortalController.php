<?php

namespace App\Http\Controllers\Api\PublicPortal;

use App\Http\Controllers\Controller;
use App\Services\Customer\CustomerCommunicationService;
use App\Services\Customer\CustomerPortalService;
use App\Services\Printing\PrintingCustomerApprovalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCustomerPortalController extends Controller
{
    public function __construct(
        private readonly CustomerPortalService $portal,
        private readonly CustomerCommunicationService $communications,
        private readonly PrintingCustomerApprovalService $approvals,
    ) {}

    public function show(string $token): JsonResponse
    {
        $access = $this->portal->findByToken($token);

        return ApiResponse::success($this->portal->portalPayload($access->customer, $token));
    }

    public function quotations(string $token): JsonResponse
    {
        $access = $this->portal->findByToken($token);

        return ApiResponse::success([
            'items' => $this->portal->quotationsPayload($access->customer),
        ]);
    }

    public function payments(string $token): JsonResponse
    {
        $access = $this->portal->findByToken($token);

        return ApiResponse::success($this->portal->paymentsPayload($access->customer));
    }

    public function printing(string $token): JsonResponse
    {
        $access = $this->portal->findByToken($token);

        return ApiResponse::success([
            'items' => $this->portal->printingPayload($access->customer),
        ]);
    }

    public function approvals(string $token): JsonResponse
    {
        $access = $this->portal->findByToken($token);

        return ApiResponse::success([
            'items' => $this->portal->approvalsPayload($access->customer),
        ]);
    }

    public function decideApproval(Request $request, string $token, int $id): JsonResponse
    {
        $access = $this->portal->findByToken($token);

        $data = $request->validate([
            'decision' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $approval = $this->portal->decideApproval(
            $access,
            $id,
            $data['decision'],
            $data['notes'] ?? null,
        );

        return ApiResponse::success($this->approvals->publicPayload($approval));
    }

    public function magicLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $payload = $this->communications->requestPortalMagicLink(
            $data['email'],
            fn ($customer) => $this->portal->createAccessForCustomer($customer),
        );

        return ApiResponse::success($payload);
    }

    public function quotationPdf(string $token, int $id): mixed
    {
        $access = $this->portal->findByToken($token);

        return $this->portal->quotationPdfForPortal($access, $id);
    }

    public function paymentReceipt(string $token, int $id): JsonResponse
    {
        $access = $this->portal->findByToken($token);
        $payload = $this->portal->paymentReceiptForPortal($access, $id);

        return ApiResponse::success($payload);
    }
}
