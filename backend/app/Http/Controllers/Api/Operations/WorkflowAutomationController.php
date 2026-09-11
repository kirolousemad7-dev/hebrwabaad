<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowTrigger;
use App\Http\Controllers\Controller;
use App\Models\WorkflowAutomation;
use App\Models\WorkflowAutomationRun;
use App\Services\Workflow\WorkflowAutomationEngine;
use App\Support\ApiResponse;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowAutomationController extends Controller
{
    public function __construct(
        private readonly WorkflowAutomationEngine $engine,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', WorkflowAutomation::class);

        $items = WorkflowAutomation::query()
            ->with('creator:id,name')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (WorkflowAutomation $row) => $this->serialize($row))
            ->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WorkflowAutomation::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', Rule::in(WorkflowTrigger::values())],
            'conditions' => ['nullable', 'array'],
            'actions' => ['required', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'max_depth' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'max_actions_per_run' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $automation = WorkflowAutomation::query()->create([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'conditions' => $data['conditions'] ?? [],
            'actions' => $data['actions'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_template' => (bool) ($data['is_template'] ?? false),
            'max_depth' => (int) ($data['max_depth'] ?? 3),
            'max_actions_per_run' => (int) ($data['max_actions_per_run'] ?? 10),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success($this->serialize($automation), 201);
    }

    public function show(WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('view', $automation);

        return ApiResponse::success($this->serialize($automation->load('creator:id,name')));
    }

    public function update(Request $request, WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('update', $automation);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trigger' => ['sometimes', 'required', 'string', Rule::in(WorkflowTrigger::values())],
            'conditions' => ['nullable', 'array'],
            'actions' => ['sometimes', 'required', 'array', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_template' => ['sometimes', 'boolean'],
            'max_depth' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'max_actions_per_run' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $automation->fill($data)->save();

        return ApiResponse::success($this->serialize($automation->fresh(['creator:id,name']) ?? $automation));
    }

    public function destroy(WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('delete', $automation);
        $automation->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function activate(Request $request, WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('update', $automation);

        // confirm is optional for backward compatibility; frontend may require it later.
        $request->validate([
            'confirm' => ['sometimes', 'boolean'],
        ]);

        $automation->update(['is_active' => true, 'is_template' => false]);

        return ApiResponse::success($this->serialize($automation->fresh() ?? $automation));
    }

    public function dryRun(Request $request, WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('view', $automation);

        $data = $request->validate([
            'context' => ['sometimes', 'array'],
        ]);

        $preview = $this->engine->dryRun($automation, $data['context'] ?? []);

        return ApiResponse::success($preview);
    }

    public function deactivate(WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('update', $automation);
        $automation->update(['is_active' => false]);

        return ApiResponse::success($this->serialize($automation->fresh() ?? $automation));
    }

    public function runs(WorkflowAutomation $automation): JsonResponse
    {
        $this->authorize('view', $automation);

        $runs = WorkflowAutomationRun::query()
            ->where('automation_id', $automation->id)
            ->orderByDesc('executed_at')
            ->limit(100)
            ->get()
            ->map(fn (WorkflowAutomationRun $run) => [
                'id' => $run->id,
                'trigger' => $run->trigger,
                'idempotency_key' => $run->idempotency_key,
                'source_type' => $run->source_type,
                'source_id' => $run->source_id,
                'status' => $run->status instanceof WorkflowRunStatus
                    ? $run->status->value
                    : $run->status,
                'depth' => (int) ($run->depth ?? 0),
                'origin_run_id' => $run->origin_run_id,
                'trigger_chain' => $run->trigger_chain ?? [],
                'is_dry_run' => (bool) ($run->is_dry_run ?? false),
                'result' => $run->result,
                'actions' => is_array($run->result) ? ($run->result['actions'] ?? null) : null,
                'executed_at' => $run->executed_at?->toIso8601String(),
            ])
            ->all();

        return ApiResponse::success(['items' => $runs]);
    }

    public function seedTemplates(Request $request): JsonResponse
    {
        $this->authorize('create', WorkflowAutomation::class);

        $seeder = new WorkflowTemplateSeeder;
        $seeder->run($request->user());

        return ApiResponse::success(['seeded' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowAutomation $automation): array
    {
        return [
            'id' => $automation->id,
            'name' => $automation->name,
            'trigger' => $automation->trigger,
            'conditions' => $automation->conditions ?? [],
            'actions' => $automation->actions ?? [],
            'is_active' => (bool) $automation->is_active,
            'is_template' => (bool) $automation->is_template,
            'max_depth' => (int) ($automation->max_depth ?? 3),
            'max_actions_per_run' => (int) ($automation->max_actions_per_run ?? 10),
            'created_by' => $automation->created_by,
            'creator' => $automation->relationLoaded('creator') && $automation->creator
                ? ['id' => $automation->creator->id, 'name' => $automation->creator->name]
                : null,
            'last_run_at' => $automation->last_run_at?->toIso8601String(),
            'created_at' => $automation->created_at?->toIso8601String(),
            'updated_at' => $automation->updated_at?->toIso8601String(),
        ];
    }
}
