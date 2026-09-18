<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'title',
    'slug',
    'excerpt',
    'content',
    'featured_image',
    'author_id',
    'category_id',
    'status',
    'published_at',
    'seo_title',
    'meta_description',
    'og_image',
    'canonical_url',
    'created_by',
])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    use HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'DRAFT',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BlogAuthor, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(BlogAuthor::class, 'author_id');
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<BlogTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    /**
     * Publicly visible posts (published, or scheduled whose time has arrived).
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $now = now();

        $query->where(function (Builder $inner) use ($now): void {
            $inner->where(function (Builder $published) use ($now): void {
                $published->where('status', BlogPostStatus::Published)
                    ->where(function (Builder $dates) use ($now): void {
                        $dates->whereNull('published_at')
                            ->orWhere('published_at', '<=', $now);
                    });
            })->orWhere(function (Builder $scheduled) use ($now): void {
                $scheduled->where('status', BlogPostStatus::Scheduled)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', $now);
            });
        });
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status === BlogPostStatus::Archived || $this->status === BlogPostStatus::Draft) {
            return false;
        }

        if ($this->status === BlogPostStatus::Published) {
            return $this->published_at === null || $this->published_at->lte(now());
        }

        return $this->status === BlogPostStatus::Scheduled
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }
}
