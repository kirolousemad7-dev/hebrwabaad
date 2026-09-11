<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadSource;
use App\Models\CrmLostReason;
use App\Models\CrmPipelineStage;
use App\Models\CrmTag;
use App\Services\Crm\CrmSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CrmSettingsController extends Controller
{
    public function __construct(private readonly CrmSettingsService $config) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCrmSettings() === true, 403);

        return ApiResponse::success([
            'sources' => CrmLeadSource::query()->orderBy('sort_order')->get(),
            'stages' => CrmPipelineStage::query()->orderBy('sort_order')->get(),
            'lost_reasons' => CrmLostReason::query()->orderBy('sort_order')->get(),
            'tags' => CrmTag::query()->orderBy('name')->get(),
            'config' => $this->config->all(),
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'discount_max_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stale_lead_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'new_lead_sla_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'assignment_mode' => ['nullable', Rule::in(['manual', 'round_robin', 'by_source', 'by_service'])],
        ]);

        return ApiResponse::success([
            'config' => $this->config->setMany($data),
        ]);
    }

    public function storeSource(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $data = $this->validateNamed($request);

        $source = CrmLeadSource::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success($source, 201);
    }

    public function updateSource(Request $request, CrmLeadSource $source): JsonResponse
    {
        $this->assertManager($request);
        $data = $this->validateNamed($request, optional: true);
        $source->update($data);

        return ApiResponse::success($source->fresh());
    }

    public function storeStage(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:crm_pipeline_stages,slug'],
            'is_won' => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $stage = CrmPipelineStage::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'is_won' => $data['is_won'] ?? false,
            'is_lost' => $data['is_lost'] ?? false,
            'probability' => $data['probability'] ?? 0,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success($stage, 201);
    }

    public function updateStage(Request $request, CrmPipelineStage $stage): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'max:120', 'unique:crm_pipeline_stages,slug,'.$stage->id],
            'is_won' => ['nullable', 'boolean'],
            'is_lost' => ['nullable', 'boolean'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $stage->update($data);

        return ApiResponse::success($stage->fresh());
    }

    public function storeLostReason(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $data = $this->validateNamed($request);

        $reason = CrmLostReason::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return ApiResponse::success($reason, 201);
    }

    public function updateLostReason(Request $request, CrmLostReason $reason): JsonResponse
    {
        $this->assertManager($request);
        $data = $this->validateNamed($request, optional: true, table: 'crm_lost_reasons', id: $reason->id);
        $reason->update($data);

        return ApiResponse::success($reason->fresh());
    }

    public function storeTag(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', 'unique:crm_tags,slug'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $tag = CrmTag::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);

        return ApiResponse::success($tag, 201);
    }

    public function updateTag(Request $request, CrmTag $tag): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'slug' => ['sometimes', 'required', 'string', 'max:80', 'unique:crm_tags,slug,'.$tag->id],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        $tag->update($data);

        return ApiResponse::success($tag->fresh());
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()?->role?->canManageCrmSettings() === true, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNamed(
        Request $request,
        bool $optional = false,
        string $table = 'crm_lead_sources',
        ?int $id = null,
    ): array {
        $nameRule = $optional ? ['sometimes', 'required', 'string', 'max:120'] : ['required', 'string', 'max:120'];
        $slugUnique = 'unique:'.$table.',slug'.($id ? ','.$id : '');

        return $request->validate([
            'name' => $nameRule,
            'slug' => [$optional ? 'sometimes' : 'nullable', 'string', 'max:120', $slugUnique],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
