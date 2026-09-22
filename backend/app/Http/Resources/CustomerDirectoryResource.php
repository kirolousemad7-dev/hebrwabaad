<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer directory / project-selector payload (User with role CUSTOMER).
 *
 * @mixin User
 */
class CustomerDirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof UserRole ? $this->role : UserRole::tryFrom((string) $this->role);
        $hasAccount = $this->resolveHasAccount();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role?->value,
            'workspace' => $role?->workspaceKey(),
            'is_active' => (bool) $this->is_active,
            'has_account' => $hasAccount,
            'account_status' => $hasAccount ? 'ACTIVE' : 'NO_ACCOUNT',
            'projects_count' => (int) ($this->customer_projects_count ?? $this->customerProjects()->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_seen_at' => $this->lastSeenAt()?->toIso8601String(),
        ];
    }

    private function resolveHasAccount(): bool
    {
        if (array_key_exists('tokens_max_last_used_at', $this->getAttributes())
            || isset($this->tokens_max_last_used_at)) {
            return $this->tokens_max_last_used_at !== null;
        }

        return $this->tokens()->whereNotNull('last_used_at')->exists();
    }
}
