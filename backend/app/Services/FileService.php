<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ManagedFile;
use App\Models\Order;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileService
{
    public const MAX_KILOBYTES = 10240;

    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'];

    /**
     * @return list<string>
     */
    public function eagerLoad(): array
    {
        return ['uploader', 'project', 'order', 'task'];
    }

    /**
     * @return Collection<int, ManagedFile>
     */
    public function recentFor(User $user, int $limit = 6): Collection
    {
        $query = ManagedFile::query()->with($this->eagerLoad());
        $this->scopeVisibleTo($query, $user);

        return $query->latest()->limit($limit)->get();
    }

    public function countFor(User $user): int
    {
        $query = ManagedFile::query();
        $this->scopeVisibleTo($query, $user);

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ManagedFile>
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        $query = ManagedFile::query()->with($this->eagerLoad());
        $this->scopeVisibleTo($query, $user);
        $this->applyContextFilters($query, $filters);

        return $query->latest()->paginate($this->perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function store(User $actor, UploadedFile $upload, array $attributes): ManagedFile
    {
        $context = $this->assertUploadContext($actor, $attributes);
        $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->guessExtension() ?: 'bin');
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $upload->storeAs('files', $storedName, 'local');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $file = ManagedFile::query()->create([
            'uploaded_by' => $actor->id,
            'original_name' => $this->safeOriginalName($upload),
            'stored_name' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size' => $upload->getSize() ?: 0,
            'project_id' => $context['project_id'],
            'order_id' => $context['order_id'],
            'task_id' => $context['task_id'],
        ]);

        return $file->load($this->eagerLoad());
    }

    public function download(ManagedFile $file): StreamedResponse
    {
        $this->assertStored($file);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function preview(ManagedFile $file): StreamedResponse
    {
        if (! $file->isPreviewable()) {
            abort(404, 'Not found.');
        }

        $this->assertStored($file);

        $filename = $this->headerFilename($file);

        return Storage::disk($file->disk)->response(
            $file->path,
            $filename,
            ['Content-Disposition' => 'inline; filename="'.$filename.'"'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{project_id: int|null, order_id: int|null, task_id: int|null}
     */
    private function assertUploadContext(User $actor, array $attributes): array
    {
        $projectId = $attributes['project_id'] ?? null;
        $orderId = $attributes['order_id'] ?? null;
        $taskId = $attributes['task_id'] ?? null;
        $filled = collect([$projectId, $orderId, $taskId])->filter(fn ($value) => $value !== null && $value !== '')->count();

        if ($filled !== 1) {
            throw ValidationException::withMessages([
                'file' => ['Select exactly one project, order, or task.'],
            ]);
        }

        if ($actor->role === UserRole::Customer) {
            if ($taskId !== null) {
                throw ValidationException::withMessages([
                    'task_id' => ['Customers cannot attach files to tasks.'],
                ]);
            }

            if ($projectId !== null) {
                $project = Project::query()->find((int) $projectId);
                if ($project === null || $project->customer_id !== $actor->id) {
                    throw ValidationException::withMessages([
                        'project_id' => ['Selected project is not available.'],
                    ]);
                }

                return ['project_id' => $project->id, 'order_id' => null, 'task_id' => null];
            }

            $order = Order::query()->find((int) $orderId);
            if ($order === null || $order->customer_id !== $actor->id) {
                throw ValidationException::withMessages([
                    'order_id' => ['Selected order is not available.'],
                ]);
            }

            return ['project_id' => $order->project_id, 'order_id' => $order->id, 'task_id' => null];
        }

        if ($taskId !== null) {
            $task = Task::query()->with('project')->find((int) $taskId);
            if ($task === null || ! $this->staffCanUseTask($actor, $task)) {
                throw ValidationException::withMessages([
                    'task_id' => ['Selected task is not available.'],
                ]);
            }

            return ['project_id' => $task->project_id, 'order_id' => null, 'task_id' => $task->id];
        }

        if ($projectId !== null) {
            $project = Project::query()->find((int) $projectId);
            if ($project === null || ! $this->staffCanUseProject($actor, $project)) {
                throw ValidationException::withMessages([
                    'project_id' => ['Selected project is not available.'],
                ]);
            }

            return ['project_id' => $project->id, 'order_id' => null, 'task_id' => null];
        }

        $order = Order::query()->find((int) $orderId);
        if ($order === null || ! $this->staffCanUseOrder($actor, $order)) {
            throw ValidationException::withMessages([
                'order_id' => ['Selected order is not available.'],
            ]);
        }

        return ['project_id' => $order->project_id, 'order_id' => $order->id, 'task_id' => null];
    }

    private function staffCanUseTask(User $actor, Task $task): bool
    {
        if ($actor->role === UserRole::Owner) {
            return true;
        }

        if ($actor->role === UserRole::AccountManager) {
            return $task->project?->account_manager_id === $actor->id || $task->created_by === $actor->id;
        }

        return $task->assigned_to === $actor->id;
    }

    private function staffCanUseProject(User $actor, Project $project): bool
    {
        if ($actor->role === UserRole::Owner) {
            return true;
        }

        if ($actor->role === UserRole::AccountManager) {
            return $project->account_manager_id === $actor->id;
        }

        return $project->tasks()->where('assigned_to', $actor->id)->exists();
    }

    private function staffCanUseOrder(User $actor, Order $order): bool
    {
        if ($actor->role === UserRole::Owner) {
            return true;
        }

        return $actor->role === UserRole::AccountManager && $order->account_manager_id === $actor->id;
    }

    /**
     * @param  Builder<ManagedFile>  $query
     */
    private function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === UserRole::Owner) {
            return;
        }

        if ($user->role === UserRole::Customer) {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->whereHas('project', fn (Builder $project) => $project->where('customer_id', $user->id))
                    ->orWhereHas('order', fn (Builder $order) => $order->where('customer_id', $user->id));
            });

            return;
        }

        if ($user->role === UserRole::AccountManager) {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->whereHas('project', fn (Builder $project) => $project->where('account_manager_id', $user->id))
                    ->orWhereHas('order', fn (Builder $order) => $order->where('account_manager_id', $user->id));
            });

            return;
        }

        $query->where(function (Builder $inner) use ($user): void {
            $inner->whereHas('task', fn (Builder $task) => $task->where('assigned_to', $user->id))
                ->orWhereHas('project', fn (Builder $project) => $project->whereHas(
                    'tasks',
                    fn (Builder $task) => $task->where('assigned_to', $user->id),
                ));
        });
    }

    /**
     * @param  Builder<ManagedFile>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyContextFilters(Builder $query, array $filters): void
    {
        if (isset($filters['project_id']) && $filters['project_id'] !== '') {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if (isset($filters['order_id']) && $filters['order_id'] !== '') {
            $query->where('order_id', (int) $filters['order_id']);
        }

        if (isset($filters['task_id']) && $filters['task_id'] !== '') {
            $query->where('task_id', (int) $filters['task_id']);
        }
    }

    private function headerFilename(ManagedFile $file): string
    {
        return str_replace(['"', "\r", "\n", '\\'], '', $file->original_name);
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());

        return Str::limit($name === '' ? 'file' : $name, 255, '');
    }

    private function assertStored(ManagedFile $file): void
    {
        if (! Storage::disk($file->disk)->exists($file->path)) {
            abort(404, 'Not found.');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 15), 50));
    }
}
