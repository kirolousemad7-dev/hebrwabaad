<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class EmployeeService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = is_string($filters['sort'] ?? null) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'created_at', 'is_active', 'email'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 50));

        $query = User::query()
            ->employees()
            ->withMax('tokens', 'last_used_at');

        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $role = $filters['role'] ?? null;
        if (is_string($role) && in_array($role, UserRole::assignableStaffValues(), true)) {
            $query->where('role', $role);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    public function directorySummary(): array
    {
        $base = User::query()->employees();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        $this->assertAssignableRole($attributes['role']);

        $employee = User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'role' => $attributes['role'],
            'is_active' => $attributes['is_active'] ?? true,
        ]);

        return $this->employeeQuery()->findOrFail($employee->id);
    }

    public function findEmployee(User $user): User
    {
        $this->assertManageable($user);

        $employee = $this->employeeQuery()->find($user->id);

        if ($employee === null) {
            abort(404, 'Not found.');
        }

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $employee = $this->findEmployee($user);
        $this->assertAssignableRole($attributes['role']);
        $this->assertOwnerSafety($employee, $attributes['role'], $employee->is_active);

        $employee->update([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'role' => $attributes['role'],
        ]);

        return $this->employeeQuery()->findOrFail($employee->id);
    }

    public function setActive(User $user, bool $isActive): User
    {
        $employee = $this->findEmployee($user);
        $this->assertOwnerSafety($employee, $employee->role, $isActive);

        $employee->update(['is_active' => $isActive]);

        if (! $isActive) {
            PersonalAccessToken::query()
                ->where('tokenable_type', $employee->getMorphClass())
                ->where('tokenable_id', $employee->id)
                ->delete();
        }

        return $this->employeeQuery()->findOrFail($employee->id);
    }

    /**
     * @return Builder<User>
     */
    private function employeeQuery()
    {
        return User::query()->employees()->withMax('tokens', 'last_used_at');
    }

    private function assertManageable(User $user): void
    {
        if ($user->isOwner() || ! $user->isEmployee()) {
            abort(404, 'Not found.');
        }
    }

    private function assertAssignableRole(mixed $role): void
    {
        $value = $role instanceof UserRole ? $role->value : (string) $role;

        if (! in_array($value, UserRole::assignableStaffValues(), true)) {
            throw ValidationException::withMessages([
                'role' => ['The selected role is invalid.'],
            ]);
        }
    }

    private function assertOwnerSafety(User $user, mixed $nextRole, bool $willBeActive): void
    {
        if (! $user->isOwner()) {
            return;
        }

        $remainingOwners = User::query()
            ->where('role', UserRole::Owner)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->count();

        $nextRoleValue = $nextRole instanceof UserRole ? $nextRole->value : (string) $nextRole;
        $keepsOwnerRole = $nextRoleValue === UserRole::Owner->value;

        if ((! $willBeActive || ! $keepsOwnerRole) && $remainingOwners === 0) {
            throw ValidationException::withMessages([
                'role' => ['The platform must keep at least one active owner.'],
            ]);
        }
    }
}
