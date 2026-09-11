<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\Operations\DepartmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $activeOnly = $request->boolean('active_only', false);

        return ApiResponse::success([
            'items' => $this->departments->list($activeOnly ? true : null),
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        return ApiResponse::success([
            'items' => $this->departments->options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $department = $this->departments->create($data);

        return ApiResponse::success($this->departments->serialize($department), 201);
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        $department->load('manager:id,name')->loadCount('employees');

        return ApiResponse::success($this->departments->serialize($department));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $department = $this->departments->update($department, $data);

        return ApiResponse::success($this->departments->serialize($department));
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        $this->departments->delete($department);

        return ApiResponse::success(['deleted' => true]);
    }

    public function assignEmployee(Request $request): JsonResponse
    {
        $this->authorize('assign', Department::class);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);
        $user = $this->departments->assignEmployee(
            $user,
            array_key_exists('department_id', $data) ? ($data['department_id'] !== null ? (int) $data['department_id'] : null) : null,
        );

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'department_id' => $user->department_id,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ] : null,
        ]);
    }
}
