<?php

namespace App\Services\Operations;

use App\Models\OperationsAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OperationsAuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(?User $actor, string $action, ?Model $subject = null, array $meta = []): OperationsAuditLog
    {
        return OperationsAuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
