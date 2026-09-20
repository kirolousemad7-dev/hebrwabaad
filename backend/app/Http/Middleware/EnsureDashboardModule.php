<?php

namespace App\Http\Middleware;

use App\Services\DashboardAccessService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardModule
{
    public function __construct(
        private readonly DashboardAccessService $dashboardAccess,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();
        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if ($modules === []) {
            return $next($request);
        }

        foreach ($modules as $module) {
            foreach (explode(',', $module) as $key) {
                $key = trim($key);
                if ($key !== '' && $this->dashboardAccess->canAccess($user, $key)) {
                    return $next($request);
                }
            }
        }

        return ApiResponse::error('Forbidden.', 403);
    }
}
