<?php

namespace App\Http\Resources;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    public function __construct($resource, private readonly bool $includeContent = true, private readonly bool $includeRelated = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BlogPost $post */
        $post = $this->resource;

        $payload = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'featured_image' => $post->featured_image,
            'status' => $post->status instanceof \BackedEnum ? $post->status->value : $post->status,
            'status_label' => $post->status instanceof BlogPostStatus
                ? $post->status->labelAr()
                : (string) $post->status,
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'seo' => [
                'title' => $post->seo_title,
                'meta_description' => $post->meta_description,
                'og_image' => $post->og_image,
                'canonical_url' => $post->canonical_url,
            ],
            'author' => $this->whenLoaded('author', fn () => $post->author === null ? null : [
                'id' => $post->author->id,
                'name' => $post->author->name,
                'slug' => $post->author->slug,
                'bio' => $post->author->bio,
                'avatar_url' => $post->author->avatar_url,
            ]),
            'category' => $this->whenLoaded('category', fn () => $post->category === null ? null : [
                'id' => $post->category->id,
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $post->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values()),
            'author_id' => $post->author_id,
            'category_id' => $post->category_id,
            'tag_ids' => $this->whenLoaded('tags', fn () => $post->tags->pluck('id')->values()),
        ];

        if ($this->includeContent) {
            $payload['content'] = $post->content;
            $payload['seo_title'] = $post->seo_title;
            $payload['meta_description'] = $post->meta_description;
            $payload['og_image'] = $post->og_image;
            $payload['canonical_url'] = $post->canonical_url;
        }

        return $payload;
    }
}
