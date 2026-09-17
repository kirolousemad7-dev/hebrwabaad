<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SupplierVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\TransitionSupplierStatusRequest;
use App\Http\Requests\Content\UpdateSupplierLockedFieldsRequest;
use App\Http\Resources\AdminSupplierResource;
use App\Models\Supplier;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SupplierLifecycleController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function approve(TransitionSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('approve', $supplier);

        $supplier = $this->management->approve($request->user(), $supplier);

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function reject(TransitionSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('approve', $supplier);

        $supplier = $this->management->reject(
            $request->user(),
            $supplier,
            $request->validated('notes'),
        );

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function suspend(TransitionSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('suspend', $supplier);

        $supplier = $this->management->suspend(
            $request->user(),
            $supplier,
            $request->validated('notes'),
        );

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function requestChanges(TransitionSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('approve', $supplier);
        $notes = $request->validated('notes');
        if (! is_string($notes) || trim($notes) === '') {
            return ApiResponse::error('notes is required.', 422);
        }

        $supplier = $this->management->requestChanges($request->user(), $supplier, $notes);

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function block(TransitionSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('suspend', $supplier);

        $supplier = $this->management->block(
            $request->user(),
            $supplier,
            $request->validated('notes'),
        );

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function lockFields(UpdateSupplierLockedFieldsRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $supplier = $this->management->lockFields(
            $request->user(),
            $supplier,
            $request->validated('locked_fields'),
        );

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }

    public function verify(TransitionSupplierStatusRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('verify', $supplier);

        $statusValue = $request->validated('verification_status');
        if ($statusValue === null) {
            return ApiResponse::error('verification_status is required.', 422);
        }

        $status = $statusValue instanceof SupplierVerificationStatus
            ? $statusValue
            : SupplierVerificationStatus::from((string) $statusValue);

        $supplier = $this->management->verify($request->user(), $supplier, $status);

        return ApiResponse::success(AdminSupplierResource::make($supplier)->resolve($request));
    }
}
