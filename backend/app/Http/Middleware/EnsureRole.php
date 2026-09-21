<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error(__('messages.unauthenticated'), 401);
        }

        if ($user->is_active === false) {
            return ApiResponse::error('الحساب معطّل.', 403);
        }

        $currentRole = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        if (! in_array($currentRole, $roles, true)) {
            return ApiResponse::error(__('messages.forbidden'), 403);
        }

        return $next($request);
    }
}
