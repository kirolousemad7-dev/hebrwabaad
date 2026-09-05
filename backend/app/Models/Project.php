<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'customer_id', 'account_manager_id', 'status', 'started_at', 'deadline'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'started_at' => 'date',
            'deadline' => 'date',
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
     * @return array{total: int, todo: int, in_progress: int, review: int, revision: int, completed: int, overdue: int, percent: float}
     */
    public function progress(): array
    {
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
