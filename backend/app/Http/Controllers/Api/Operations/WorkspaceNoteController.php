<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectReference;
use App\Models\Task;
use App\Models\WorkspaceNote;
use App\Services\ProjectActivityService;
use App\Support\ApiResponse;
use App\Support\ProjectActivityAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkspaceNoteController extends Controller
{
    public function __construct(private readonly ProjectActivityService $activities) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $taskId = $request->integer('task_id') ?: null;
        if ($taskId !== null) {
            $task = $this->taskOnProject($project, $taskId);
            $this->authorize('view', $task);
        }

        $notes = WorkspaceNote::query()
            ->with('author:id,name')
            ->where('project_id', $project->id)
            ->when(
                $taskId !== null,
                fn ($query) => $query->where('task_id', $taskId),
                fn ($query) => $query->whereNull('task_id'),
            )
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceNote $note) => $this->serialize($note))
            ->all();

        return ApiResponse::success(['items' => $notes]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'task_id' => ['nullable', 'integer'],
        ]);

        $taskId = isset($data['task_id']) ? (int) $data['task_id'] : null;
        $task = null;
        if ($taskId !== null) {
            $task = $this->taskOnProject($project, $taskId);
            $this->authorize('comment', $task);
        } else {
            $this->authorize('update', $project);
        }

        $note = WorkspaceNote::query()->create([
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);
        $note->load('author:id,name');

        $excerpt = mb_strlen($note->body) > 180 ? mb_substr($note->body, 0, 180).'…' : $note->body;
        $this->activities->recordUserAction(
            project: $project,
            user: $request->user(),
            action: ProjectActivityAction::NOTE_CREATED,
            entityType: $task ? 'task' : 'project',
            entityId: $task?->id ?? $project->id,
            description: 'تمت إضافة ملاحظة: '.$excerpt,
            metadata: ['task_id' => $task?->id],
        );

        return ApiResponse::success($this->serialize($note), 201);
    }

    public function syncTaskReferences(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertTaskBelongsToProject($project, $task);
        $this->authorize('comment', $task);

        $data = $request->validate([
            'reference_ids' => ['present', 'array'],
            'reference_ids.*' => ['integer'],
        ]);

        $ids = collect($data['reference_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $allowed = ProjectReference::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ids)
            ->pluck('id');

        if ($allowed->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'reference_ids' => ['يمكن ربط مراجع هذا المشروع فقط.'],
            ]);
        }

        $task->references()->sync($allowed->all());

        $this->activities->recordUserAction(
            project: $project,
            user: $request->user(),
            action: ProjectActivityAction::REFERENCE_LINKED,
            entityType: 'task',
            entityId: $task->id,
            description: 'تم تحديث مراجع المهمة',
            metadata: ['reference_ids' => $allowed->all()],
        );

        return ApiResponse::success([
            'task_id' => $task->id,
            'reference_ids' => $allowed->all(),
        ]);
    }

    private function taskOnProject(Project $project, int $taskId): Task
    {
        $task = Task::query()->where('project_id', $project->id)->whereKey($taskId)->first();
        if ($task === null) {
            abort(404);
        }

        return $task;
    }

    private function assertTaskBelongsToProject(Project $project, Task $task): void
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkspaceNote $note): array
    {
        return [
            'id' => $note->id,
            'project_id' => $note->project_id,
            'task_id' => $note->task_id,
            'body' => $note->body,
            'author' => $note->author ? [
                'id' => $note->author->id,
                'name' => $note->author->name,
            ] : null,
            'created_at' => $note->created_at?->toIso8601String(),
        ];
    }
}
