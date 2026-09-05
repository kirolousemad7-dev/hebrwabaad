<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerConversationResource;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\CustomerProjectResource;
use App\Http\Resources\ManagedFileResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\PrintingRequestResource;
use App\Models\Project;
use App\Services\CustomerDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function __construct(
        private readonly CustomerDashboardService $dashboard,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $this->dashboard->dashboard($user);

        return ApiResponse::success([
            'customer' => $payload['customer'],
            'summary' => $payload['summary'],
            'projects' => CustomerProjectResource::collection($payload['projects'])->resolve($request),
            'requests' => PrintingRequestResource::collection($payload['requests'])->resolve($request),
            'activity' => $payload['activity'],
            'orders' => CustomerOrderResource::collection($payload['orders'])->resolve($request),
            'messages' => CustomerConversationResource::collection($payload['messages'])->resolve($request),
            'files' => [
                'available' => true,
                'items' => ManagedFileResource::collection($payload['files'])->resolve($request),
            ],
            'notifications' => [
                'available' => true,
                'unread_count' => $payload['notification_unread'],
                'items' => NotificationResource::collection($payload['notifications'])->resolve($request),
            ],
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $projects = $this->dashboard->projectsFor($request->user());

        return ApiResponse::success(
            CustomerProjectResource::collection($projects)->resolve($request)
        );
    }

    public function project(Request $request, Project $project): JsonResponse
    {
        $this->authorize('viewOwned', $project);

        return ApiResponse::success(
            CustomerProjectResource::make($this->dashboard->load($project))->resolve($request)
        );
    }
}
