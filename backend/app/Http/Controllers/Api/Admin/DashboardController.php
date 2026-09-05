<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\OwnerDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(OwnerDashboardService $dashboard): JsonResponse
    {
        return ApiResponse::success($dashboard->build());
    }
}
