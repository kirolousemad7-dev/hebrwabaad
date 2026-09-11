<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\PrintingCatalogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicPrintingCatalogController extends Controller
{
    public function __construct(
        private readonly PrintingCatalogService $catalog,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'products' => $this->catalog->publicProducts(),
        ]);
    }
}
