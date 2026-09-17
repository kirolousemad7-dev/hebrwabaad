<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Enums\SupplierStatus;
use App\Exceptions\ContentWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreSupplierContactRequest;
use App\Http\Requests\Content\StoreSupplierDocumentRequest;
use App\Http\Requests\Content\UpsertSupplierServiceRequest;
use App\Http\Resources\AdminSupplierResource;
use App\Models\SupplierContact;
use App\Models\SupplierDocument;
use App\Models\SupplierService;
use App\Services\SupplierContentService;
use App\Services\SupplierManagementService;
use App\Services\SupplierProfileCompletionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPortalController extends Controller
{
    public function __construct(
        private readonly SupplierContentService $content,
        private readonly SupplierManagementService $management,
        private readonly SupplierProfileCompletionService $completion,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $supplier = $this->content->supplierFor($request->user());
        } catch (ContentWorkflowException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        $supplier->loadCount(['contacts', 'offeredServices', 'products', 'portfolioItems', 'documents']);
        $completion = $this->completion->summarize($supplier);

        return ApiResponse::success([
            'supplier' => AdminSupplierResource::make($supplier)->resolve($request),
            'completion' => $completion,
            'counts' => [
                'contacts' => (int) $supplier->contacts_count,
                'services' => (int) $supplier->offered_services_count,
                'products' => (int) $supplier->products_count,
                'portfolio' => (int) $supplier->portfolio_items_count,
                'documents' => (int) $supplier->documents_count,
            ],
            'access' => [
                'is_active_supplier' => $supplier->status === SupplierStatus::Active,
                'status' => $supplier->status?->value,
                'owner_change_request' => $supplier->owner_change_request,
                'locked_fields' => $supplier->locked_fields ?? [],
            ],
            'modules' => [
                'quotations' => ['available' => false, 'items' => []],
                'projects' => ['available' => false, 'items' => []],
                'tasks' => ['available' => false, 'items' => []],
                'calendar' => ['available' => false, 'items' => []],
                'messages' => ['available' => false, 'items' => []],
            ],
        ]);
    }

    public function completion(Request $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());

        return ApiResponse::success($this->completion->summarize($supplier));
    }

    public function contacts(Request $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());

        return ApiResponse::success([
            'items' => $supplier->contacts()->orderByDesc('is_primary')->orderBy('id')->get(),
        ]);
    }

    public function storeContact(StoreSupplierContactRequest $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $contact = $this->management->upsertContact($supplier, $request->validated());

        return ApiResponse::success($contact, 201);
    }

    public function updateContact(StoreSupplierContactRequest $request, SupplierContact $contact): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $this->assertOwn($supplier->id, (int) $contact->supplier_id);
        $contact = $this->management->upsertContact($supplier, $request->validated(), $contact);

        return ApiResponse::success($contact);
    }

    public function destroyContact(Request $request, SupplierContact $contact): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $this->assertOwn($supplier->id, (int) $contact->supplier_id);
        $contact->delete();

        return ApiResponse::success(null);
    }

    public function services(Request $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());

        return ApiResponse::success([
            'items' => $supplier->offeredServices()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function storeService(UpsertSupplierServiceRequest $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $service = $this->management->upsertService($supplier, $request->validated());

        return ApiResponse::success($service, 201);
    }

    public function updateService(UpsertSupplierServiceRequest $request, SupplierService $service): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $this->assertOwn($supplier->id, (int) $service->supplier_id);
        $service = $this->management->upsertService($supplier, $request->validated(), $service);

        return ApiResponse::success($service);
    }

    public function destroyService(Request $request, SupplierService $service): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $this->assertOwn($supplier->id, (int) $service->supplier_id);
        $service->delete();

        return ApiResponse::success(null);
    }

    public function documents(Request $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());

        return ApiResponse::success([
            'items' => $supplier->documents()->orderByDesc('id')->get(),
        ]);
    }

    public function storeDocument(StoreSupplierDocumentRequest $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $document = $this->management->storeDocument($request->user(), $supplier, $request->validated());

        return ApiResponse::success($document, 201);
    }

    public function destroyDocument(Request $request, SupplierDocument $document): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());
        $this->assertOwn($supplier->id, (int) $document->supplier_id);
        $document->delete();

        return ApiResponse::success(null);
    }

    public function settings(Request $request): JsonResponse
    {
        $supplier = $this->content->supplierFor($request->user());

        return ApiResponse::success([
            'locked_fields' => $supplier->locked_fields ?? [],
            'status' => $supplier->status?->value,
            'verification_status' => $supplier->verification_status?->value,
            'onboarding_status' => $supplier->onboarding_status?->value,
            'owner_change_request' => $supplier->owner_change_request,
            'show_public_contact' => $supplier->show_public_contact,
            'email_verified_at' => $supplier->email_verified_at?->toIso8601String()
                ?? $request->user()?->email_verified_at?->toIso8601String(),
            'phone_verified_at' => $supplier->phone_verified_at?->toIso8601String(),
        ]);
    }

    private function assertOwn(int $supplierId, int $resourceSupplierId): void
    {
        if ($supplierId !== $resourceSupplierId) {
            abort(404);
        }
    }
}
