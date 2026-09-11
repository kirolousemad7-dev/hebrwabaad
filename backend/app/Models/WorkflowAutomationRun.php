<?php

namespace App\Models;

use App\Enums\WorkflowRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'automation_id',
    'trigger',
    'idempotency_key',
    'source_type',
    'source_id',
    'status',
    'depth',
    'origin_run_id',
    'trigger_chain',
    'is_dry_run',
    'result',
    'executed_at',
])]
class WorkflowAutomationRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowRunStatus::class,
            'result' => 'array',
            'trigger_chain' => 'array',
            'is_dry_run' => 'boolean',
            'depth' => 'integer',
            'origin_run_id' => 'integer',
            'executed_at' => 'datetime',
            'source_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkflowAutomation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(WorkflowAutomation::class, 'automation_id');
    }
}
