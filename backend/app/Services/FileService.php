<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CalendarItem;
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

    public function __construct(
        private readonly ProjectActivityService $activities,
    ) {}

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
            'calendar_item_id' => $context['calendar_item_id'],
            'is_client_visible' => $this->resolveClientVisibility($actor, $attributes, $context),
        ]);

        $file = $file->load($this->eagerLoad());
        $this->activities->recordFileUploaded($file, $actor);

        return $file;
    }

    public function updateClientVisibility(User $actor, ManagedFile $file, bool $isClientVisible): ManagedFile
    {
        if ($actor->role === UserRole::Customer) {
            abort(403);
        }

        if (! $actor->can('updateClientVisibility', $file)) {
            abort(403);
        }

        $old = (bool) $file->is_client_visible;
        if ($old === $isClientVisible) {
            return $file->load($this->eagerLoad());
        }

        $file->update(['is_client_visible' => $isClientVisible]);
        $file = $file->fresh($this->eagerLoad()) ?? $file->load($this->eagerLoad());
        $this->activities->recordFileClientVisibilityChanged($file, $actor, $old, $isClientVisible);

        return $file;
    }

    public function download(ManagedFile $file): StreamedResponse
    {
        $this->assertStored($file);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function preview(ManagedFile $file): StreamedResponse
    {
        if (! $file->isPreviewable()) {
            abort(404, __('messages.not_found'));
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
     * @return array{project_id: int|null, order_id: int|null, task_id: int|null, calendar_item_id: int|null}
     */
    private function assertUploadContext(User $actor, array $attributes): array
    {
        $projectId = $attributes['project_id'] ?? null;
        $orderId = $attributes['order_id'] ?? null;
        $taskId = $attributes['task_id'] ?? null;
        $calendarItemId = $attributes['calendar_item_id'] ?? null;
        $filled = collect([$projectId, $orderId, $taskId, $calendarItemId])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->count();

        if ($filled !== 1) {
            throw ValidationException::withMessages([
                'file' => ['Select exactly one project, order, task, or calendar item.'],
            ]);
        }

        if ($calendarItemId !== null) {
            $item = CalendarItem::query()->find((int) $calendarItemId);
            if ($item === null) {
                throw ValidationException::withMessages([
                    'calendar_item_id' => ['Selected calendar item is not available.'],
                ]);
            }

            $canUpdate = $actor->role === UserRole::Owner
                || ($actor->role instanceof UserRole && $actor->role->canManageWorkCalendar())
                || (int) $item->created_by === (int) $actor->id
                || $item->assignees()->where('users.id', $actor->id)->exists();

            if (! $canUpdate) {
                throw ValidationException::withMessages([
                    'calendar_item_id' => ['Selected calendar item is not available.'],
                ]);
            }

            return [
                'project_id' => null,
                'order_id' => null,
                'task_id' => null,
                'calendar_item_id' => $item->id,
            ];
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

                return ['project_id' => $project->id, 'order_id' => null, 'task_id' => null, 'calendar_item_id' => null];
            }

            $order = Order::query()->find((int) $orderId);
            if ($order === null || $order->customer_id !== $actor->id) {
                throw ValidationException::withMessages([
                    'order_id' => ['Selected order is not available.'],
                ]);
            }

            return ['project_id' => $order->project_id, 'order_id' => $order->id, 'task_id' => null, 'calendar_item_id' => null];
        }

        if ($taskId !== null) {
            $task = Task::query()->with('project')->find((int) $taskId);
            if ($task === null || ! $this->staffCanUseTask($actor, $task)) {
                throw ValidationException::withMessages([
                    'task_id' => ['Selected task is not available.'],
                ]);
            }

            return ['project_id' => $task->project_id, 'order_id' => null, 'task_id' => $task->id, 'calendar_item_id' => null];
        }

        if ($projectId !== null) {
            $project = Project::query()->find((int) $projectId);
            if ($project === null || ! $this->staffCanUseProject($actor, $project)) {
                throw ValidationException::withMessages([
                    'project_id' => ['Selected project is not available.'],
                ]);
            }

            return ['project_id' => $project->id, 'order_id' => null, 'task_id' => null, 'calendar_item_id' => null];
        }

        $order = Order::query()->find((int) $orderId);
        if ($order === null || ! $this->staffCanUseOrder($actor, $order)) {
            throw ValidationException::withMessages([
                'order_id' => ['Selected order is not available.'],
            ]);
        }

        return ['project_id' => $order->project_id, 'order_id' => $order->id, 'task_id' => null, 'calendar_item_id' => null];
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
            return $project->account_manager_id === $actor->id
                || $project->members()->where('user_id', $actor->id)->exists();
        }

        return $project->members()->where('user_id', $actor->id)->exists()
            || $project->tasks()->where('assigned_to', $actor->id)->exists();
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
            $query->where('is_client_visible', true)
                ->where(function (Builder $inner) use ($user): void {
                    $inner->whereHas('project', fn (Builder $project) => $project->where('customer_id', $user->id))
                        ->orWhereHas('order', fn (Builder $order) => $order->where('customer_id', $user->id));
                });

            return;
        }

        if ($user->role === UserRole::AccountManager) {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->whereHas('project', function (Builder $project) use ($user): void {
                    $project->where('account_manager_id', $user->id)
                        ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
                })->orWhereHas('order', fn (Builder $order) => $order->where('account_manager_id', $user->id));
            });

            return;
        }

        $query->where(function (Builder $inner) use ($user): void {
            $inner->whereHas('task', fn (Builder $task) => $task->where('assigned_to', $user->id))
                ->orWhereHas('project', function (Builder $project) use ($user): void {
                    $project->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id))
                        ->orWhereHas(
                            'tasks',
                            fn (Builder $task) => $task->where('assigned_to', $user->id),
                        );
                });
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

        if (isset($filters['calendar_item_id']) && $filters['calendar_item_id'] !== '') {
            $query->where('calendar_item_id', (int) $filters['calendar_item_id']);
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
            abort(404, __('messages.not_found'));
        }
    }

    /**
     * Customers always create client-visible files (their own uploads).
     * Staff may mark client-visible only when Owner or the managing Account Manager.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{project_id: int|null, order_id: int|null, task_id: int|null, calendar_item_id: int|null}  $context
     */
    private function resolveClientVisibility(User $actor, array $attributes, array $context): bool
    {
        if ($actor->role === UserRole::Customer) {
            return true;
        }

        $requested = filter_var($attributes['is_client_visible'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $requested) {
            return false;
        }

        if ($actor->role === UserRole::Owner) {
            return true;
        }

        if ($actor->role === UserRole::AccountManager) {
            if ($context['project_id'] !== null) {
                $project = Project::query()->find((int) $context['project_id']);
                if ($project !== null && (int) $project->account_manager_id === (int) $actor->id) {
                    return true;
                }
            }

            if ($context['order_id'] !== null) {
                $order = Order::query()->find((int) $context['order_id']);
                if ($order !== null && (int) $order->account_manager_id === (int) $actor->id) {
                    return true;
                }
            }
        }

        throw ValidationException::withMessages([
            'is_client_visible' => ['You are not allowed to mark files as client visible.'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 15), 50));
    }
}
