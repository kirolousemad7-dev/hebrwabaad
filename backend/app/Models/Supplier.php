<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Concerns\HasSlug;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'user_id',
    'name',
    'slug',
    'logo',
    'cover_image',
    'short_description',
    'description',
    'specialties',
    'services',
    'location',
    'address',
    'email',
    'phone',
    'website',
    'category',
    'brand_colors',
    'brand_description',
    'years_experience',
    'min_order_info',
    'sort_order',
    'is_active',
    'is_featured',
    'is_published',
    'profile_status',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
    'published_at',
    'seo_title',
    'seo_description',
    'og_title',
    'og_description',
    'og_image',
    'canonical_url',
    'robots',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
        'is_published' => true,
        'profile_status' => ContentStatus::Published->value,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'services' => 'array',
            'brand_colors' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'years_experience' => 'integer',
            'profile_status' => ContentStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<SupplierPortfolioItem, $this>
     */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(SupplierPortfolioItem::class);
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * @return HasMany<SupplierProfileVersion, $this>
     */
    public function profileVersions(): HasMany
    {
        return $this->hasMany(SupplierProfileVersion::class);
    }

    /**
     * @return HasOne<SupplierProfileVersion, $this>
     */
    public function pendingProfileVersion(): HasOne
    {
        return $this->hasOne(SupplierProfileVersion::class)->ofMany(
            ['id' => 'max'],
            function (Builder $query): void {
                $query->whereIn('status', [
                    ContentStatus::Draft->value,
                    ContentStatus::Submitted->value,
                    ContentStatus::UnderReview->value,
                    ContentStatus::ChangesRequested->value,
                    ContentStatus::Rejected->value,
                ]);
            },
        );
    }

    /**
     * @return MorphMany<ContentReview, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'subject')->orderByDesc('id');
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)
            ->where('profile_status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->active()->published();
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_active
            && $this->is_published
            && $this->profile_status === ContentStatus::Published;
    }

    /**
     * @return HasMany<SupplierPortfolioItem, $this>
     */
    public function publicPortfolioItems(): HasMany
    {
        return $this->portfolioItems()
            ->where('is_active', true)
            ->where('status', ContentStatus::Published)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function publicProducts(): HasMany
    {
        return $this->products()
            ->published()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function featuredPublicProducts(): HasMany
    {
        return $this->publicProducts()->where('is_featured', true);
    }

    public function belongsToUser(?User $user): bool
    {
        return $user !== null
            && $user->role === UserRole::Supplier
            && $this->user_id !== null
            && $this->user_id === $user->id;
    }
}
