<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\BlogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBlogController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->blog->paginatePublic($request->query());

        $categories = BlogCategory::query()
            ->active()
            ->whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        $tags = BlogTag::query()
            ->whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success([
            'items' => collect($page->items())->map(
                fn (BlogPost $post) => (new BlogPostResource($post, false))->resolve($request)
            )->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'filters' => [
                'categories' => $categories->map(fn (BlogCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'posts_count' => $category->posts_count,
                ])->values(),
                'tags' => $tags->map(fn (BlogTag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'posts_count' => $tag->posts_count,
                ])->values(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->with(['author', 'category', 'tags'])
            ->firstOrFail();

        $payload = (new BlogPostResource($post, true))->resolve($request);
        $payload['related'] = $this->blog->relatedPosts($post)->map(
            fn (BlogPost $related) => (new BlogPostResource($related, false))->resolve($request)
        )->values()->all();

        return ApiResponse::success($payload);
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $category = BlogCategory::query()->active()->where('slug', $slug)->firstOrFail();

        $filters = $request->query();
        $filters['category'] = $category->slug;
        $page = $this->blog->paginatePublic($filters);

        return ApiResponse::success([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'items' => collect($page->items())->map(
                fn (BlogPost $post) => (new BlogPostResource($post, false))->resolve($request)
            )->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
