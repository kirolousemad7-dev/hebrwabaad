<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminSupplierResource;
use App\Services\Identity\PhoneVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierPhoneVerificationController extends Controller
{
    public function __construct(private readonly PhoneVerificationService $phones) {}

    public function requestCode(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $result = $this->phones->request($request->user(), $payload['phone'] ?? null);
        } catch (ValidationException $exception) {
            $status = str_contains(implode(' ', $exception->errors()['phone'] ?? []), 'الانتظار') ? 429 : 422;

            return ApiResponse::error(__('messages.unable_to_send_phone_otp'), $status, $exception->errors());
        }

        return ApiResponse::success($result);
    }

    public function verify(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:8'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $supplier = $this->phones->verify(
                $request->user(),
                $payload['code'],
                $payload['phone'] ?? null,
            );
        } catch (ValidationException $exception) {
            return ApiResponse::error('Phone verification failed.', 422, $exception->errors());
        }

        return ApiResponse::success([
            'supplier' => AdminSupplierResource::make($supplier)->resolve($request),
            'status' => 'verified',
        ]);
    }
}
