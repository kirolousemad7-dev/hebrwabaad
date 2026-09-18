<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\UpsertBlogPostRequest;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->blog->paginateAdmin($request->query());

        return ApiResponse::success([
            'items' => collect($page->items())->map(
                fn (BlogPost $post) => (new BlogPostResource($post, true))->resolve($request)
            )->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, BlogPost $blogPost): JsonResponse
    {
        $blogPost->load(['author', 'category', 'tags', 'creator']);

        return ApiResponse::success((new BlogPostResource($blogPost, true))->resolve($request));
    }

    public function store(UpsertBlogPostRequest $request): JsonResponse
    {
        $post = $this->blog->upsertPost($request->validated(), null, $request->user());

        return ApiResponse::success((new BlogPostResource($post, true))->resolve($request), 201);
    }

    public function update(UpsertBlogPostRequest $request, BlogPost $blogPost): JsonResponse
    {
        $post = $this->blog->upsertPost($request->validated(), $blogPost, $request->user());

        return ApiResponse::success((new BlogPostResource($post, true))->resolve($request));
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $blogPost->delete();

        return ApiResponse::success(null);
    }
}
