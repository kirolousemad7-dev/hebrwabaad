<?php

namespace App\Services\Operations;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(?bool $activeOnly = null): array
    {
        $query = Department::query()->with('manager:id,name')->orderBy('sort_order')->orderBy('name');

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->get()->map(fn (Department $department) => $this->serialize($department))->all();
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function options(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'slug' => $department->slug,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Department
    {
        $name = trim((string) $attributes['name']);
        $slug = $this->uniqueSlug($attributes['slug'] ?? $name);

        $department = Department::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'manager_id' => $attributes['manager_id'] ?? null,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'sort_order' => (int) ($attributes['sort_order'] ?? 0),
        ]);

        return $department->load('manager:id,name');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Department $department, array $attributes): Department
    {
        if (array_key_exists('name', $attributes)) {
            $department->name = trim((string) $attributes['name']);
        }

        if (array_key_exists('slug', $attributes) && is_string($attributes['slug']) && $attributes['slug'] !== '') {
            $department->slug = $this->uniqueSlug($attributes['slug'], $department->id);
        }

        if (array_key_exists('description', $attributes)) {
            $department->description = $attributes['description'];
        }

        if (array_key_exists('manager_id', $attributes)) {
            $department->manager_id = $attributes['manager_id'];
        }

        if (array_key_exists('is_active', $attributes)) {
            $department->is_active = (bool) $attributes['is_active'];
        }

        if (array_key_exists('sort_order', $attributes)) {
            $department->sort_order = (int) $attributes['sort_order'];
        }

        $department->save();

        return $department->fresh(['manager:id,name']) ?? $department;
    }

    public function delete(Department $department): void
    {
        User::query()->where('department_id', $department->id)->update(['department_id' => null]);
        $department->delete();
    }

    public function assignEmployee(User $employee, ?int $departmentId): User
    {
        if ($departmentId !== null) {
            $department = Department::query()->where('is_active', true)->find($departmentId);
            if ($department === null) {
                throw ValidationException::withMessages([
                    'department_id' => ['Selected department is not available.'],
                ]);
            }
        }

        if (! $employee->isEmployee() && ! $employee->isOwner()) {
            throw ValidationException::withMessages([
                'user_id' => ['Only staff accounts can be assigned to a department.'],
            ]);
        }

        $employee->department_id = $departmentId;
        $employee->save();

        return $employee->fresh(['department']) ?? $employee;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'slug' => $department->slug,
            'description' => $department->description,
            'manager_id' => $department->manager_id,
            'manager' => $department->manager ? [
                'id' => $department->manager->id,
                'name' => $department->manager->name,
            ] : null,
            'is_active' => (bool) $department->is_active,
            'sort_order' => (int) $department->sort_order,
            'employees_count' => (int) ($department->employees_count ?? $department->employees()->count()),
            'created_at' => $department->created_at?->toIso8601String(),
            'updated_at' => $department->updated_at?->toIso8601String(),
        ];
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'department';
        }

        $slug = $base;
        $i = 1;
        while (
            Department::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
