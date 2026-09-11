<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\SupplierProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id',
    'name',
    'slug',
    'short_description',
    'description',
    'images',
    'category',
    'specifications',
    'variants',
    'price',
    'currency',
    'contact_for_price',
    'availability',
    'is_featured',
    'sort_order',
    'status',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
    'published_at',
    'submitted_at',
    'seo_title',
    'seo_description',
    'og_title',
    'og_description',
    'og_image',
    'canonical_url',
    'robots',
])]
class SupplierProduct extends Model
{
    /** @use HasFactory<SupplierProductFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'contact_for_price' => true,
        'availability' => 'CONTACT',
        'is_featured' => false,
        'sort_order' => 0,
        'status' => ContentStatus::Draft->value,
        'currency' => 'SAR',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
            'specifications' => 'array',
            'variants' => 'array',
            'price' => 'decimal:2',
            'contact_for_price' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'status' => ContentStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphMany<ContentReview, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'subject')->orderByDesc('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published);
    }
}
