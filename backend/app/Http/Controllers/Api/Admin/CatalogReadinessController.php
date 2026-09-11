<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogReadinessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CatalogReadinessController extends Controller
{
    public function __construct(private CatalogReadinessService $readiness) {}

    public function show(): JsonResponse
    {
        $rows = $this->readiness->report();

        return ApiResponse::success([
            'items' => $rows,
            'summary' => [
                'total' => count($rows),
                'purchasable' => count(array_filter($rows, fn (array $row) => $row['is_purchasable'])),
                'price_incomplete' => count(array_filter($rows, fn (array $row) => ! $row['is_price_complete'])),
            ],
        ]);
    }
}
