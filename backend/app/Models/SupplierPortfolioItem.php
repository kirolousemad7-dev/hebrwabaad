<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\SupplierPortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id',
    'title',
    'description',
    'image',
    'gallery',
    'category',
    'tags',
    'external_url',
    'sort_order',
    'is_active',
    'is_featured',
    'status',
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'tags' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
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
}
