<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmAuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmAuditController extends Controller
{
    public function __construct(private readonly CrmAuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->audit->paginateFor($request->user(), $request->query());

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
}
