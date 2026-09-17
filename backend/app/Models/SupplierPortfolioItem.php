<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\SupplierVisibility;
use Database\Factories\SupplierPortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id',
    'title',
    'description',
    'image',
    'gallery',
    'videos',
    'documents',
    'category',
    'client_type',
    'tags',
    'external_url',
    'completion_date',
    'sort_order',
    'is_active',
    'is_featured',
    'status',
    'visibility',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
    'published_at',
    'submitted_at',
])]
class SupplierPortfolioItem extends Model
{
    /** @use HasFactory<SupplierPortfolioItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'is_featured' => false,
        'status' => ContentStatus::Draft->value,
        'visibility' => SupplierVisibility::Internal->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'videos' => 'array',
            'documents' => 'array',
            'tags' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'status' => ContentStatus::class,
            'visibility' => SupplierVisibility::class,
            'completion_date' => 'date',
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
     * @return MorphToMany<Tag, $this>
     */
    public function catalogTags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }
}
