<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\UpsertBlogTagRequest;
use App\Models\BlogTag;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BlogTagController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(): JsonResponse
    {
        $items = BlogTag::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->map(fn (BlogTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'posts_count' => $tag->posts_count,
            ]);

        return ApiResponse::success($items);
    }

    public function store(UpsertBlogTagRequest $request): JsonResponse
    {
        $tag = $this->blog->upsertTag($request->validated());

        return ApiResponse::success([
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ], 201);
    }

    public function update(UpsertBlogTagRequest $request, BlogTag $blogTag): JsonResponse
    {
        $tag = $this->blog->upsertTag($request->validated(), $blogTag);

        return ApiResponse::success([
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ]);
    }

    public function destroy(BlogTag $blogTag): JsonResponse
    {
        $blogTag->delete();

        return ApiResponse::success(null);
    }
}
