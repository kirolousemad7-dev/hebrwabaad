<?php

namespace App\Http\Controllers\Api\NeedsDiscovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\NeedsDiscovery\StoreNeedsDiscoveryRequest;
use App\Services\NeedsDiscovery\NeedsDiscoveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class NeedsDiscoveryController extends Controller
{
    public function __construct(private readonly NeedsDiscoveryService $discovery) {}

    public function steps(): JsonResponse
    {
        return ApiResponse::success([
            'title' => 'اكتشف احتياجك',
            'steps' => $this->discovery->steps(),
        ]);
    }

    public function store(StoreNeedsDiscoveryRequest $request): JsonResponse
    {
        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = $files !== null ? [$files] : [];
        }

        $result = $this->discovery->submit($request->validated(), array_values($files));

        return ApiResponse::success([
            'status' => 'accepted',
            'reference' => $result['requirement']->reference,
            'updated_lead' => ! $result['created_lead'],
            'summary' => $result['requirement']->summary,
            'recommended_services' => $result['requirement']->recommended_services ?? [],
        ], 201);
    }
}
