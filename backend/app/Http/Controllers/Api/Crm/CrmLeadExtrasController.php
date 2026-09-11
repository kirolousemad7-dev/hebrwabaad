<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrmLeadResource;
use App\Services\Crm\CrmBulkLeadService;
use App\Services\Crm\CrmImportExportService;
use App\Services\Crm\CrmMergeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmLeadExtrasController extends Controller
{
    public function __construct(
        private readonly CrmImportExportService $importExport,
        private readonly CrmBulkLeadService $bulk,
        private readonly CrmMergeService $merge,
    ) {}

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'mapping' => ['nullable', 'array'],
        ]);

        $result = $this->importExport->importLeads(
            $request->user(),
            $request->file('file'),
            ['mapping' => $request->input('mapping', [])],
        );

        return ApiResponse::success([
            'created' => $result['created'],
            'duplicates' => $result['duplicates'],
            'leads' => CrmLeadResource::collection($result['leads'])->resolve($request),
        ], 201);
    }

    public function export(Request $request, string $entity): StreamedResponse|JsonResponse
    {
        $format = $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            return ApiResponse::error('Invalid format.', 422);
        }

        return $this->importExport->export($request->user(), $entity, $format, $request->query());
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:crm_leads,id'],
            'action' => ['required', 'in:assign,stage,priority,tags,schedule_follow_up,archive'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'stage_id' => ['nullable', 'integer', 'exists:crm_pipeline_stages,id'],
            'priority' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'follow_up_type' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->bulk->handle($request->user(), $data);

        return ApiResponse::success([
            'updated' => $result['updated'],
            'leads' => CrmLeadResource::collection($result['leads'])->resolve($request),
        ]);
    }

    public function mergeLeads(Request $request): JsonResponse
    {
        $data = $request->validate([
            'primary_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'secondary_id' => ['required', 'integer', 'exists:crm_leads,id', 'different:primary_id'],
        ]);

        $lead = $this->merge->merge(
            $request->user(),
            (int) $data['primary_id'],
            (int) $data['secondary_id'],
        );

        return ApiResponse::success(CrmLeadResource::make($lead)->resolve($request));
    }
}
