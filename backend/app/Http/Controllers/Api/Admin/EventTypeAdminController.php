<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventTypeAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            EventType::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::success($this->upsert($request->all()), status: 201);
    }

    public function update(Request $request, EventType $eventType): JsonResponse
    {
        return ApiResponse::success($this->upsert($request->all(), $eventType));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsert(array $data, ?EventType $eventType = null): EventType
    {
        $nameAr = trim(strip_tags((string) ($data['name_ar'] ?? '')));
        if ($nameAr === '') {
            throw ValidationException::withMessages(['name_ar' => ['Required.']]);
        }

        $attributes = [
            'slug' => trim((string) ($data['slug'] ?? '')) ?: Str::slug($nameAr),
            'name_ar' => $nameAr,
            'name_en' => isset($data['name_en']) ? trim(strip_tags((string) $data['name_en'])) : null,
            'description' => isset($data['description']) ? trim(strip_tags((string) $data['description'])) : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'is_public' => array_key_exists('is_public', $data) ? (bool) $data['is_public'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($eventType === null) {
            return EventType::query()->create($attributes);
        }

        $eventType->update($attributes);

        return $eventType->fresh();
    }
}
