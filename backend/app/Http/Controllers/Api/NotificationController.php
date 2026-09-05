<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->notifications->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => NotificationResource::collection($page->items())->resolve($request),
            'unread_count' => $this->notifications->unreadCount($request->user()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $owned = $this->notifications->owned($request->user(), $notification);

        if ($owned === null) {
            return ApiResponse::error('Not found.', 404);
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
        ]);
    }
}
