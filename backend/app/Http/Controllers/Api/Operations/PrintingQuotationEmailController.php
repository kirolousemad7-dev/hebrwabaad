<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\PrintingQuotation;
use App\Services\Customer\CustomerCommunicationService;
use App\Services\Printing\PrintingQuotationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrintingQuotationEmailController extends Controller
{
    public function __construct(
        private readonly CustomerCommunicationService $communications,
        private readonly PrintingQuotationService $quotations,
    ) {}

    public function store(Request $request, PrintingQuotation $printingQuotation): JsonResponse
    {
        $this->quotations->assertCanManage($request->user());

        $data = $request->validate([
            'force' => ['sometimes', 'boolean'],
            'public_token' => ['sometimes', 'nullable', 'string', 'min:32', 'max:128'],
        ]);

        if ($printingQuotation->public_token_hash === null || $printingQuotation->token_revoked_at !== null) {
            throw ValidationException::withMessages([
                'quotation' => ['Send the quotation before emailing a public link.'],
            ]);
        }

        $rawToken = $data['public_token'] ?? null;
        if (! is_string($rawToken) || $rawToken === '') {
            $rawToken = Str::random(64);
            $printingQuotation->update([
                'public_token_hash' => PrintingQuotation::hashToken($rawToken),
                'public_token_hint' => substr($rawToken, -8),
            ]);
        } elseif (PrintingQuotation::hashToken($rawToken) !== $printingQuotation->public_token_hash) {
            throw ValidationException::withMessages([
                'public_token' => ['Public token does not match this quotation.'],
            ]);
        }

        $result = $this->communications->emailQuotationForStaff(
            $request->user(),
            $printingQuotation->fresh() ?? $printingQuotation,
            $rawToken,
            (bool) ($data['force'] ?? false),
        );

        return ApiResponse::success([
            'status' => $result['status'],
            'public_url' => $result['public_url'],
            'public_path' => '/q/'.$rawToken,
            'mail_enabled' => $this->communications->mailEnabled(),
        ]);
    }
}
