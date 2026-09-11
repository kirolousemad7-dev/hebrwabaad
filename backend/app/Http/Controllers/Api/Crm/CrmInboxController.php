<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmInboxService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmInboxController extends Controller
{
    public function __construct(private readonly CrmInboxService $inbox) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->inbox->inbox($request->user()));
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['string'],
            'all' => ['nullable', 'boolean'],
        ]);

        $count = $this->inbox->markRead(
            $request->user(),
            $data['ids'] ?? [],
            (bool) ($data['all'] ?? false),
        );

        return ApiResponse::success(['marked' => $count]);
    }
}
