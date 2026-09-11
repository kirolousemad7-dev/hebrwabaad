<?php

namespace App\Http\Controllers\Api\Crm;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canAccessCrm() === true, 403);

        $team = User::query()
            ->active()
            ->whereIn('role', [
                UserRole::SalesManager->value,
                UserRole::SalesRepresentative->value,
                UserRole::Owner->value,
                UserRole::AdminManager->value,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return ApiResponse::success([
            'items' => $team->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
            ])->values(),
        ]);
    }
}
