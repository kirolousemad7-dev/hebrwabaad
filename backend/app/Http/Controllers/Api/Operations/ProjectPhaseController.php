<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Services\Operations\ProjectPhaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectPhaseController extends Controller
{
    public function __construct(
        private readonly ProjectPhaseService $phases,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('view', $project);

        return ApiResponse::success([
            'items' => $this->phases->list($project),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in([
                ProjectPhase::STATUS_PENDING,
                ProjectPhase::STATUS_IN_PROGRESS,
                ProjectPhase::STATUS_DONE,
            ])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_client_visible' => ['nullable', 'boolean'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $phase = $this->phases->create($request->user(), $project, $data);

        return ApiResponse::success($this->phases->serialize($phase), 201);
    }

    public function update(Request $request, Project $project, ProjectPhase $phase): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('manageMembers', $project);
        $this->assertBelongsToProject($project, $phase);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in([
                ProjectPhase::STATUS_PENDING,
                ProjectPhase::STATUS_IN_PROGRESS,
                ProjectPhase::STATUS_DONE,
            ])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_client_visible' => ['nullable', 'boolean'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $phase = $this->phases->update($phase, $data);

        return ApiResponse::success($this->phases->serialize($phase));
    }

    public function destroy(Request $request, Project $project, ProjectPhase $phase): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('manageMembers', $project);
        $this->assertBelongsToProject($project, $phase);

        $this->phases->delete($phase);

        return ApiResponse::success(['deleted' => true]);
    }

    private function assertCanOversee(Request $request): void
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole) || ! $user->role->canOverseeProjects()) {
            abort(403);
        }
    }

    private function assertBelongsToProject(Project $project, ProjectPhase $phase): void
    {
        if ((int) $phase->project_id !== (int) $project->id) {
            abort(404);
        }
    }
}
