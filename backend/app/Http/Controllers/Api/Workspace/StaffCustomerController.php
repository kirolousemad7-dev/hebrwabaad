<?php

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreStaffCustomerRequest;
use App\Http\Resources\CustomerDirectoryResource;
use App\Models\Project;
use App\Services\Customer\StaffCustomerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StaffCustomerController extends Controller
{
    public function __construct(private readonly StaffCustomerService $customers) {}

    public function store(StoreStaffCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $customer = $this->customers->create($request->validated());
        $customer->loadCount('customerProjects');
        $customer->loadMax('tokens', 'last_used_at');

        return ApiResponse::success(
            CustomerDirectoryResource::make($customer)->resolve($request),
            201,
        );
    }
}
