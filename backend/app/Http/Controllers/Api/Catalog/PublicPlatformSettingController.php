<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicPlatformSettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettingService $settings,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->settings->publicPayload());
    }
}
