<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\SupplierVisibility;
use App\Enums\UserRole;
use App\Models\Concerns\HasSlug;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'company_id',
    'supplier_code',
    'name',
    'legal_name',
    'display_name',
    'slug',
    'logo',
    'cover_image',
    'short_description',
    'description',
    'specialties',
    'services',
    'location',
    'country',
    'city',
    'address',
    'email',
    'phone',
    'whatsapp',
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
    'show_public_contact',
    'status',
    'verification_status',
    'onboarding_status',
    'rating',
    'profile_status',
    'review_notes',
    'notes',
    'internal_notes',
    'locked_fields',
    'contact_person',
    'owner_change_request',
    'reviewed_by',
    'reviewed_at',
    'published_at',
    'created_by',
    'updated_by',
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
    use HasFactory, HasSlug, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_featured' => false,
        'is_published' => true,
        'show_public_contact' => false,
        'profile_status' => ContentStatus::Published->value,
        'status' => SupplierStatus::Pending->value,
        'verification_status' => SupplierVerificationStatus::Unverified->value,
        'onboarding_status' => SupplierOnboardingStatus::Draft->value,
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
            'show_public_contact' => 'boolean',
            'sort_order' => 'integer',
            'years_experience' => 'integer',
            'rating' => 'decimal:2',
            'profile_status' => ContentStatus::class,
            'status' => SupplierStatus::class,
            'verification_status' => SupplierVerificationStatus::class,
            'onboarding_status' => SupplierOnboardingStatus::class,
            'locked_fields' => 'array',
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
     * @return BelongsTo<CrmCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
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
     * @return HasMany<SupplierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /**
     * @return HasMany<SupplierService, $this>
     */
    public function offeredServices(): HasMany
    {
        return $this->hasMany(SupplierService::class);
    }

    /**
     * @return HasMany<SupplierDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    /**
     * @return BelongsToMany<SupplierCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(SupplierCategory::class, 'supplier_category_supplier')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
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
            ->where('visibility', SupplierVisibility::Public)
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
            ->where('visibility', SupplierVisibility::Public)
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

    public function publicDisplayName(): string
    {
        return $this->display_name ?: $this->name;
    }
}
