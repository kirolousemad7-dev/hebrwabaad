<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreTagRequest;
use App\Http\Requests\Content\UpdateTagRequest;
use App\Models\Supplier;
use App\Models\Tag;
use App\Services\SupplierManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierTagController extends Controller
{
    public function __construct(private readonly SupplierManagementService $management) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $tags = Tag::query()
            ->forSuppliers()
            ->orderBy('name')
            ->get();

        return ApiResponse::success([
            'items' => $tags->map(fn (Tag $tag) => $this->serialize($tag))->all(),
        ]);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $tag = $this->management->upsertTag($request->validated());

        return ApiResponse::success($this->serialize($tag), 201);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $this->authorize('create', Supplier::class);
        $this->assertSupplierScope($tag);

        $tag = $this->management->upsertTag($request->validated(), $tag);

        return ApiResponse::success($this->serialize($tag));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('create', Supplier::class);
        $this->assertSupplierScope($tag);

        $tag->suppliers()->detach();
        $tag->delete();

        return ApiResponse::success(null);
    }

    private function assertSupplierScope(Tag $tag): void
    {
        if ($tag->scope !== 'supplier') {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'scope' => $tag->scope,
            'color' => $tag->color,
            'is_active' => $tag->is_active,
            'created_at' => $tag->created_at?->toIso8601String(),
            'updated_at' => $tag->updated_at?->toIso8601String(),
        ];
    }
}
