<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\Notifications\NotificationCenterService;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationCenterService $center,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->center->inbox($request->user(), $request->query(), $request)
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->center->unreadCount($request->user())
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $owned = $this->notifications->owned($request->user(), $notification);

        if ($owned === null) {
            return ApiResponse::error(__('messages.not_found'), 404);
        }

        return ApiResponse::success(
            NotificationResource::make($this->notifications->markRead($owned))->resolve($request)
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notifications->markAllRead($request->user());

        return ApiResponse::success([
            'updated' => $updated,
            'unread_count' => 0,
            'total' => 0,
            'by_category' => [],
        ]);
    }
}
