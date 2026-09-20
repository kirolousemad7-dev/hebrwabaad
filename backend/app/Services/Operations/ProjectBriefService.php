<?php

namespace App\Services\Operations;

use App\Models\ManagedFile;
use App\Models\Project;
use App\Models\ProjectReference;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProjectBriefService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMetadata(Project $project, array $attributes): Project
    {
        $payload = [];

        foreach (['brief', 'client_profile', 'requirements', 'scope'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $this->normalizeAssociative($attributes[$field]);
            }
        }

        if ($payload !== []) {
            $project->update($payload);
        }

        return $project->fresh() ?? $project;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(Project $project): array
    {
        return [
            'brief' => is_array($project->brief) ? $project->brief : [],
            'client_profile' => is_array($project->client_profile) ? $project->client_profile : [],
            'requirements' => is_array($project->requirements) ? $project->requirements : [],
            'scope' => is_array($project->scope) ? $project->scope : [],
        ];
    }

    /**
     * Client-safe subset of project metadata (no internal-only keys).
     *
     * @return array<string, mixed>
     */
    public function clientVisibleMetadata(Project $project): array
    {
        $brief = is_array($project->brief) ? $project->brief : [];
        $profile = is_array($project->client_profile) ? $project->client_profile : [];

        return [
            'client_profile' => array_intersect_key($profile, array_flip([
                'company_name',
                'business_description',
                'industry',
                'location',
                'website',
            ])),
            'brief' => array_intersect_key($brief, array_flip([
                'objective',
                'desired_outcome',
                'deliverables',
            ])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReferences(Project $project, bool $clientVisibleOnly = false): array
    {
        $query = ProjectReference::query()
            ->with(['file:id,original_name,disk,path', 'creator:id,name'])
            ->where('project_id', $project->id)
            ->orderByDesc('id');

        if ($clientVisibleOnly) {
            $query->where('is_client_visible', true);
        }

        return $query->get()
            ->map(fn (ProjectReference $row) => $this->serializeReference($row))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createReference(User $actor, Project $project, array $attributes): ProjectReference
    {
        $type = (string) ($attributes['type'] ?? ProjectReference::TYPE_OTHER);
        $this->assertReferenceType($type);

        if (! empty($attributes['file_id'])) {
            $this->assertFileBelongsToProject($project, (int) $attributes['file_id']);
        }

        return ProjectReference::query()->create([
            'project_id' => $project->id,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'url' => $attributes['url'] ?? null,
            'type' => $type,
            'category' => $attributes['category'] ?? null,
            'is_client_visible' => (bool) ($attributes['is_client_visible'] ?? false),
            'file_id' => $attributes['file_id'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateReference(Project $project, ProjectReference $reference, array $attributes): ProjectReference
    {
        if ((int) $reference->project_id !== (int) $project->id) {
            abort(404);
        }

        $payload = [];

        foreach (['title', 'description', 'url', 'category', 'file_id'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('type', $attributes)) {
            $type = (string) $attributes['type'];
            $this->assertReferenceType($type);
            $payload['type'] = $type;
        }

        if (array_key_exists('is_client_visible', $attributes)) {
            $payload['is_client_visible'] = (bool) $attributes['is_client_visible'];
        }

        if (! empty($payload['file_id'])) {
            $this->assertFileBelongsToProject($project, (int) $payload['file_id']);
        }

        $reference->update($payload);

        return $reference->fresh(['file:id,original_name,disk,path', 'creator:id,name']) ?? $reference;
    }

    public function deleteReference(Project $project, ProjectReference $reference): void
    {
        if ((int) $reference->project_id !== (int) $project->id) {
            abort(404);
        }

        $reference->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeReference(ProjectReference $reference): array
    {
        return [
            'id' => $reference->id,
            'project_id' => $reference->project_id,
            'title' => $reference->title,
            'description' => $reference->description,
            'url' => $reference->url,
            'type' => $reference->type,
            'category' => $reference->category,
            'is_client_visible' => (bool) $reference->is_client_visible,
            'file_id' => $reference->file_id,
            'file' => $reference->relationLoaded('file') && $reference->file
                ? [
                    'id' => $reference->file->id,
                    'original_name' => $reference->file->original_name,
                ]
                : null,
            'created_by' => $reference->created_by,
            'creator' => $reference->relationLoaded('creator') && $reference->creator
                ? ['id' => $reference->creator->id, 'name' => $reference->creator->name]
                : null,
            'created_at' => $reference->created_at?->toIso8601String(),
            'updated_at' => $reference->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeAssociative(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'payload' => ['Expected an object/array of fields.'],
            ]);
        }

        return $value;
    }

    private function assertReferenceType(string $type): void
    {
        if (! in_array($type, [
            ProjectReference::TYPE_DESIGN,
            ProjectReference::TYPE_VIDEO,
            ProjectReference::TYPE_WEBSITE,
            ProjectReference::TYPE_CONTENT,
            ProjectReference::TYPE_BRANDING,
            ProjectReference::TYPE_OTHER,
        ], true)) {
            throw ValidationException::withMessages([
                'type' => ['Invalid reference type.'],
            ]);
        }
    }

    private function assertFileBelongsToProject(Project $project, int $fileId): void
    {
        $exists = ManagedFile::query()
            ->where('id', $fileId)
            ->where('project_id', $project->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'file_id' => ['Selected file does not belong to this project.'],
            ]);
        }
    }
}
