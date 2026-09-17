<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Identity\EmailVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierEmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $emails) {}

    public function status(Request $request): JsonResponse
    {
        return ApiResponse::success($this->emails->status($request->user()));
    }

    public function resend(Request $request): JsonResponse
    {
        try {
            $status = $this->emails->send($request->user());
        } catch (ValidationException $exception) {
            return ApiResponse::error('Unable to resend.', 429, $exception->errors());
        }

        return ApiResponse::success($status);
    }

    public function verify(Request $request): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error('رابط التحقق غير صالح أو منتهٍ.', 403, [
                'status' => ['expired'],
            ]);
        }

        try {
            $user = $this->emails->verify(
                (int) $request->query('id'),
                (string) $request->query('hash'),
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error('Verification failed.', 422, $exception->errors());
        }

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve($request),
            'status' => 'verified',
        ]);
    }
}
