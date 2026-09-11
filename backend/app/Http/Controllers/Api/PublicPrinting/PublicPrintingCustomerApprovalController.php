<?php

namespace App\Http\Controllers\Api\PublicPrinting;

use App\Http\Controllers\Controller;
use App\Services\Printing\PrintingCustomerApprovalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPrintingCustomerApprovalController extends Controller
{
    public function __construct(private readonly PrintingCustomerApprovalService $approvals) {}

    public function show(string $token): JsonResponse
    {
        $approval = $this->approvals->findByToken($token);

        return ApiResponse::success($this->approvals->publicPayload($approval));
    }

    public function approve(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $approval = $this->approvals->approveByToken($token, $data['notes'] ?? null);

        return ApiResponse::success($this->approvals->publicPayload($approval));
    }

    public function reject(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $approval = $this->approvals->rejectByToken($token, $data['notes'] ?? null);

        return ApiResponse::success($this->approvals->publicPayload($approval));
    }
}
