<?php

namespace App\Services\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogAuthor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BlogService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function paginateAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = BlogPost::query()
            ->with(['author', 'category', 'tags'])
            ->latest('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['author_id'])) {
            $query->where('author_id', (int) $filters['author_id']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('excerpt', 'like', $term);
            });
        }

        return $query->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 50)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BlogPost>
     */
    public function paginatePublic(array $filters = []): LengthAwarePaginator
    {
        $query = BlogPost::query()
            ->published()
            ->with(['author:id,name,slug,avatar_url', 'category:id,name,slug', 'tags:id,name,slug'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $filters['tag']));
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term)
                    ->orWhere('content', 'like', $term);
            });
        }

        return $query->paginate(max(1, min((int) ($filters['per_page'] ?? 12), 24)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertPost(array $data, ?BlogPost $post = null, ?User $actor = null): BlogPost
    {
        return DB::transaction(function () use ($data, $post, $actor): BlogPost {
            $creating = $post === null;
            $post ??= new BlogPost;

            $title = trim((string) ($data['title'] ?? $post->title ?? ''));
            $slugSource = filled($data['slug'] ?? null) ? (string) $data['slug'] : $title;
            $slug = BlogPost::uniqueSlug($slugSource, $post->id);

            $status = BlogPostStatus::from((string) ($data['status'] ?? $post->status?->value ?? BlogPostStatus::Draft->value));
            $publishedAt = array_key_exists('published_at', $data)
                ? ($data['published_at'] ? Carbon::parse((string) $data['published_at']) : null)
                : $post->published_at;

            if ($status === BlogPostStatus::Published && $publishedAt === null) {
                $publishedAt = now();
            }

            if ($status === BlogPostStatus::Scheduled && $publishedAt !== null && $publishedAt->lte(now())) {
                $status = BlogPostStatus::Published;
            }

            if ($status === BlogPostStatus::Scheduled && $publishedAt === null) {
                $status = BlogPostStatus::Draft;
            }

            $post->fill([
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $this->nullableString($data['excerpt'] ?? $post->excerpt),
                'content' => (string) ($data['content'] ?? $post->content ?? ''),
                'featured_image' => $this->nullableString($data['featured_image'] ?? $post->featured_image),
                'author_id' => $data['author_id'] ?? $post->author_id,
                'category_id' => $data['category_id'] ?? $post->category_id,
                'status' => $status,
                'published_at' => $publishedAt,
                'seo_title' => $this->nullableString($data['seo_title'] ?? $post->seo_title),
                'meta_description' => $this->nullableString($data['meta_description'] ?? $post->meta_description),
                'og_image' => $this->nullableString($data['og_image'] ?? $post->og_image),
                'canonical_url' => $this->nullableString($data['canonical_url'] ?? $post->canonical_url),
            ]);

            if ($creating && $actor !== null) {
                $post->created_by = $actor->id;
            }

            $post->save();

            if (array_key_exists('tag_ids', $data)) {
                $tagIds = collect($data['tag_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
                $post->tags()->sync($tagIds);
            }

            return $post->fresh(['author', 'category', 'tags', 'creator']) ?? $post;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertCategory(array $data, ?BlogCategory $category = null): BlogCategory
    {
        $category ??= new BlogCategory;
        $name = trim((string) ($data['name'] ?? $category->name ?? ''));
        $slugSource = filled($data['slug'] ?? null) ? (string) $data['slug'] : $name;

        $category->fill([
            'name' => $name,
            'slug' => BlogCategory::uniqueSlug($slugSource, $category->id),
            'description' => $this->nullableString($data['description'] ?? $category->description),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($category->is_active ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $category->sort_order ?? 0),
        ]);
        $category->save();

        return $category->fresh() ?? $category;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertTag(array $data, ?BlogTag $tag = null): BlogTag
    {
        $tag ??= new BlogTag;
        $name = trim((string) ($data['name'] ?? $tag->name ?? ''));
        $slugSource = filled($data['slug'] ?? null) ? (string) $data['slug'] : $name;

        $tag->fill([
            'name' => $name,
            'slug' => BlogTag::uniqueSlug($slugSource, $tag->id),
        ]);
        $tag->save();

        return $tag->fresh() ?? $tag;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertAuthor(array $data, ?BlogAuthor $author = null): BlogAuthor
    {
        $author ??= new BlogAuthor;
        $name = trim((string) ($data['name'] ?? $author->name ?? ''));
        $slugSource = filled($data['slug'] ?? null) ? (string) $data['slug'] : $name;

        $author->fill([
            'name' => $name,
            'slug' => BlogAuthor::uniqueSlug($slugSource, $author->id),
            'bio' => $this->nullableString($data['bio'] ?? $author->bio),
            'avatar_url' => $this->nullableString($data['avatar_url'] ?? $author->avatar_url),
            'email' => $this->nullableString($data['email'] ?? $author->email),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($author->is_active ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $author->sort_order ?? 0),
        ]);
        $author->save();

        return $author->fresh() ?? $author;
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function relatedPosts(BlogPost $post, int $limit = 3): Collection
    {
        return BlogPost::query()
            ->published()
            ->whereKeyNot($post->id)
            ->with(['author:id,name,slug', 'category:id,name,slug'])
            ->where(function ($query) use ($post): void {
                if ($post->category_id) {
                    $query->where('category_id', $post->category_id);
                }
                $tagIds = $post->tags->pluck('id');
                if ($tagIds->isNotEmpty()) {
                    $query->orWhereHas('tags', fn ($q) => $q->whereIn('blog_tags.id', $tagIds));
                }
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
