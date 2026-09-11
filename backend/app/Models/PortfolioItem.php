<?php

namespace App\Models;

use App\Enums\PortfolioCategory;
use App\Enums\PortfolioMediaType;
use App\Models\Concerns\HasSlug;
use Database\Factories\PortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'brand_name',
    'category',
    'description',
    'short_description',
    'challenge',
    'solution',
    'execution',
    'deliverables',
    'results',
    'tags',
    'image_url',
    'project_url',
    'video_url',
    'primary_media_type',
    'is_sample',
    'is_published',
    'is_featured',
    'sort_order',
    'work_submission_id',
    'package_id',
])]
class PortfolioItem extends Model
{
    /** @use HasFactory<PortfolioItemFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_sample' => true,
        'is_published' => true,
        'is_featured' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PortfolioCategory::class,
            'primary_media_type' => PortfolioMediaType::class,
            'tags' => 'array',
            'deliverables' => 'array',
            'is_sample' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PortfolioItem $item): void {
            if (! filled($item->slug) && filled($item->title)) {
                $item->slug = static::uniqueSlug((string) $item->title, $item->id);
            }
        });
    }

    public function workSubmission(): BelongsTo
    {
        return $this->belongsTo(WorkSubmission::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsToMany<Sector, $this>
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_portfolio_item')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'portfolio_item_service')->withTimestamps();
    }

    /**
     * @return HasMany<PortfolioMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PortfolioMedia::class)->orderBy('display_order')->orderBy('id');
    }

    /**
     * @return HasMany<PortfolioMedia, $this>
     */
    public function publicMedia(): HasMany
    {
        return $this->media()->where('is_public', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
