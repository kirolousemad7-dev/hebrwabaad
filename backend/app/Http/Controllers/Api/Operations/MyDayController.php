<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\EmployeeMyDayService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDayController extends Controller
{
    public function __construct(
        private readonly EmployeeMyDayService $myDay,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->myDay->for($request->user()));
    }
}
