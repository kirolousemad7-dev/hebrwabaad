<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmDashboardController extends Controller
{
    public function __construct(private readonly CrmDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboard->summary(
                $request->user(),
                $request->query('from'),
                $request->query('to'),
                $request->query('view'),
            )
        );
    }
}
