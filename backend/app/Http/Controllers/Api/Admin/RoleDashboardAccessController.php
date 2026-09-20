<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\DashboardAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleDashboardAccessController extends Controller
{
    public function __construct(
        private readonly DashboardAccessService $dashboardAccess,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->dashboardAccess->matrix());
    }

    public function show(string $role): JsonResponse
    {
        $roleEnum = UserRole::tryFrom($role);
        if ($roleEnum === null || ! in_array($role, $this->dashboardAccess->configurableRoles(), true)) {
            abort(404);
        }

        return ApiResponse::success([
            'role' => $roleEnum->value,
            'modules' => $this->dashboardAccess->forRole($roleEnum),
            'catalog' => $this->dashboardAccess->catalog(),
        ]);
    }

    public function update(Request $request, string $role): JsonResponse
    {
        $roleEnum = UserRole::tryFrom($role);
        if ($roleEnum === null || ! in_array($role, $this->dashboardAccess->configurableRoles(), true)) {
            abort(404);
        }

        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*' => ['boolean'],
        ]);

        $allowedKeys = array_flip(array_column($this->dashboardAccess->catalog(), 'key'));
        foreach (array_keys($validated['modules']) as $key) {
            if (! array_key_exists($key, $allowedKeys)) {
                return ApiResponse::error('Unknown dashboard module: '.$key, 422);
            }
        }

        $modules = $this->dashboardAccess->updateRole($roleEnum, $validated['modules']);

        return ApiResponse::success([
            'role' => $roleEnum->value,
            'modules' => $modules,
        ]);
    }
}
