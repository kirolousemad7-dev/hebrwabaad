<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AccessController extends Controller
{
    public function test(): JsonResponse
    {
        return ApiResponse::success([
            'ok' => true,
        ]);
    }
}
