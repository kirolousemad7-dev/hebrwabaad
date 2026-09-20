<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasMedia;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable([
    'title',
    'description',
    'brief',
    'client_profile',
    'requirements',
    'scope',
    'customer_id',
    'account_manager_id',
    'status',
    'started_at',
    'deadline',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'started_at' => 'date',
            'deadline' => 'date',
            'brief' => 'array',
            'client_profile' => 'array',
            'requirements' => 'array',
            'scope' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ManagedFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ManagedFile::class);
    }

    /**
     * @return HasMany<ProjectPhase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    /**
     * @return HasMany<ProjectReference, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(ProjectReference::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    /**
     * @return array{total: int, todo: int, in_progress: int, review: int, revision: int, completed: int, overdue: int, percent: float}
     */
    public function progress(bool $clientVisibleOnly = false): array
    {
        if ($clientVisibleOnly) {
            $base = $this->tasks()->where('is_client_visible', true);
            $total = (clone $base)->count();
            $todo = (clone $base)->where('status', TaskStatus::Todo->value)->count();
            $inProgress = (clone $base)->where('status', TaskStatus::InProgress->value)->count();
            $review = (clone $base)->where('status', TaskStatus::Review->value)->count();
            $revision = (clone $base)->where('status', TaskStatus::Revision->value)->count();
            $completed = (clone $base)->where('status', TaskStatus::Completed->value)->count();
            $overdue = (clone $base)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString())
                ->count();

            return [
                'total' => $total,
                'todo' => $todo,
                'in_progress' => $inProgress,
                'review' => $review,
                'revision' => $revision,
                'completed' => $completed,
                'overdue' => $overdue,
                'percent' => $total === 0 ? 0.0 : round(($completed / $total) * 100, 1),
            ];
        }

        $total = (int) ($this->tasks_count ?? $this->tasks()->count());
        $todo = (int) ($this->todo_tasks_count ?? $this->tasks()->where('status', TaskStatus::Todo->value)->count());
        $inProgress = (int) ($this->in_progress_tasks_count ?? $this->tasks()->where('status', TaskStatus::InProgress->value)->count());
        $review = (int) ($this->review_tasks_count ?? $this->tasks()->where('status', TaskStatus::Review->value)->count());
        $revision = (int) ($this->revision_tasks_count ?? $this->tasks()->where('status', TaskStatus::Revision->value)->count());
        $completed = (int) ($this->completed_tasks_count ?? $this->tasks()->where('status', TaskStatus::Completed->value)->count());
        $overdue = (int) ($this->overdue_tasks_count ?? $this->tasks()
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', now()->toDateString())
            ->count());

        return [
            'total' => $total,
            'todo' => $todo,
            'in_progress' => $inProgress,
            'review' => $review,
            'revision' => $revision,
            'completed' => $completed,
            'overdue' => $overdue,
            'percent' => $total === 0 ? 0.0 : round(($completed / $total) * 100, 1),
        ];
    }
}
