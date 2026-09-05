<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\EmployeeWorkspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class WorkspaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::tryFrom((string) $this->role);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role?->value,
            'workspace' => $role?->workspaceKey(),
            'is_active' => (bool) $this->is_active,
            'capabilities' => $role ? EmployeeWorkspace::capabilitiesFor($role) : [],
            'widgets' => $role ? EmployeeWorkspace::widgetIdsFor($role) : [],
            'domains' => $role ? EmployeeWorkspace::domainsFor($role) : [],
        ];
    }
}
