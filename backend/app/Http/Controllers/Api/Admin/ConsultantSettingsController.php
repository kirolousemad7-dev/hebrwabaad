<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\UpdateConsultantSettingsRequest;
use App\Services\Consultant\ConsultantSettings;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConsultantSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success(ConsultantSettings::all());
    }

    public function update(UpdateConsultantSettingsRequest $request): JsonResponse
    {
        return ApiResponse::success(ConsultantSettings::update($request->validated()));
    }
}
