<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmForecastService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmForecastController extends Controller
{
    public function __construct(private readonly CrmForecastService $forecast) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->forecast->forecast($request->user()));
    }
}
