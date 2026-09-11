<?php

namespace App\Http\Controllers\Api\PublicCrm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQuotationController extends Controller
{
    public function __construct(private readonly CrmQuotationService $quotations) {}

    public function show(string $token): JsonResponse
    {
        $quotation = $this->quotations->findByPublicToken($token);

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }

    public function accept(string $token): JsonResponse
    {
        $quotation = $this->quotations->acceptByToken($token);

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }

    public function reject(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $quotation = $this->quotations->rejectByToken($token, $data['notes'] ?? null);

        return ApiResponse::success($this->quotations->publicPayload($quotation));
    }
}
