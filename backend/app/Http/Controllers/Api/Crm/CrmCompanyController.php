<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmCompany;
use App\Services\Crm\CrmCompanyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmCompanyController extends Controller
{
    public function __construct(private readonly CrmCompanyService $companies) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->companies->paginateFor($request->user(), $request->query());

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'industry' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'company_size' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'integer', 'exists:crm_lead_sources,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::success($this->companies->create($request->user(), $data), 201);
    }

    public function show(Request $request, CrmCompany $company): JsonResponse
    {
        return ApiResponse::success($this->companies->assertVisible($request->user(), $company));
    }

    public function update(Request $request, CrmCompany $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'industry' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'company_size' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'integer', 'exists:crm_lead_sources,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:active,archived'],
        ]);

        return ApiResponse::success($this->companies->update($request->user(), $company, $data));
    }

    public function archive(Request $request, CrmCompany $company): JsonResponse
    {
        return ApiResponse::success($this->companies->archive($request->user(), $company));
    }

    public function destroy(Request $request, CrmCompany $company): JsonResponse
    {
        return $this->archive($request, $company);
    }
}
