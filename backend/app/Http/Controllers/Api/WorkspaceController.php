<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role;

        if (! $role instanceof UserRole || ! $role->usesEmployeeWorkspace()) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success(
            WorkspaceResource::make($user)->resolve($request)
        );
    }

    public function developer(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== UserRole::WebDeveloper) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return $this->show($request);
    }

    public function designer(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::GraphicDesigner);
    }

    public function marketing(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::MarketingSpecialist);
    }

    public function event(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::EventSpecialist);
    }

    public function printing(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::PrintingSpecialist);
    }

    public function mediaBuyer(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::MediaBuyer);
    }

    public function videoEditor(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::VideoEditor);
    }

    public function accountManager(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::AccountManager);
    }

    public function hr(Request $request): JsonResponse
    {
        return $this->requireRole($request, UserRole::Hr);
    }

    private function requireRole(Request $request, UserRole $role): JsonResponse
    {
        if ($request->user()?->role !== $role) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return $this->show($request);
    }
}
