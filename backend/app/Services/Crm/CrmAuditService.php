<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CrmAuditService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmAuditLog>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            throw new AuthorizationException('Only CRM managers can view audit logs.');
        }

        $query = CrmAuditLog::query()->with('user:id,name,email')->latest('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        return $query->paginate(max(1, min((int) ($filters['per_page'] ?? 25), 100)));
    }
}
