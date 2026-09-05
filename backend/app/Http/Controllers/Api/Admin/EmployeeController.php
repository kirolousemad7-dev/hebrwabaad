<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeStatusRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->employees->paginate($request->query());

        return ApiResponse::success([
            'items' => EmployeeResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employees->create($request->validated());

        return ApiResponse::success(
            EmployeeResource::make($employee)->resolve($request),
            201
        );
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $employee = $this->employees->findEmployee($user);

        return ApiResponse::success(
            EmployeeResource::make($employee)->resolve($request)
        );
    }

    public function update(UpdateEmployeeRequest $request, User $user): JsonResponse
    {
        $employee = $this->employees->update($user, $request->validated());

        return ApiResponse::success(
            EmployeeResource::make($employee)->resolve($request)
        );
    }

    public function setStatus(UpdateEmployeeStatusRequest $request, User $user): JsonResponse
    {
        $employee = $this->employees->setActive($user, (bool) $request->validated('is_active'));

        return ApiResponse::success(
            EmployeeResource::make($employee)->resolve($request)
        );
    }
}
