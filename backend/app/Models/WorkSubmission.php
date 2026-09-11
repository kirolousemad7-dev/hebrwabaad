<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\PortfolioCategory;
use Database\Factories\WorkSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'title',
    'description',
    'category',
    'service_id',
    'cover_media_id',
    'gallery_media_ids',
    'tags',
    'tools',
    'project_url',
    'video_url',
    'client_label',
    'employee_notes',
    'status',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
    'published_at',
    'published_portfolio_item_id',
])]
class WorkSubmission extends Model
{
    /** @use HasFactory<WorkSubmissionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ContentStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PortfolioCategory::class,
            'gallery_media_ids' => 'array',
            'tags' => 'array',
            'tools' => 'array',
            'status' => ContentStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<ContentMedia, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(ContentMedia::class, 'cover_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<PortfolioItem, $this>
     */
    public function publishedPortfolioItem(): BelongsTo
    {
        return $this->belongsTo(PortfolioItem::class, 'published_portfolio_item_id');
    }

    /**
     * @return MorphMany<ContentReview, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'subject')->orderByDesc('id');
    }

    /**
     * @return HasMany<ContentMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ContentMedia::class, 'attachable_id')
            ->where('attachable_type', $this->getMorphClass());
    }

    public function coverUrl(): ?string
    {
        return $this->coverMedia?->url();
    }
}
