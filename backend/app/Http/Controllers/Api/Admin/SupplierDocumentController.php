<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreSupplierDocumentRequest;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierDocumentController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $documents = $supplier->documents()->orderByDesc('id')->get();

        return ApiResponse::success([
            'items' => $documents->map(fn (SupplierDocument $document) => $this->serialize($document))->all(),
        ]);
    }

    public function store(StoreSupplierDocumentRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $document = $this->management->storeDocument(
            $request->user(),
            $supplier,
            $request->safe()->except('file'),
            $request->file('file'),
        );

        return ApiResponse::success($this->serialize($document), 201);
    }

    public function download(Request $request, Supplier $supplier, SupplierDocument $document): StreamedResponse
    {
        $this->authorize('view', $supplier);
        $this->assertBelongsToSupplier($supplier, $document);

        return $this->management->downloadDocument($request->user(), $document);
    }

    public function destroy(Supplier $supplier, SupplierDocument $document): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $document);

        $this->management->deleteDocument($document);

        return ApiResponse::success(null);
    }

    private function assertBelongsToSupplier(Supplier $supplier, SupplierDocument $document): void
    {
        if ((int) $document->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SupplierDocument $document): array
    {
        return [
            'id' => $document->id,
            'supplier_id' => $document->supplier_id,
            'uploaded_by' => $document->uploaded_by,
            'title' => $document->title,
            'category' => $document->category,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'visibility' => $document->visibility?->value,
            'notes' => $document->notes,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}
