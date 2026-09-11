<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreContactInquiryRequest;
use App\Models\ContactInquiry;
use App\Services\Crm\CrmLeadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class ContactInquiryController extends Controller
{
    public function __construct(private readonly CrmLeadService $crmLeads) {}

    public function store(StoreContactInquiryRequest $request): JsonResponse
    {
        $inquiry = ContactInquiry::query()->create($request->validated());

        try {
            $this->crmLeads->createFromContactInquiry($inquiry);
        } catch (Throwable $e) {
            report($e);
        }

        return ApiResponse::success(['status' => 'accepted'], 201);
    }
}
