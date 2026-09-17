<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreSupplierContactRequest;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierContactController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $contacts = $supplier->contacts()->orderByDesc('is_primary')->orderBy('name')->get();

        return ApiResponse::success([
            'items' => $contacts->map(fn (SupplierContact $contact) => $this->serialize($contact))->all(),
        ]);
    }

    public function store(StoreSupplierContactRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $contact = $this->management->upsertContact($supplier, $request->validated());

        return ApiResponse::success($this->serialize($contact), 201);
    }

    public function update(StoreSupplierContactRequest $request, Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $contact);

        $contact = $this->management->upsertContact($supplier, $request->validated(), $contact);

        return ApiResponse::success($this->serialize($contact));
    }

    public function destroy(Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        $this->authorize('update', $supplier);
        $this->assertBelongsToSupplier($supplier, $contact);

        $contact->delete();

        return ApiResponse::success(null);
    }

    private function assertBelongsToSupplier(Supplier $supplier, SupplierContact $contact): void
    {
        if ((int) $contact->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SupplierContact $contact): array
    {
        return [
            'id' => $contact->id,
            'supplier_id' => $contact->supplier_id,
            'name' => $contact->name,
            'position' => $contact->position,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'whatsapp' => $contact->whatsapp,
            'is_primary' => $contact->is_primary,
            'notes' => $contact->notes,
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }
}
