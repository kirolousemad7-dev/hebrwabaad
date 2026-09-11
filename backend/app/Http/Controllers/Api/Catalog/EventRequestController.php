<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreEventRequestRequest;
use App\Http\Resources\EventRequestResource;
use App\Services\Catalog\EventRequestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EventRequestController extends Controller
{
    public function __construct(private EventRequestService $events) {}

    public function store(StoreEventRequestRequest $request): JsonResponse
    {
        $eventRequest = $this->events->create($request->user(), $request->validated());

        return ApiResponse::success(
            EventRequestResource::make($eventRequest)->resolve($request),
            201
        );
    }
}
