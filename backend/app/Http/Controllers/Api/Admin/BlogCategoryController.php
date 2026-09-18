<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\UpsertBlogCategoryRequest;
use App\Models\BlogCategory;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BlogCategoryController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(): JsonResponse
    {
        $items = BlogCategory::query()
            ->withCount('posts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (BlogCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'posts_count' => $category->posts_count,
            ]);

        return ApiResponse::success($items);
    }

    public function store(UpsertBlogCategoryRequest $request): JsonResponse
    {
        $category = $this->blog->upsertCategory($request->validated());

        return ApiResponse::success([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
        ], 201);
    }

    public function update(UpsertBlogCategoryRequest $request, BlogCategory $blogCategory): JsonResponse
    {
        $category = $this->blog->upsertCategory($request->validated(), $blogCategory);

        return ApiResponse::success([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
        ]);
    }

    public function destroy(BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory->delete();

        return ApiResponse::success(null);
    }
}
