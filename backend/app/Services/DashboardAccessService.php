<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\RoleDashboardAccess;
use App\Models\User;
use App\Support\DashboardModules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DashboardAccessService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return list<array{key: string, label: string, shell: string, description: string|null}>
     */
    public function catalog(): array
    {
        $rows = [];
        foreach (DashboardModules::definitions() as $key => $meta) {
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'shell' => $meta['shell'],
                'description' => $meta['description'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function configurableRoles(): array
    {
        return UserRole::assignableStaffValues();
    }

    /**
     * Effective access map for a role (defaults merged with DB overrides).
     *
     * @return array<string, bool>
     */
    public function forRole(UserRole|string $role): array
    {
        $roleEnum = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
        if ($roleEnum === null) {
            return array_fill_keys(DashboardModules::keys(), false);
        }

        if ($roleEnum === UserRole::Owner) {
            return array_map(static fn (): bool => true, array_fill_keys(DashboardModules::keys(), true));
        }

        $cacheKey = 'role_dashboard_access:'.$roleEnum->value;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($roleEnum): array {
            $access = DashboardModules::defaultsForRole($roleEnum);
            $overrides = RoleDashboardAccess::query()
                ->where('role', $roleEnum->value)
                ->get(['module_key', 'enabled']);

            foreach ($overrides as $row) {
                if (array_key_exists($row->module_key, $access)) {
                    $access[$row->module_key] = (bool) $row->enabled;
                }
            }

            return $access;
        });
    }

    /**
     * @return array<string, bool>
     */
    public function forUser(User $user): array
    {
        if (! $user->role instanceof UserRole) {
            return array_fill_keys(DashboardModules::keys(), false);
        }

        if ($user->role === UserRole::Owner) {
            return array_map(static fn (): bool => true, array_fill_keys(DashboardModules::keys(), true));
        }

        if ($user->role === UserRole::Customer || $user->role === UserRole::Supplier) {
            return array_fill_keys(DashboardModules::keys(), false);
        }

        return $this->forRole($user->role);
    }

    public function canAccess(User $user, string $moduleKey): bool
    {
        if (! DashboardModules::isValid($moduleKey)) {
            return false;
        }

        if ($user->role instanceof UserRole && $user->role === UserRole::Owner) {
            return true;
        }

        return (bool) ($this->forUser($user)[$moduleKey] ?? false);
    }

    public function canAccessPath(User $user, string $path): bool
    {
        $module = DashboardModules::moduleForPath($path);
        if ($module === null) {
            return true;
        }

        return $this->canAccess($user, $module);
    }

    /**
     * @param  array<string, bool>  $modules
     * @return array<string, bool>
     */
    public function updateRole(UserRole $role, array $modules): array
    {
        if ($role === UserRole::Owner) {
            throw ValidationException::withMessages([
                'role' => ['Owner dashboard access cannot be restricted.'],
            ]);
        }

        if (! in_array($role->value, $this->configurableRoles(), true)) {
            throw ValidationException::withMessages([
                'role' => ['This role cannot be configured for dashboard access.'],
            ]);
        }

        foreach ($modules as $key => $enabled) {
            if (! DashboardModules::isValid((string) $key)) {
                throw ValidationException::withMessages([
                    'modules' => ['Unknown dashboard module: '.$key],
                ]);
            }

            RoleDashboardAccess::query()->updateOrCreate(
                [
                    'role' => $role->value,
                    'module_key' => (string) $key,
                ],
                [
                    'enabled' => (bool) $enabled,
                ],
            );
        }

        Cache::forget('role_dashboard_access:'.$role->value);

        return $this->forRole($role);
    }

    /**
     * @return array{roles: list<array{role: string, modules: array<string, bool>}>, catalog: list<array<string, mixed>>}
     */
    public function matrix(): array
    {
        $roles = [];
        foreach ($this->configurableRoles() as $roleValue) {
            $role = UserRole::from($roleValue);
            $roles[] = [
                'role' => $roleValue,
                'modules' => $this->forRole($role),
            ];
        }

        return [
            'roles' => $roles,
            'catalog' => $this->catalog(),
        ];
    }
}
