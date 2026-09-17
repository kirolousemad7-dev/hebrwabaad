<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpsertSupplierServiceRequest;
use App\Models\Supplier;
use App\Models\SupplierService;
use App\Services\SupplierCatalogService;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierServiceController extends Controller
{
    public function __construct(
        private readonly SupplierManagementService $management,
        private readonly SupplierCatalogService $catalog,
    ) {}

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $services = $supplier->offeredServices()->with('tags')->orderBy('sort_order')->orderBy('name')->get();

        return ApiResponse::success([
            'items' => $services->map(fn (SupplierService $service) => $this->serialize($service))->all(),
        ]);
    }

    public function store(UpsertSupplierServiceRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $service = $this->management->upsertService($supplier, $request->validated());

        return ApiResponse::success($this->serialize($service), 201);
    }

    public function update(UpsertSupplierServiceRequest $request, Supplier $supplier, SupplierService $service): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $service);

        $service = $this->management->upsertService($supplier, $request->validated(), $service);

        return ApiResponse::success($this->serialize($service));
    }

    public function destroy(Supplier $supplier, SupplierService $service): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $service);

        $service->delete();

        return ApiResponse::success(null);
    }

    public function duplicate(Supplier $supplier, SupplierService $service): JsonResponse
    {
        $this->authorize('update', $supplier);
        $copy = $this->catalog->duplicateService($supplier, $service);

        return ApiResponse::success($this->serialize($copy), 201);
    }

    public function archive(Supplier $supplier, SupplierService $service): JsonResponse
    {
        $this->authorize('update', $supplier);
        $service = $this->catalog->archiveService($supplier, $service);

        return ApiResponse::success($this->serialize($service));
    }

    public function export(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return ApiResponse::success([
            'items' => $this->catalog->exportServices($supplier),
        ]);
    }

    public function import(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);
        $payload = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['array'],
        ]);

        $result = $this->catalog->importServices(
            $supplier,
            $this->catalog->normalizeImportRows($payload['rows']),
        );

        return ApiResponse::success($result, 201);
    }

    private function assertBelongsToSupplier(Supplier $supplier, SupplierService $service): void
    {
        if ((int) $service->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SupplierService $service): array
    {
        return [
            'id' => $service->id,
            'supplier_id' => $service->supplier_id,
            'name' => $service->name,
            'category' => $service->category,
            'description' => $service->description,
            'pricing_model' => $service->pricing_model?->value,
            'minimum_price' => $service->minimum_price,
            'maximum_price' => $service->maximum_price,
            'currency' => $service->currency,
            'delivery_time' => $service->delivery_time,
            'service_area' => $service->service_area,
            'notes' => $service->notes,
            'attachments' => $service->attachments ?? [],
            'is_active' => $service->is_active,
            'visibility' => $service->visibility?->value,
            'sort_order' => $service->sort_order,
            'tags' => $service->relationLoaded('tags')
                ? $service->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                ])->all()
                : [],
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }
}
