<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsCommandCenterService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsCommandCenterController extends Controller
{
    public function __construct(
        private readonly OperationsCommandCenterService $commandCenter,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole) || ! $user->role->canViewCommandCenter()) {
            abort(403);
        }

        return ApiResponse::success($this->commandCenter->for($user));
    }
}
