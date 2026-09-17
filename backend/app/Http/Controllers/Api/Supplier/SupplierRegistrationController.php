<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\RegisterSupplierRequest;
use App\Http\Resources\AdminSupplierResource;
use App\Http\Resources\UserResource;
use App\Services\SupplierOnboardingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SupplierRegistrationController extends Controller
{
    public function __construct(private readonly SupplierOnboardingService $onboarding) {}

    public function store(RegisterSupplierRequest $request): JsonResponse
    {
        $result = $this->onboarding->register($request->validated());

        return ApiResponse::success([
            'user' => UserResource::make($result['user'])->resolve($request),
            'supplier' => AdminSupplierResource::make($result['supplier'])->resolve($request),
            'token' => $result['token'],
            'status' => $result['supplier']->status?->value,
            'message' => 'تم إرسال طلب التسجيل. بانتظار موافقة الإدارة.',
        ], 201);
    }
}
