<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Services\Crm\CrmContactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmContactController extends Controller
{
    public function __construct(private readonly CrmContactService $contacts) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->contacts->paginateFor($request->user(), $request->query());

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
            'company_id' => ['required', 'integer', 'exists:crm_companies,id'],
            'lead_id' => ['nullable', 'integer', 'exists:crm_leads,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::success($this->contacts->create($request->user(), $data), 201);
    }

    public function show(Request $request, CrmContact $contact): JsonResponse
    {
        return ApiResponse::success($this->contacts->assertVisible($request->user(), $contact));
    }

    public function update(Request $request, CrmContact $contact): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => ['nullable', 'integer', 'exists:crm_leads,id'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return ApiResponse::success($this->contacts->update($request->user(), $contact, $data));
    }
}
