<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UpdateEventRequestRequest;
use App\Http\Resources\EventRequestResource;
use App\Models\EventRequest;
use App\Services\Catalog\EventRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRequestAdminController extends Controller
{
    public function __construct(private EventRequestService $events) {}

    public function index(Request $request): JsonResponse
    {
        $requests = EventRequest::query()
            ->with(['user', 'project'])
            ->latest()
            ->get();

        return ApiResponse::success(EventRequestResource::collection($requests)->resolve($request));
    }

    public function update(UpdateEventRequestRequest $request, EventRequest $eventRequest): JsonResponse
    {
        $updated = $this->events->update($eventRequest, $request->validated());

        return ApiResponse::success(EventRequestResource::make($updated)->resolve($request));
    }
}
