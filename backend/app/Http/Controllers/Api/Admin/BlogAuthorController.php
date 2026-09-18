<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\UpsertBlogAuthorRequest;
use App\Models\BlogAuthor;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BlogAuthorController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(): JsonResponse
    {
        $items = BlogAuthor::query()
            ->withCount('posts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (BlogAuthor $author) => [
                'id' => $author->id,
                'name' => $author->name,
                'slug' => $author->slug,
                'bio' => $author->bio,
                'avatar_url' => $author->avatar_url,
                'email' => $author->email,
                'is_active' => $author->is_active,
                'sort_order' => $author->sort_order,
                'posts_count' => $author->posts_count,
            ]);

        return ApiResponse::success($items);
    }

    public function store(UpsertBlogAuthorRequest $request): JsonResponse
    {
        $author = $this->blog->upsertAuthor($request->validated());

        return ApiResponse::success([
            'id' => $author->id,
            'name' => $author->name,
            'slug' => $author->slug,
            'bio' => $author->bio,
            'avatar_url' => $author->avatar_url,
            'email' => $author->email,
            'is_active' => $author->is_active,
            'sort_order' => $author->sort_order,
        ], 201);
    }

    public function update(UpsertBlogAuthorRequest $request, BlogAuthor $blogAuthor): JsonResponse
    {
        $author = $this->blog->upsertAuthor($request->validated(), $blogAuthor);

        return ApiResponse::success([
            'id' => $author->id,
            'name' => $author->name,
            'slug' => $author->slug,
            'bio' => $author->bio,
            'avatar_url' => $author->avatar_url,
            'email' => $author->email,
            'is_active' => $author->is_active,
            'sort_order' => $author->sort_order,
        ]);
    }

    public function destroy(BlogAuthor $blogAuthor): JsonResponse
    {
        $blogAuthor->delete();

        return ApiResponse::success(null);
    }
}
