<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\DashboardAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof UserRole ? $this->role->value : $this->role;
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'is_active' => (bool) $this->is_active,
            'workspace' => $this->role instanceof UserRole ? $this->role->workspaceKey() : null,
        ];

        // Internal staff only — never attach dashboard ACL to customers/suppliers.
        if ($this->role instanceof UserRole
            && $this->role !== UserRole::Customer
            && $this->role !== UserRole::Supplier
        ) {
            $payload['dashboard_access'] = app(DashboardAccessService::class)->forUser($this->resource);
        }

        return $payload;
    }
}
