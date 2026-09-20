<?php

namespace App\Http\Controllers\Api\Operations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectReference;
use App\Services\Operations\ProjectBriefService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectBriefController extends Controller
{
    public function __construct(
        private readonly ProjectBriefService $brief,
    ) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('view', $project);

        return ApiResponse::success($this->brief->metadata($project));
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('update', $project);

        $data = $request->validate([
            'brief' => ['sometimes', 'nullable', 'array'],
            'client_profile' => ['sometimes', 'nullable', 'array'],
            'requirements' => ['sometimes', 'nullable', 'array'],
            'scope' => ['sometimes', 'nullable', 'array'],
        ]);

        $project = $this->brief->updateMetadata($project, $data);

        return ApiResponse::success($this->brief->metadata($project));
    }

    public function references(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('view', $project);

        return ApiResponse::success([
            'items' => $this->brief->listReferences($project),
        ]);
    }

    public function storeReference(Request $request, Project $project): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('update', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'type' => ['nullable', 'string', Rule::in([
                ProjectReference::TYPE_DESIGN,
                ProjectReference::TYPE_VIDEO,
                ProjectReference::TYPE_WEBSITE,
                ProjectReference::TYPE_CONTENT,
                ProjectReference::TYPE_BRANDING,
                ProjectReference::TYPE_OTHER,
            ])],
            'category' => ['nullable', 'string', 'max:80'],
            'is_client_visible' => ['nullable', 'boolean'],
            'file_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $reference = $this->brief->createReference($request->user(), $project, $data);

        return ApiResponse::success($this->brief->serializeReference($reference), 201);
    }

    public function updateReference(Request $request, Project $project, ProjectReference $reference): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('update', $project);
        $this->assertReferenceBelongsToProject($project, $reference);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'type' => ['sometimes', 'string', Rule::in([
                ProjectReference::TYPE_DESIGN,
                ProjectReference::TYPE_VIDEO,
                ProjectReference::TYPE_WEBSITE,
                ProjectReference::TYPE_CONTENT,
                ProjectReference::TYPE_BRANDING,
                ProjectReference::TYPE_OTHER,
            ])],
            'category' => ['nullable', 'string', 'max:80'],
            'is_client_visible' => ['nullable', 'boolean'],
            'file_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $reference = $this->brief->updateReference($project, $reference, $data);

        return ApiResponse::success($this->brief->serializeReference($reference));
    }

    public function destroyReference(Request $request, Project $project, ProjectReference $reference): JsonResponse
    {
        $this->assertCanOversee($request);
        $this->authorize('update', $project);
        $this->assertReferenceBelongsToProject($project, $reference);

        $this->brief->deleteReference($project, $reference);

        return ApiResponse::success(['deleted' => true]);
    }

    private function assertReferenceBelongsToProject(Project $project, ProjectReference $reference): void
    {
        if ((int) $reference->project_id !== (int) $project->id) {
            abort(404);
        }
    }

    private function assertCanOversee(Request $request): void
    {
        $user = $request->user();
        if (! ($user->role instanceof UserRole) || ! $user->role->canOverseeProjects()) {
            abort(403);
        }
    }
}
