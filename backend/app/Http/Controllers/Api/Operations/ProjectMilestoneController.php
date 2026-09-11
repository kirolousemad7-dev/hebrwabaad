<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\Operations\ProjectMilestoneService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMilestoneController extends Controller
{
    public function __construct(
        private readonly ProjectMilestoneService $milestones,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('view', $project);

        return ApiResponse::success([
            'items' => $this->milestones->list($project),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('manageMembers', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in([
                ProjectMilestone::STATUS_PENDING,
                ProjectMilestone::STATUS_DONE,
                ProjectMilestone::STATUS_MISSED,
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $milestone = $this->milestones->create($request->user(), $project, $data);

        return ApiResponse::success($this->milestones->serialize($milestone), 201);
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('manageMembers', $project);
        $this->assertBelongsToProject($project, $milestone);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in([
                ProjectMilestone::STATUS_PENDING,
                ProjectMilestone::STATUS_DONE,
                ProjectMilestone::STATUS_MISSED,
            ])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $milestone = $this->milestones->update($milestone, $data);

        return ApiResponse::success($this->milestones->serialize($milestone));
    }

    public function destroy(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('manageMembers', $project);
        $this->assertBelongsToProject($project, $milestone);

        $this->milestones->delete($milestone);

        return ApiResponse::success(['deleted' => true]);
    }

    private function assertCanOversee(Request $request): void
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole) || ! $user->role->canOverseeProjects()) {
            abort(403);
        }
    }

    private function assertBelongsToProject(Project $project, ProjectMilestone $milestone): void
    {
        if ((int) $milestone->project_id !== (int) $project->id) {
            abort(404);
        }
    }
}
