<?php

namespace App\Models;

use App\Enums\PortfolioMediaType;
use Database\Factories\PortfolioMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'portfolio_item_id',
    'type',
    'title',
    'caption',
    'url',
    'content_media_id',
    'thumbnail_media_id',
    'thumbnail_url',
    'display_order',
    'is_featured',
    'is_public',
])]
class PortfolioMedia extends Model
{
    /** @use HasFactory<PortfolioMediaFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'display_order' => 0,
        'is_featured' => false,
        'is_public' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PortfolioMediaType::class,
            'display_order' => 'integer',
            'is_featured' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PortfolioItem, $this>
     */
    public function portfolioItem(): BelongsTo
    {
        return $this->belongsTo(PortfolioItem::class);
    }
}
