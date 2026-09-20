<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestCustomerOtpRequest;
use App\Http\Requests\Auth\VerifyCustomerOtpRequest;
use App\Services\Auth\CustomerOtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CustomerOtpController extends Controller
{
    public function __construct(
        private readonly CustomerOtpService $otp,
    ) {}

    public function request(RequestCustomerOtpRequest $request): JsonResponse
    {
        $result = $this->otp->requestOtp($request->validated('email'));

        return ApiResponse::success($result);
    }

    public function verify(VerifyCustomerOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->otp->verifyOtp(
                $request->validated('email'),
                $request->validated('code'),
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error('OTP verification failed.', 422, $exception->errors());
        }

        return ApiResponse::success($result);
    }
}
