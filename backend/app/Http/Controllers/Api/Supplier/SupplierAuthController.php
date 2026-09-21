<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\RequestSupplierOtpRequest;
use App\Http\Requests\Supplier\SupplierLoginRequest;
use App\Http\Requests\Supplier\VerifySupplierOtpRequest;
use App\Http\Resources\AdminSupplierResource;
use App\Http\Resources\UserResource;
use App\Services\SupplierAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SupplierAuthController extends Controller
{
    public function __construct(private readonly SupplierAuthService $auth) {}

    public function login(SupplierLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->loginWithPassword(
                $request->validated('email'),
                $request->validated('password'),
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error($exception->getMessage() ?: __('messages.invalid_credentials'), 401, $exception->errors());
        }

        return ApiResponse::success([
            'user' => UserResource::make($result['user'])->resolve($request),
            'supplier' => AdminSupplierResource::make($result['supplier'])->resolve($request),
            'token' => $result['token'],
        ]);
    }

    public function requestOtp(RequestSupplierOtpRequest $request): JsonResponse
    {
        $result = $this->auth->requestOtp($request->validated('email'));

        return ApiResponse::success($result);
    }

    public function verifyOtp(VerifySupplierOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->verifyOtp(
                $request->validated('email'),
                $request->validated('code'),
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error('OTP verification failed.', 422, $exception->errors());
        }

        return ApiResponse::success([
            'user' => UserResource::make($result['user'])->resolve($request),
            'supplier' => AdminSupplierResource::make($result['supplier'])->resolve($request),
            'token' => $result['token'],
        ]);
    }
}
