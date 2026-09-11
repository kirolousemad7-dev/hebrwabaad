<?php

namespace App\Http\Controllers\Api\Crm;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Crm\CrmCustomer360Service;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmCustomerController extends Controller
{
    public function __construct(private readonly CrmCustomer360Service $customers) {}

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($user->role === UserRole::Customer, 404);

        return ApiResponse::success($this->customers->profile($request->user(), $user));
    }
}
