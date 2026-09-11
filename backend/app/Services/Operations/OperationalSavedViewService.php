<?php

namespace App\Services\Operations;

use App\Models\OperationalSavedView;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OperationalSavedViewService
{
    /**
     * Whitelisted filter keys only — never accept arbitrary SQL/query fragments.
     *
     * @var list<string>
     */
    public const ALLOWED_FILTER_KEYS = [
        'scope',
        'department_id',
        'project_id',
        'status',
        'priority',
        'source',
        'bucket',
        'assigned_to',
        'sort',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function listFor(User $actor, ?string $viewType = null): array
    {
        $query = OperationalSavedView::query()
            ->where(function ($builder) use ($actor): void {
                $builder->where('user_id', $actor->id)
                    ->orWhere('is_shared', true);
            })
            ->orderByDesc('is_pinned')
            ->orderBy('name');

        if ($viewType !== null && $viewType !== '') {
            $query->where('view_type', $viewType);
        }

        return $query->get()->map(fn (OperationalSavedView $view) => $this->serialize($view))->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $actor, array $attributes): OperationalSavedView
    {
        $filters = $this->validateFilters($attributes['filters'] ?? []);

        return OperationalSavedView::query()->create([
            'user_id' => $actor->id,
            'name' => $attributes['name'],
            'view_type' => $attributes['view_type'] ?? 'work',
            'filters' => $filters,
            'sort' => is_array($attributes['sort'] ?? null) ? $attributes['sort'] : null,
            'is_pinned' => (bool) ($attributes['is_pinned'] ?? false),
            'is_shared' => (bool) ($attributes['is_shared'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(OperationalSavedView $view, array $attributes): OperationalSavedView
    {
        $payload = [];

        if (array_key_exists('name', $attributes)) {
            $payload['name'] = $attributes['name'];
        }
        if (array_key_exists('view_type', $attributes)) {
            $payload['view_type'] = $attributes['view_type'];
        }
        if (array_key_exists('filters', $attributes)) {
            $payload['filters'] = $this->validateFilters($attributes['filters'] ?? []);
        }
        if (array_key_exists('sort', $attributes)) {
            $payload['sort'] = is_array($attributes['sort']) ? $attributes['sort'] : null;
        }
        if (array_key_exists('is_pinned', $attributes)) {
            $payload['is_pinned'] = (bool) $attributes['is_pinned'];
        }
        if (array_key_exists('is_shared', $attributes)) {
            $payload['is_shared'] = (bool) $attributes['is_shared'];
        }

        $view->update($payload);

        return $view->fresh() ?? $view;
    }

    public function pin(OperationalSavedView $view, bool $pinned = true): OperationalSavedView
    {
        $view->update(['is_pinned' => $pinned]);

        return $view->fresh() ?? $view;
    }

    public function delete(OperationalSavedView $view): void
    {
        $view->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function validateFilters(mixed $filters): array
    {
        if (! is_array($filters)) {
            throw ValidationException::withMessages([
                'filters' => ['Filters must be an object of allowed keys only.'],
            ]);
        }

        $invalid = array_diff(array_keys($filters), self::ALLOWED_FILTER_KEYS);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'filters' => ['Unsupported filter keys: '.implode(', ', $invalid)],
            ]);
        }

        $clean = [];
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                throw ValidationException::withMessages([
                    'filters.'.$key => ['Filter values must be scalars.'],
                ]);
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(OperationalSavedView $view): array
    {
        return [
            'id' => $view->id,
            'user_id' => $view->user_id,
            'name' => $view->name,
            'view_type' => $view->view_type,
            'filters' => $view->filters ?? [],
            'sort' => $view->sort,
            'is_pinned' => (bool) $view->is_pinned,
            'is_shared' => (bool) $view->is_shared,
            'created_at' => $view->created_at?->toIso8601String(),
            'updated_at' => $view->updated_at?->toIso8601String(),
        ];
    }
}
