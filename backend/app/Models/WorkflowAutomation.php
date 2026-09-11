<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'trigger',
    'conditions',
    'actions',
    'is_active',
    'is_template',
    'max_depth',
    'max_actions_per_run',
    'created_by',
    'last_run_at',
])]
class WorkflowAutomation extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'is_template' => 'boolean',
            'max_depth' => 'integer',
            'max_actions_per_run' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WorkflowAutomationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowAutomationRun::class, 'automation_id');
    }
}
